<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Espace du client connecté : informations personnelles, demande de suppression du compte et avis après événement.
 * Toutes les requêtes sont limitées au compte de la session ; le compte MySQL dédié ne peut rien modifier d'autre.
 */
final class ClientSpace
{
    /** Champs d'adresse facultatifs de la fiche client, avec leur longueur maximale. */
    private const OPTIONAL = ['adresse_ligne1' => 255, 'adresse_ligne2' => 255, 'code_postal' => 20, 'ville' => 120];

    /** Texte saisi nettoyé (espaces retirés aux extrémités), ou null s'il est invalide ou trop long. */
    private static function text(mixed $value, int $max): ?string
    {
        if (!is_string($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }
        $value = trim($value);
        return preg_match_all('/./us', $value) > $max ? null : $value;
    }

    /**
     * Informations du client et état de sa demande de suppression.
     * Un compte créé par inscription peut ne pas avoir de fiche client : seuls le prénom et le nom sont alors modifiables.
     */
    public function profile(): array
    {
        $user = (new Authentication())->requireUser(['client']);
        $db = Connections::clientSpace();

        $query = $db->prepare('SELECT u.email, u.prenom, u.nom, c.id AS client_id, c.entreprise, c.telephone, c.adresse_ligne1, c.adresse_ligne2, c.code_postal, c.ville
            FROM utilisateurs u LEFT JOIN clients c ON c.utilisateur_id = u.id WHERE u.id = ?');
        $query->execute([$user['id']]);
        $row = $query->fetch();
        if (!$row) {
            throw new HttpError(401, 'authentication_required');
        }

        $query = $db->prepare('SELECT statut, cree_le FROM demandes_suppression WHERE utilisateur_id = ? ORDER BY id DESC LIMIT 1');
        $query->execute([$user['id']]);
        $request = $query->fetch();

        $hasClientRecord = $row['client_id'] !== null;
        $profile = ['email' => $row['email'], 'prenom' => $row['prenom'], 'nom' => $row['nom'], 'fiche_client' => $hasClientRecord];
        if ($hasClientRecord) {
            $profile += array_intersect_key($row, array_flip(['entreprise', 'telephone', 'adresse_ligne1', 'adresse_ligne2', 'code_postal', 'ville']));
        }

        return [
            'profil'      => $profile,
            'suppression' => $request ? ['statut' => $request['statut'], 'demandee_le' => $request['cree_le']] : null,
        ];
    }

    /**
     * Enregistre les informations modifiées par le client (jamais l'adresse de connexion ni le rôle).
     * Le nom est mis à jour à la fois sur le compte et sur la fiche client ; la modification est journalisée.
     */
    public function saveProfile(array $input): array
    {
        $user = (new Authentication())->requireUser(['client']);
        $db = Connections::clientSpace();

        $query = $db->prepare('SELECT id FROM clients WHERE utilisateur_id = ?');
        $query->execute([$user['id']]);
        $clientId = $query->fetchColumn();

        $data = [];
        $errors = [];

        // Champs obligatoires ; l'entreprise et le téléphone seulement s'il existe une fiche client.
        $required = ['prenom' => 100, 'nom' => 100] + ($clientId !== false ? ['entreprise' => 200, 'telephone' => 32] : []);
        foreach ($required as $key => $max) {
            $value = self::text($input[$key] ?? null, $max);
            if ($value === null || $value === '') {
                $errors[$key] = "Renseignez ce champ (maximum $max caractères).";
            } else {
                $data[$key] = $value;
            }
        }

        if ($clientId !== false) {
            foreach (self::OPTIONAL as $key => $max) {
                $value = self::text($input[$key] ?? '', $max);
                if ($value === null) {
                    $errors[$key] = "Maximum $max caractères.";
                } else {
                    $data[$key] = $value === '' ? null : $value;
                }
            }
        }

        if (isset($data['telephone']) && (!preg_match('/^[+0-9(). \-]+$/', $data['telephone']) || strlen(preg_replace('/\D/', '', $data['telephone'])) < 6)) {
            $errors['telephone'] = 'Saisissez un numéro de téléphone valide.';
        }
        if (isset($data['code_postal']) && !preg_match('/^[A-Za-z0-9 \-]{2,20}$/', $data['code_postal'])) {
            $errors['code_postal'] = 'Saisissez un code postal valide.';
        }
        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }

        try {
            $db->beginTransaction();
            $db->prepare('UPDATE utilisateurs SET prenom = ?, nom = ? WHERE id = ?')
                ->execute([$data['prenom'], $data['nom'], $user['id']]);

            if ($clientId !== false) {
                $db->prepare('UPDATE clients SET contact_prenom = ?, contact_nom = ?, entreprise = ?, telephone = ?, adresse_ligne1 = ?, adresse_ligne2 = ?, code_postal = ?, ville = ? WHERE id = ?')
                    ->execute([$data['prenom'], $data['nom'], $data['entreprise'], $data['telephone'], $data['adresse_ligne1'], $data['adresse_ligne2'], $data['code_postal'], $data['ville'], $clientId]);
                // Journal exigé : modification d'un client (identifiant uniquement).
                $db->prepare("INSERT INTO journal_a_emettre(id_trace, horodatage, type_action, id_utilisateur, details) VALUES(UUID(), UTC_TIMESTAMP(6), 'MODIFICATION_CLIENT', ?, ?)")
                    ->execute([$user['id'], json_encode(['id_client' => (string) $clientId], JSON_THROW_ON_ERROR)]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->profile();
    }

    /**
     * Demande de suppression totale des informations et du compte, avec confirmation explicite.
     * L'agence est prévenue par courriel ; le traitement est fait par l'administratrice (AdminAccounts::delete).
     */
    public function requestDeletion(array $input): array
    {
        $user = (new Authentication())->requireUser(['client']);

        if (($input['confirmation'] ?? null) !== true) {
            throw new HttpError(422, 'validation_failed', ['confirmation' => 'Cochez la case pour confirmer votre demande.']);
        }
        $reason = self::text($input['motif'] ?? '', 2000);
        if ($reason === null) {
            throw new HttpError(422, 'validation_failed', ['motif' => 'Le motif est limité à 2 000 caractères.']);
        }

        $db = Connections::clientSpace();
        try {
            $db->beginTransaction();
            $db->prepare('INSERT INTO demandes_suppression(utilisateur_id, motif) VALUES(?, ?)')
                ->execute([$user['id'], $reason === '' ? null : $reason]);

            $recipient = getenv('AGENCY_EMAIL') ?: 'agence@example.invalid';
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Agency configuration');
            }
            MailQueue::enqueue($db, 'suppression', $recipient, 'Demande de suppression de compte',
                "Le client {$user['prenom']} {$user['nom']} (compte n° {$user['id']}, {$user['email']}) demande la suppression totale de ses informations et de son compte.\n"
                . ($reason === '' ? 'Aucun motif indiqué.' : "Motif : $reason")
                . "\nTraitez la demande depuis l’administration des comptes, puis informez le client.", 10080);

            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // 23000 = contrainte d'unicité : une demande est déjà en attente pour ce compte.
            if ($e->getCode() === '23000') {
                throw new HttpError(409, 'deletion_already_requested');
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->profile();
    }

    /** Événements du client terminés (statut ou date de fin passée), avec l'avis déjà donné le cas échéant. */
    public function reviews(): array
    {
        $user = (new Authentication())->requireUser(['client']);

        $query = Connections::clientSpace()->prepare(
            "SELECT e.id, e.nom, e.debut, e.fin, e.lieu, a.contenu, a.statut AS avis_statut, a.cree_le AS avis_le
             FROM evenements e
             JOIN clients c ON c.id = e.client_id
             LEFT JOIN avis a ON a.evenement_id = e.id AND a.auteur_id = ?
             WHERE c.utilisateur_id = ? AND e.statut NOT IN ('brouillon', 'annule') AND (e.statut = 'termine' OR e.fin < UTC_TIMESTAMP())
             ORDER BY e.fin DESC, e.id DESC
             LIMIT 50"
        );
        $query->execute([$user['id'], $user['id']]);

        return ['evenements' => array_map(static fn(array $r) => [
            'id'    => (string) $r['id'],
            'nom'   => $r['nom'] ?? 'Événement sans nom',
            'debut' => EventSchedule::local($r['debut']),
            'fin'   => EventSchedule::local($r['fin']),
            'lieu'  => $r['lieu'],
            'avis'  => $r['avis_statut'] === null ? null : ['contenu' => $r['contenu'], 'statut' => $r['avis_statut'], 'depose_le' => $r['avis_le']],
        ], $query->fetchAll())];
    }

    /** Dépôt d'un avis (10 à 2 000 caractères) sur un événement terminé du client ; publié après validation par l'équipe. */
    public function submitReview(array $input): array
    {
        $user = (new Authentication())->requireUser(['client']);

        $id = $input['evenement_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,17}$/D', $id)) {
            throw new HttpError(422, 'invalid_id');
        }
        $text = self::text($input['contenu'] ?? null, 2000);
        if ($text === null || preg_match_all('/./us', $text) < 10) {
            throw new HttpError(422, 'validation_failed', ['contenu' => 'Rédigez votre avis (entre 10 et 2 000 caractères).']);
        }

        $db = Connections::clientSpace();
        $query = $db->prepare(
            "SELECT e.id FROM evenements e JOIN clients c ON c.id = e.client_id
             WHERE e.id = ? AND c.utilisateur_id = ? AND e.statut NOT IN ('brouillon', 'annule') AND (e.statut = 'termine' OR e.fin < UTC_TIMESTAMP())"
        );
        $query->execute([$id, $user['id']]);
        // Événement d'un autre client, inexistant ou non terminé : même réponse.
        if ($query->fetchColumn() === false) {
            throw new HttpError(404, 'event_not_reviewable');
        }

        try {
            $db->prepare('INSERT INTO avis(evenement_id, auteur_id, contenu) VALUES(?, ?, ?)')->execute([$id, $user['id'], $text]);
        } catch (\PDOException $e) {
            // Un seul avis par client et par événement (contrainte d'unicité en base).
            if ($e->getCode() === '23000') {
                throw new HttpError(409, 'review_exists');
            }
            throw $e;
        }

        return $this->reviews();
    }
}
