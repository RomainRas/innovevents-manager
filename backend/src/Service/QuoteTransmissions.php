<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Transmission du devis au client : envoi par la plateforme (courriel avec PDF joint)
 * ou déclaration d'un envoi manuel. La première transmission fige la version,
 * la passe en « étude côté client » et crée le compte client s'il n'existe pas.
 */
final class QuoteTransmissions
{
    private function id(mixed $v): string
    {
        if (!(is_int($v) || is_string($v)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $v)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $v;
    }

    /** Exécute l'opération sous le verrou partagé du devis et dans une transaction (voir QuotePdfs::locked). */
    private function locked(string $id, callable $operation): mixed
    {
        $db = Connections::quoteSend();
        $lock = 'innovevents.quote.' . $id;
        $query = $db->prepare('SELECT GET_LOCK(?, 5)');
        $query->execute([$lock]);
        if ((int) $query->fetchColumn() !== 1) {
            throw new HttpError(503, 'quote_busy');
        }

        try {
            $db->beginTransaction();
            $result = $operation($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } finally {
            $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
    }

    /**
     * Version courante du devis de l'événement, avec le client et l'historique des transmissions.
     * Avec $lock, le client et la version sont verrouillés pour être modifiés.
     */
    private function state(\PDO $db, string $id, bool $lock = false): array
    {
        $query = $db->prepare('SELECT id, nom, client_id FROM evenements WHERE id = ?');
        $query->execute([$id]);
        $event = $query->fetch();
        if (!$event) {
            throw new HttpError(404, 'event_not_found');
        }

        $query = $db->prepare('SELECT id, utilisateur_id, entreprise, contact_prenom, contact_nom, email FROM clients WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$event['client_id']]);
        $client = $query->fetch();

        $query = $db->prepare('SELECT v.id, v.numero, v.statut, v.revision_contenu, v.client_snapshot, d.reference FROM devis d JOIN versions_devis v ON v.id = d.version_courante_id AND v.devis_id = d.id WHERE d.evenement_id = ?' . ($lock ? ' FOR UPDATE OF v' : ''));
        $query->execute([$id]);
        $quote = $query->fetch() ?: null;

        $history = [];
        if ($quote) {
            $query = $db->prepare('SELECT t.id, t.document_id, t.mode, t.destinataire, t.etat, t.cree_le, t.confirmee_le FROM transmissions_devis t JOIN documents_devis p ON p.id = t.document_id WHERE p.version_id = ? ORDER BY t.id');
            $query->execute([$quote['id']]);
            $history = $query->fetchAll();
        }

        return ['event' => $event, 'client' => $client, 'quote' => $quote, 'history' => $history];
    }

    /**
     * Document que l'administratrice peut transmettre, ou null :
     * - devis brouillon : le PDF à jour (même empreinte que les données actuelles) ;
     * - devis déjà en étude chez le client : le PDF déjà transmis (renvoi), à l'adresse figée lors du premier envoi.
     */
    private function sendable(\PDO $db, array $state, string $id): ?array
    {
        $quote = $state['quote'];
        if (!$quote) {
            return null;
        }
        $pdfs = new QuotePdfs();

        if ($quote['statut'] === 'brouillon') {
            try {
                $source = $pdfs->source($db, $id);
            } catch (HttpError $e) {
                if (in_array($e->status, [409, 422], true)) {
                    return null;
                }
                throw $e;
            }

            $query = $db->prepare("SELECT * FROM documents_devis WHERE version_id = ? AND revision_contenu = ? AND source_empreinte = ? AND etat = 'disponible' ORDER BY id DESC LIMIT 1");
            $query->execute([$source['quote']['id'], $source['quote']['revision_contenu'], $pdfs->hash($source)]);
            $document = $query->fetch();

            return $document && $pdfs->bytes($document) !== null
                ? ['document' => $document, 'email' => $source['client']['email'], 'prenom' => $source['client']['contact_prenom'], 'event' => $source['event']['nom']]
                : null;
        }

        if ($quote['statut'] !== 'etude_client' || !$state['history']) {
            return null;
        }

        $query = $db->prepare('SELECT * FROM documents_devis WHERE id = ?');
        $query->execute([$state['history'][0]['document_id']]);
        $document = $query->fetch();
        $snapshot = json_decode((string) $quote['client_snapshot'], true);

        return $document && is_array($snapshot) && $pdfs->bytes($document) !== null
            ? ['document' => $document, 'email' => $snapshot['email'], 'prenom' => $snapshot['contact_prenom'], 'event' => $state['event']['nom']]
            : null;
    }

    /** Historique des transmissions sous la forme envoyée au navigateur. */
    private function publicHistory(array $history): array
    {
        return array_map(static fn($t) => [
            'id'           => (string) $t['id'],
            'mode'         => $t['mode'],
            'destinataire' => $t['destinataire'],
            'etat'         => $t['etat'],
            'cree_le'      => $t['cree_le'],
            'confirmee_le' => $t['confirmee_le'],
        ], $history);
    }

    /** État du devis pour l'écran de transmission : statut, destinataire, PDF transmissible, historique, réponses du client. */
    public function status(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);

        return $this->locked($id, function ($db) use ($id) {
            $state = $this->state($db, $id);
            $quote = $state['quote'];
            $send = $this->sendable($db, $state, $id);

            return [
                'statut'        => $quote['statut'] ?? null,
                'reference'     => $quote['reference'] ?? null,
                'version'       => $quote ? (int) $quote['numero'] : null,
                'destinataire'  => $send['email'] ?? $state['client']['email'],
                'compte_client' => $state['client']['utilisateur_id'] !== null,
                'document_id'   => $send ? (string) $send['document']['id'] : null,
                'en_attente'    => (bool) array_filter($state['history'], static fn($t) => $t['etat'] === 'en_attente'),
                'transmissions' => $this->publicHistory($state['history']),
                'decisions'     => $this->decisions($db, $id),
            ];
        });
    }

    /** Réponses du client sur toutes les versions du devis, la plus récente d'abord. */
    private function decisions(\PDO $db, string $eventId): array
    {
        $query = $db->prepare('SELECT v.numero, x.choix, x.motif, x.decidee_le FROM devis d JOIN versions_devis v ON v.devis_id = d.id JOIN decisions_devis x ON x.version_id = v.id WHERE d.evenement_id = ? ORDER BY v.numero DESC');
        $query->execute([$eventId]);

        return array_map(static fn($r) => [
            'version'    => (int) $r['numero'],
            'choix'      => $r['choix'],
            'motif'      => $r['motif'],
            'decidee_le' => $r['decidee_le'],
        ], $query->fetchAll());
    }

    /**
     * Suivi administrateur : version courante de chaque devis, filtrable par statut.
     * Les devis qui demandent une action (modification demandée, accepté) apparaissent en premier.
     */
    public function overview(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $status = $input['statut'] ?? '';
        if (!in_array($status, ['', 'brouillon', 'etude_client', 'modification', 'accepte', 'refuse'], true)) {
            throw new HttpError(422, 'invalid_status');
        }

        $query = Connections::quoteSend()->prepare(
            "SELECT d.reference, d.evenement_id, e.nom, e.statut AS evenement_statut, c.entreprise, v.numero, v.statut, v.total_ttc, v.modifie_le,
                    (SELECT x.decidee_le FROM decisions_devis x WHERE x.version_id = v.id) AS decidee_le
             FROM devis d
             JOIN versions_devis v ON v.id = d.version_courante_id AND v.devis_id = d.id
             JOIN evenements e ON e.id = d.evenement_id
             JOIN clients c ON c.id = e.client_id
             WHERE (? = '' OR v.statut = ?)
             ORDER BY FIELD(v.statut, 'modification', 'accepte', 'etude_client', 'brouillon', 'refuse'), v.modifie_le DESC
             LIMIT 100"
        );
        $query->execute([$status, $status]);

        return ['devis' => array_map(static fn($r) => [
            'reference'        => $r['reference'],
            'evenement_id'     => (string) $r['evenement_id'],
            'evenement'        => $r['nom'],
            'evenement_statut' => $r['evenement_statut'],
            'client'           => $r['entreprise'],
            'version'          => (int) $r['numero'],
            'statut'           => $r['statut'],
            'total_ttc'        => $r['total_ttc'],
            'decidee_le'       => $r['decidee_le'],
        ], $query->fetchAll())];
    }

    /**
     * Transmet le devis : « automatique » (courriel avec PDF joint) ou « manuel » (envoi fait hors plateforme, confirmé).
     * La clé d'opération (UUID) envoyée par le navigateur évite un double envoi en cas de réponse perdue.
     */
    public function send(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);
        $documentId = $this->id($input['document_id'] ?? null);

        $mode = $input['mode'] ?? null;
        if (!in_array($mode, ['automatique', 'manuel'], true)) {
            throw new HttpError(422, 'invalid_mode');
        }
        $key = $input['cle'] ?? null;
        if (!is_string($key) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $key)) {
            throw new HttpError(422, 'invalid_key');
        }
        if ($mode === 'manuel' && ($input['confirmation'] ?? null) !== true) {
            throw new HttpError(422, 'manual_confirmation_required');
        }

        return $this->locked($id, function ($db) use ($id, $documentId, $mode, $key, $user) {
            // Répétition d'une demande déjà enregistrée (réponse perdue) : même résultat, sans nouvel envoi.
            $query = $db->prepare('SELECT t.*, v.statut, d.evenement_id FROM transmissions_devis t JOIN documents_devis p ON p.id = t.document_id JOIN versions_devis v ON v.id = p.version_id JOIN devis d ON d.id = v.devis_id WHERE t.cle_idempotence = ?');
            $query->execute([$key]);
            $previous = $query->fetch();
            if ($previous) {
                if ((string) $previous['document_id'] !== $documentId || $previous['mode'] !== $mode || (string) $previous['evenement_id'] !== $id) {
                    throw new HttpError(409, 'idempotency_conflict');
                }
                return ['transmission' => $this->publicHistory([$previous])[0], 'statut' => $previous['statut'], 'compte_cree' => false, 'repetition' => true];
            }

            $state = $this->state($db, $id, true);
            $quote = $state['quote'];
            if (!$quote || !in_array($quote['statut'], ['brouillon', 'etude_client'], true)) {
                throw new HttpError(409, 'quote_not_transmissible');
            }
            // Un envoi automatique encore dans la file : on attend qu'il parte avant d'en accepter un autre.
            if (array_filter($state['history'], static fn($t) => $t['etat'] === 'en_attente')) {
                throw new HttpError(409, 'transmission_pending');
            }
            $send = $this->sendable($db, $state, $id);
            // Le PDF affiché à l'écran doit être exactement celui qu'on transmet.
            if (!$send || (string) $send['document']['id'] !== $documentId) {
                throw new HttpError(409, 'pdf_obsolete');
            }

            $created = false;
            if ($quote['statut'] === 'brouillon') {
                // Première transmission : création du compte client s'il n'en a pas encore.
                $client = $state['client'];
                if ($client['utilisateur_id'] === null) {
                    $email = strtolower(trim($client['email']));
                    $query = $db->prepare('SELECT id FROM utilisateurs WHERE email_normalise = ?');
                    $query->execute([$email]);
                    // Un compte existant n'est jamais rattaché automatiquement : l'inscription ne prouve pas la possession de l'adresse.
                    if ($query->fetchColumn() !== false) {
                        throw new HttpError(409, 'account_email_conflict');
                    }

                    // Mot de passe par défaut aléatoire, à changer obligatoirement à la première connexion.
                    $password = 'Ie!7' . bin2hex(random_bytes(10));
                    $username = mb_substr(trim($client['contact_prenom'] . ' ' . $client['contact_nom']), 0, 100);
                    $db->prepare("INSERT INTO utilisateurs(email, email_normalise, prenom, nom, nom_utilisateur, mot_de_passe_hash, role, changement_mdp_requis) VALUES(?, ?, ?, ?, ?, ?, 'client', 1)")
                        ->execute([trim($client['email']), $email, $client['contact_prenom'], $client['contact_nom'], $username, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);

                    $query = $db->prepare('UPDATE clients SET utilisateur_id = ? WHERE id = ? AND utilisateur_id IS NULL');
                    $query->execute([$db->lastInsertId(), $client['id']]);
                    if ($query->rowCount() !== 1) {
                        throw new HttpError(409, 'client_changed');
                    }

                    MailQueue::enqueue($db, 'compte_client', trim($client['email']), 'Votre espace client Innov’Events',
                        "Bonjour {$client['contact_prenom']},\nInnov’Events vous a ouvert un espace client pour consulter vos devis.\nIdentifiant : " . trim($client['email']) . "\nMot de passe par défaut : $password\nÀ votre première connexion, vous devrez choisir un nouveau mot de passe.\nConnexion : " . $this->origin() . "/connexion", 10080);
                    $created = true;
                }

                // La version est figée : elle ne pourra plus être modifiée, seulement remplacée par une contre-proposition.
                $query = $db->prepare("UPDATE versions_devis SET statut = 'etude_client', figee_le = UTC_TIMESTAMP(6), transmise_le = UTC_TIMESTAMP(6) WHERE id = ? AND statut = 'brouillon'");
                $query->execute([$quote['id']]);
                if ($query->rowCount() !== 1) {
                    throw new HttpError(409, 'quote_revision_conflict');
                }
            }

            $document = $send['document'];
            $mailId = null;
            if ($mode === 'automatique') {
                // Le PDF est joint par mail_worker, qui revérifie son empreinte avant l'envoi.
                $name = 'devis-' . preg_replace('/[^A-Za-z0-9-]/', '-', $quote['reference']) . '-v' . $quote['numero'] . '.pdf';
                $mailId = MailQueue::enqueue($db, 'devis', $send['email'], 'Votre devis Innov’Events ' . $quote['reference'],
                    "Bonjour {$send['prenom']},\nVeuillez trouver ci-joint le devis {$quote['reference']} (version {$quote['numero']}) pour l’événement « {$send['event']} ».\nVous pouvez aussi le consulter et y répondre depuis votre espace client : " . $this->origin() . "/espace\nL’équipe Innov’Events", 2880,
                    ['reference' => $document['fichier_reference'], 'sha256' => $document['empreinte_sha256'], 'name' => $name]);
            }

            // Envoi manuel : confirmé tout de suite ; envoi automatique : « en attente » jusqu'au départ du courriel.
            $db->prepare('INSERT INTO transmissions_devis(document_id, acteur_id, mode, destinataire, etat, cle_idempotence, confirmee_le, courriel_id) VALUES(?, ?, ?, ?, ?, ?, ' . ($mode === 'manuel' ? 'UTC_TIMESTAMP(6)' : 'NULL') . ', ?)')
                ->execute([$document['id'], $user['id'], $mode, $send['email'], $mode === 'manuel' ? 'confirme' : 'en_attente', $key, $mailId]);

            $query = $db->prepare('SELECT * FROM transmissions_devis WHERE id = ?');
            $query->execute([$db->lastInsertId()]);

            return ['transmission' => $this->publicHistory([$query->fetch()])[0], 'statut' => 'etude_client', 'compte_cree' => $created, 'repetition' => false];
        });
    }

    /** Adresse du site pour les liens des courriels (variable APP_ORIGIN, contrôlée). */
    private function origin(): string
    {
        $origin = getenv('APP_ORIGIN');
        return is_string($origin) && preg_match('#^https?://[A-Za-z0-9.:-]+$#D', $origin) ? $origin : 'http://127.0.0.1:5173';
    }
}
