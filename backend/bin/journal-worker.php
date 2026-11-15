<?php
declare(strict_types=1);

/**
 * Traitement de fond « journal_worker » : livre dans MongoDB les entrées du journal des actions.
 *
 * Les services écrivent chaque action sensible dans la table MySQL journal_a_emettre, dans la même transaction
 * que l'action elle-même (rien n'est perdu si MongoDB est indisponible à ce moment-là). Ce programme tourne en
 * continu et recopie ces entrées dans la collection « logs » de MongoDB, une par une, avec reprise en cas d'erreur.
 *
 * Option --once : traite une seule entrée puis s'arrête (utilisé par les tests).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use InnovEvents\Infrastructure\Connections;
use MongoDB\BSON\UTCDateTime;

$once = in_array('--once', $argv, true);

do {
    $db = null;
    try {
        $db = Connections::journal();
        $db->beginTransaction();

        // Plus ancienne entrée à livrer. SKIP LOCKED : si plusieurs traitements tournent, ils ne prennent jamais la même.
        $row = $db->query("SELECT * FROM journal_a_emettre WHERE etat = 'en_attente' AND (prochain_essai_le IS NULL OR prochain_essai_le <= UTC_TIMESTAMP(6)) ORDER BY horodatage, id_trace LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if (!$row) {
            $db->commit();
            if (!$once) {
                sleep(2);
            }
            continue;
        }

        try {
            // Document au format exigé par l'énoncé : horodatage (ISODate), type_action, id_utilisateur, details.
            $document = [
                'horodatage'     => new UTCDateTime(new DateTimeImmutable($row['horodatage'], new DateTimeZone('UTC'))),
                'type_action'    => $row['type_action'],
                'id_utilisateur' => $row['id_utilisateur'] === null ? null : (string) $row['id_utilisateur'],
                'details'        => json_decode($row['details'], true, 16, JSON_THROW_ON_ERROR),
                'id_trace'       => $row['id_trace'],
                'version_schema' => (int) $row['version_schema'],
            ];

            try {
                Connections::audit()->selectCollection('innovevents_demo', 'logs')
                    ->insertOne($document, ['writeConcern' => new MongoDB\Driver\WriteConcern(1, 2000, true)]);
            } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
                // 11000 = document déjà présent (livré lors d'un essai précédent interrompu) : on le considère livré.
                if ($e->getCode() !== 11000) {
                    throw $e;
                }
            }

            $db->prepare("UPDATE journal_a_emettre SET etat = 'livre', livre_le = UTC_TIMESTAMP(6), tentatives = tentatives + 1, prochain_essai_le = NULL, derniere_erreur = NULL WHERE id_trace = ?")
                ->execute([$row['id_trace']]);
        } catch (Throwable $e) {
            // Échec (MongoDB arrêté, document refusé par le schéma…) : nouvel essai dans 60 secondes, abandon après 5 essais.
            $db->prepare('UPDATE journal_a_emettre SET tentatives = tentatives + 1, etat = ?, prochain_essai_le = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 60 SECOND), derniere_erreur = ? WHERE id_trace = ?')
                ->execute([(int) $row['tentatives'] >= 4 ? 'echec' : 'en_attente', get_class($e), $row['id_trace']]);
            error_log('Journal delivery failed: ' . get_class($e));
        }

        $db->commit();
    } catch (Throwable $e) {
        // Base MySQL indisponible : on attend 5 secondes avant de réessayer.
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Journal queue unavailable');
        if ($once) {
            exit(1);
        }
        sleep(5);
    }
} while (!$once);
