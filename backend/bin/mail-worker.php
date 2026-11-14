<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Service\MailQueue;
use PHPMailer\PHPMailer\PHPMailer;
$once=in_array('--once',$argv,true);
do {
    $db=null;
    try {
        $db=Connections::mail();$db->beginTransaction();
        $row=$db->query("SELECT *,expire_le<=UTC_TIMESTAMP() AS expired FROM courriels_a_envoyer WHERE etat='attente' AND prochain_essai_le<=UTC_TIMESTAMP() ORDER BY cree_le,id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if (!$row) {$db->commit();if (!$once) sleep(2);continue;}
        // Transmission de devis liée à ce courriel : son état suit le résultat de l'envoi.
        $transmission=$db->prepare("UPDATE transmissions_devis SET etat=?,confirmee_le=IF(?='confirme',UTC_TIMESTAMP(6),NULL) WHERE courriel_id=? AND etat='en_attente'");
        if ($row['expired']) {
            $db->prepare("UPDATE courriels_a_envoyer SET etat='expire',contenu_chiffre=NULL WHERE id=?")->execute([$row['id']]);
            $transmission->execute(['echec','echec',$row['id']]);
        } else {
            try {
                $data=MailQueue::decode($row['contenu_chiffre']);
                // Local : Mailpit sans authentification. Production : SMTP réel (SMTP_* et secret smtp_password).
                $mail=new PHPMailer(true);$mail->isSMTP();$mail->Host=getenv('SMTP_HOST')?:'mailpit';$mail->Port=(int)(getenv('SMTP_PORT')?:1025);$mail->Timeout=10;
                $secure=getenv('SMTP_SECURE')?:'';
                if ($secure==='ssl') $mail->SMTPSecure=PHPMailer::ENCRYPTION_SMTPS; elseif ($secure==='tls') $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS; else $mail->SMTPAutoTLS=false;
                $smtpUser=getenv('SMTP_USER')?:'';
                if ($smtpUser!=='') {
                    $password=trim((string)@file_get_contents('/run/secrets/smtp_password'));
                    if ($password==='') throw new \RuntimeException('SMTP password missing');
                    $mail->SMTPAuth=true;$mail->Username=$smtpUser;$mail->Password=$password;
                }
                $mail->CharSet='UTF-8';$mail->setFrom(getenv('MAIL_FROM')?:'contact@innovevents.example','Innov’Events');$mail->addAddress($data['email']);
                $mail->Subject=$data['subject'];$mail->Body=$data['text'];$mail->MessageID='<'.$row['id'].'@'.(explode('@',getenv('MAIL_FROM')?:'contact@innovevents.example')[1]??'innovevents.example').'>';
                if (isset($data['attachment'])) {
                    // Seul un PDF interne nommé par son empreinte est joint, après contrôle d'intégrité.
                    $a=$data['attachment'];$ref=$a['reference']??'';
                    if (!is_string($ref)||!preg_match('/^[a-f0-9]{64}\.pdf$/D',$ref)) throw new \RuntimeException('Attachment reference');
                    $bytes=@file_get_contents('/var/lib/innovevents/quote-pdfs/'.$ref);
                    if (!is_string($bytes)||!hash_equals((string)($a['sha256']??''),hash('sha256',$bytes))) throw new \RuntimeException('Attachment integrity');
                    $mail->addStringAttachment($bytes,(string)$a['name'],PHPMailer::ENCODING_BASE64,'application/pdf');
                }
                $mail->send();
                $db->prepare("UPDATE courriels_a_envoyer SET etat='envoye',contenu_chiffre=NULL,tentatives=tentatives+1,envoye_le=UTC_TIMESTAMP() WHERE id=?")->execute([$row['id']]);
                $transmission->execute(['confirme','confirme',$row['id']]);
            } catch (\Throwable $e) {
                $failed=(int)$row['tentatives']>=4;
                $q=$db->prepare("UPDATE courriels_a_envoyer SET tentatives=tentatives+1,etat=?,contenu_chiffre=?,prochain_essai_le=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND) WHERE id=?");
                $q->execute([$failed?'echec':'attente',$failed?null:$row['contenu_chiffre'],$row['id']]);
                if ($failed) $transmission->execute(['echec','echec',$row['id']]);
                error_log('Mail delivery failed: '.get_class($e));
            }
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db && $db->inTransaction())$db->rollBack();error_log('Mail queue unavailable');if ($once)exit(1);sleep(5);
    }
} while (!$once);
