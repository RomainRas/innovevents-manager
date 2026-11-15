<?php
declare(strict_types=1);

/**
 * Traitement de fond « mail_worker » : envoie les courriels placés dans la file courriels_a_envoyer (voir MailQueue).
 *
 * Un courriel à la fois : déchiffrement, envoi SMTP (Mailpit en local, serveur réel en production), puis
 * mise à jour de son état. En cas d'échec, nouvel essai toutes les 60 secondes, abandon après 5 essais
 * ou à l'expiration. Le contenu chiffré est effacé dès que le courriel est envoyé ou abandonné.
 *
 * Option --once : traite un seul courriel puis s'arrête (utilisé par les tests).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use InnovEvents\Infrastructure\Connections;
use InnovEvents\Service\MailQueue;
use PHPMailer\PHPMailer\PHPMailer;

$once = in_array('--once', $argv, true);

do {
    $db = null;
    try {
        $db = Connections::mail();
        $db->beginTransaction();

        // Plus ancien courriel prêt à partir. SKIP LOCKED : deux traitements ne prennent jamais le même courriel.
        $row = $db->query("SELECT *, expire_le <= UTC_TIMESTAMP() AS expired FROM courriels_a_envoyer WHERE etat = 'attente' AND prochain_essai_le <= UTC_TIMESTAMP() ORDER BY cree_le, id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if (!$row) {
            $db->commit();
            if (!$once) {
                sleep(2);
            }
            continue;
        }

        // Transmission de devis liée à ce courriel : son état suit le résultat de l'envoi.
        $transmission = $db->prepare("UPDATE transmissions_devis SET etat = ?, confirmee_le = IF(? = 'confirme', UTC_TIMESTAMP(6), NULL) WHERE courriel_id = ? AND etat = 'en_attente'");

        if ($row['expired']) {
            // Trop tard (ex. mot de passe temporaire de 30 minutes) : abandon sans envoi.
            $db->prepare("UPDATE courriels_a_envoyer SET etat = 'expire', contenu_chiffre = NULL WHERE id = ?")->execute([$row['id']]);
            $transmission->execute(['echec', 'echec', $row['id']]);
        } else {
            try {
                $data = MailQueue::decode($row['contenu_chiffre']);

                // Local : Mailpit sans authentification. Production : SMTP réel (SMTP_* et secret smtp_password).
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = getenv('SMTP_HOST') ?: 'mailpit';
                $mail->Port = (int) (getenv('SMTP_PORT') ?: 1025);
                $mail->Timeout = 10;

                $secure = getenv('SMTP_SECURE') ?: '';
                if ($secure === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($secure === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPAutoTLS = false;
                }

                $smtpUser = getenv('SMTP_USER') ?: '';
                if ($smtpUser !== '') {
                    $password = trim((string) @file_get_contents('/run/secrets/smtp_password'));
                    if ($password === '') {
                        throw new \RuntimeException('SMTP password missing');
                    }
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUser;
                    $mail->Password = $password;
                }

                $from = getenv('MAIL_FROM') ?: 'contact@innovevents.example';
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($from, 'Innov’Events');
                $mail->addAddress($data['email']);
                $mail->Subject = $data['subject'];
                $mail->Body = $data['text'];
                // Identifiant de message stable : un même courriel renvoyé après une coupure est reconnu comme doublon.
                $mail->MessageID = '<' . $row['id'] . '@' . (explode('@', $from)[1] ?? 'innovevents.example') . '>';

                if (isset($data['attachment'])) {
                    // Seul un PDF interne nommé par son empreinte est joint, après contrôle d'intégrité.
                    $attachment = $data['attachment'];
                    $reference = $attachment['reference'] ?? '';
                    if (!is_string($reference) || !preg_match('/^[a-f0-9]{64}\.pdf$/D', $reference)) {
                        throw new \RuntimeException('Attachment reference');
                    }
                    $bytes = @file_get_contents('/var/lib/innovevents/quote-pdfs/' . $reference);
                    if (!is_string($bytes) || !hash_equals((string) ($attachment['sha256'] ?? ''), hash('sha256', $bytes))) {
                        throw new \RuntimeException('Attachment integrity');
                    }
                    $mail->addStringAttachment($bytes, (string) $attachment['name'], PHPMailer::ENCODING_BASE64, 'application/pdf');
                }

                $mail->send();

                $db->prepare("UPDATE courriels_a_envoyer SET etat = 'envoye', contenu_chiffre = NULL, tentatives = tentatives + 1, envoye_le = UTC_TIMESTAMP() WHERE id = ?")
                    ->execute([$row['id']]);
                $transmission->execute(['confirme', 'confirme', $row['id']]);
            } catch (\Throwable $e) {
                // Échec : nouvel essai dans 60 secondes, abandon (et contenu effacé) au 5e essai.
                $failed = (int) $row['tentatives'] >= 4;
                $db->prepare('UPDATE courriels_a_envoyer SET tentatives = tentatives + 1, etat = ?, contenu_chiffre = ?, prochain_essai_le = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 SECOND) WHERE id = ?')
                    ->execute([$failed ? 'echec' : 'attente', $failed ? null : $row['contenu_chiffre'], $row['id']]);
                if ($failed) {
                    $transmission->execute(['echec', 'echec', $row['id']]);
                }
                error_log('Mail delivery failed: ' . get_class($e));
            }
        }

        $db->commit();
    } catch (\Throwable $e) {
        // Base MySQL indisponible : on attend 5 secondes avant de réessayer.
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Mail queue unavailable');
        if ($once) {
            exit(1);
        }
        sleep(5);
    }
} while (!$once);
