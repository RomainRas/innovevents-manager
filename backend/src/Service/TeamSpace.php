<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Espace employé (hérité par l'administratrice) : consultation de tous les clients et événements,
 * notes collaboratives sur les événements et statut des tâches assignées.
 * Règle des notes : l'auteur modifie ou supprime ses notes ; l'administratrice peut modifier ou supprimer toutes les notes.
 */
final class TeamSpace
{
    private const ROLES = ['employe', 'administrateur'];

    /** Étape suivante d'une tâche : à faire → en cours → terminé (pas de retour en arrière). */
    private const NEXT = ['a_faire' => 'en_cours', 'en_cours' => 'termine'];

    private static function id(mixed $v): string
    {
        if (!(is_int($v) || is_string($v)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $v)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $v;
    }

    /** Texte de recherche (100 caractères au plus, sans caractère de contrôle). */
    private static function search(array $input): string
    {
        $q = $input['q'] ?? '';
        if (!is_string($q) || preg_match_all('/./us', $q) > 100 || preg_match('/[\x00-\x1F\x7F]/', $q)) {
            throw new HttpError(422, 'invalid_filter');
        }
        return trim($q);
    }

    /** Motif LIKE « contient » : les caractères spéciaux % et _ saisis sont cherchés tels quels. */
    private static function like(string $q): string
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }

    /** Événement sous la forme envoyée au navigateur ; avec le client si la requête l'a lu. */
    private static function event(array $r): array
    {
        $event = [
            'id'     => (string) $r['id'],
            'nom'    => $r['nom'] ?? 'Événement sans nom',
            'debut'  => EventSchedule::local($r['debut']),
            'fin'    => EventSchedule::local($r['fin']),
            'lieu'   => $r['lieu'],
            'statut' => $r['statut'],
        ];
        if (array_key_exists('entreprise', $r)) {
            $event += ['client' => $r['entreprise'], 'client_id' => (string) $r['client_id']];
        }
        return $event;
    }

    /** Liste de tous les clients, avec recherche (entreprise, contact, adresse électronique, ville). */
    public function clients(array $input): array
    {
        (new Authentication())->requireUser(self::ROLES);
        $q = self::search($input);

        $where = '';
        $params = [];
        if ($q !== '') {
            $where = ' WHERE c.entreprise LIKE ? OR c.contact_nom LIKE ? OR c.contact_prenom LIKE ? OR c.email LIKE ? OR c.ville LIKE ?';
            $params = array_fill(0, 5, self::like($q));
        }

        $query = Connections::team()->prepare(
            'SELECT c.id, c.entreprise, c.contact_prenom, c.contact_nom, c.email, c.telephone, c.ville,
                    (SELECT COUNT(*) FROM evenements e WHERE e.client_id = c.id) AS evenements
             FROM clients c' . $where . '
             ORDER BY c.entreprise, c.id
             LIMIT 200'
        );
        $query->execute($params);

        return ['clients' => array_map(static fn($r) => [
            'id'         => (string) $r['id'],
            'entreprise' => $r['entreprise'],
            'contact'    => $r['contact_prenom'] . ' ' . $r['contact_nom'],
            'email'      => $r['email'],
            'telephone'  => $r['telephone'],
            'ville'      => $r['ville'],
            'evenements' => (int) $r['evenements'],
        ], $query->fetchAll())];
    }

    /** Fiche client détaillée et historique de tous ses événements (les plus récents d'abord). */
    public function client(array $input): array
    {
        (new Authentication())->requireUser(self::ROLES);
        $id = self::id($input['id'] ?? null);
        $db = Connections::team();

        $query = $db->prepare('SELECT id, entreprise, contact_prenom, contact_nom, email, telephone, adresse_ligne1, adresse_ligne2, code_postal, ville, pays_code, utilisateur_id, cree_le FROM clients WHERE id = ?');
        $query->execute([$id]);
        $client = $query->fetch();
        if (!$client) {
            throw new HttpError(404, 'client_not_found');
        }

        $query = $db->prepare('SELECT id, nom, debut, fin, lieu, statut FROM evenements WHERE client_id = ? ORDER BY debut IS NULL, debut DESC, id DESC');
        $query->execute([$id]);

        // Adresse en lignes non vides : rue, complément, « code postal ville », pays.
        $addressLines = array_values(array_filter(
            [$client['adresse_ligne1'], $client['adresse_ligne2'], trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')), $client['pays_code']],
            static fn($v) => is_string($v) && trim($v) !== ''
        ));

        return [
            'client' => [
                'id'         => (string) $client['id'],
                'entreprise' => $client['entreprise'],
                'contact'    => $client['contact_prenom'] . ' ' . $client['contact_nom'],
                'email'      => $client['email'],
                'telephone'  => $client['telephone'],
                'adresse'    => $addressLines,
                'compte'     => $client['utilisateur_id'] !== null,
                'cree_le'    => $client['cree_le'],
                // Valeurs séparées, pour préremplir la modification de la fiche par l'administratrice.
                'champs'     => array_intersect_key($client, array_flip(['entreprise', 'contact_prenom', 'contact_nom', 'email', 'telephone', 'adresse_ligne1', 'adresse_ligne2', 'code_postal', 'ville'])),
            ],
            'evenements' => array_map([self::class, 'event'], $query->fetchAll()),
        ];
    }

    /** Liste de tous les événements, brouillons compris, avec filtre par statut et recherche (nom, lieu, client). */
    public function events(array $input): array
    {
        (new Authentication())->requireUser(self::ROLES);
        $q = self::search($input);

        $status = $input['statut'] ?? '';
        if (!in_array($status, ['', 'brouillon', 'accepte', 'en_cours', 'termine', 'annule'], true)) {
            throw new HttpError(422, 'invalid_filter');
        }

        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'e.statut = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(e.nom LIKE ? OR e.lieu LIKE ? OR c.entreprise LIKE ?)';
            array_push($params, ...array_fill(0, 3, self::like($q)));
        }

        $query = Connections::team()->prepare(
            'SELECT e.id, e.nom, e.debut, e.fin, e.lieu, e.statut, e.client_id, c.entreprise
             FROM evenements e JOIN clients c ON c.id = e.client_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY e.debut IS NULL, e.debut DESC, e.id DESC LIMIT 200'
        );
        $query->execute($params);

        return ['evenements' => array_map([self::class, 'event'], $query->fetchAll())];
    }

    /**
     * Fiche projet : informations de l'événement et du client, notes et tâches.
     * Pour chaque note et chaque tâche, l'API indique ce que l'utilisateur a le droit de faire
     * (« modifiable », « suivant ») : l'interface n'affiche que les boutons autorisés.
     */
    public function eventDetail(array $input): array
    {
        $user = (new Authentication())->requireUser(self::ROLES);
        $id = self::id($input['id'] ?? null);
        $db = Connections::team();

        $query = $db->prepare('SELECT e.id, e.nom, e.debut, e.fin, e.lieu, e.type_evenement, e.theme, e.participants_estimes, e.statut, e.client_id, c.entreprise, c.contact_prenom, c.contact_nom, c.telephone, c.email
            FROM evenements e JOIN clients c ON c.id = e.client_id WHERE e.id = ?');
        $query->execute([$id]);
        $event = $query->fetch();
        if (!$event) {
            throw new HttpError(404, 'event_not_found');
        }
        $admin = $user['role'] === 'administrateur';

        $query = $db->prepare('SELECT n.id, n.contenu, n.cree_le, n.modifie_le, n.auteur_id, u.prenom, u.nom FROM notes_evenement n JOIN utilisateurs u ON u.id = n.auteur_id WHERE n.evenement_id = ? ORDER BY n.cree_le DESC, n.id DESC');
        $query->execute([$id]);
        $notes = array_map(static fn($n) => [
            'id'         => (string) $n['id'],
            'contenu'    => $n['contenu'],
            'cree_le'    => $n['cree_le'],
            'modifiee'   => $n['modifie_le'] !== $n['cree_le'],
            'auteur'     => $n['prenom'] . ' ' . mb_substr($n['nom'], 0, 1) . '.',
            'modifiable' => $admin || (string) $n['auteur_id'] === (string) $user['id'],
        ], $query->fetchAll());

        // Tâches en cours d'abord, puis à faire, puis terminées.
        $query = $db->prepare("SELECT t.id, t.titre, t.description, t.statut, t.assigne_a, u.prenom, u.nom FROM taches t LEFT JOIN utilisateurs u ON u.id = t.assigne_a WHERE t.evenement_id = ? ORDER BY FIELD(t.statut, 'en_cours', 'a_faire', 'termine'), t.id");
        $query->execute([$id]);
        $tasks = array_map(static fn($t) => [
            'id'          => (string) $t['id'],
            'titre'       => $t['titre'],
            'description' => $t['description'],
            'statut'      => $t['statut'],
            'assigne'     => $t['assigne_a'] === null ? null : $t['prenom'] . ' ' . $t['nom'],
            'a_moi'       => (string) $t['assigne_a'] === (string) $user['id'],
            // Étape suivante proposée seulement à la personne assignée.
            'suivant'     => (string) $t['assigne_a'] === (string) $user['id'] ? (self::NEXT[$t['statut']] ?? null) : null,
        ], $query->fetchAll());

        return [
            'evenement' => self::event($event) + [
                'type_evenement' => $event['type_evenement'],
                'theme'          => $event['theme'],
                'participants'   => $event['participants_estimes'] === null ? null : (int) $event['participants_estimes'],
                'contact'        => $event['contact_prenom'] . ' ' . $event['contact_nom'],
                'telephone'      => $event['telephone'],
                'email'          => $event['email'],
            ],
            'notes'  => $notes,
            'taches' => $tasks,
        ];
    }

    /** Contenu d'une note : non vide, 5 000 caractères au plus, retours à la ligne autorisés. */
    private static function content(mixed $v): string
    {
        if (!is_string($v) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v) || !mb_check_encoding($v, 'UTF-8')
            || trim($v) === '' || preg_match_all('/./us', trim($v)) > 5000) {
            throw new HttpError(422, 'validation_failed', ['contenu' => 'Rédigez la note (5 000 caractères au maximum).']);
        }
        return trim($v);
    }

    /** Note existante modifiable par l'utilisateur : la sienne, ou n'importe laquelle pour l'administratrice. */
    private function ownedNote(\PDO $db, array $user, string $id): array
    {
        $query = $db->prepare('SELECT id, evenement_id, auteur_id FROM notes_evenement WHERE id = ?');
        $query->execute([$id]);
        $note = $query->fetch();
        if (!$note) {
            throw new HttpError(404, 'note_not_found');
        }
        if ($user['role'] !== 'administrateur' && (string) $note['auteur_id'] !== (string) $user['id']) {
            throw new HttpError(403, 'not_note_author');
        }
        return $note;
    }

    /** Ajoute une note (sans « id ») ou modifie une note existante (avec « id ») ; renvoie la fiche à jour. */
    public function saveNote(array $input): array
    {
        $user = (new Authentication())->requireUser(self::ROLES);
        $text = self::content($input['contenu'] ?? null);
        $db = Connections::team();

        if (($input['id'] ?? null) !== null && ($input['id'] ?? '') !== '') {
            $note = $this->ownedNote($db, $user, self::id($input['id']));
            $db->prepare('UPDATE notes_evenement SET contenu = ? WHERE id = ?')->execute([$text, $note['id']]);
            return $this->eventDetail(['id' => (string) $note['evenement_id']]);
        }

        $event = self::id($input['evenement_id'] ?? null);
        $query = $db->prepare('SELECT id FROM evenements WHERE id = ?');
        $query->execute([$event]);
        if ($query->fetchColumn() === false) {
            throw new HttpError(404, 'event_not_found');
        }
        $db->prepare('INSERT INTO notes_evenement(evenement_id, auteur_id, contenu) VALUES(?, ?, ?)')->execute([$event, $user['id'], $text]);

        return $this->eventDetail(['id' => $event]);
    }

    /** Supprime une note (son auteur, ou l'administratrice) ; renvoie la fiche à jour. */
    public function deleteNote(array $input): array
    {
        $user = (new Authentication())->requireUser(self::ROLES);
        $db = Connections::team();

        $note = $this->ownedNote($db, $user, self::id($input['id'] ?? null));
        $db->prepare('DELETE FROM notes_evenement WHERE id = ?')->execute([$note['id']]);

        return $this->eventDetail(['id' => (string) $note['evenement_id']]);
    }

    /** Notes globales de l'équipe (les 20 plus récentes) : lues par toute l'équipe, rédigées par l'administratrice. */
    public function globalNotes(): array
    {
        $user = (new Authentication())->requireUser(self::ROLES);
        return ['notes' => $this->globalNoteRows(Connections::team(), $user)];
    }

    /** Ajoute une note globale (sans « id ») ou modifie une note existante (avec « id ») ; administratrice seulement. */
    public function saveGlobalNote(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $text = self::content($input['contenu'] ?? null);
        $db = Connections::team();

        if (($input['id'] ?? null) !== null && ($input['id'] ?? '') !== '') {
            $id = $this->existingGlobalNote($db, self::id($input['id']));
            $db->prepare('UPDATE notes_globales SET contenu = ? WHERE id = ?')->execute([$text, $id]);
        } else {
            $db->prepare('INSERT INTO notes_globales(auteur_id, contenu) VALUES(?, ?)')->execute([$user['id'], $text]);
        }

        return ['notes' => $this->globalNoteRows($db, $user)];
    }

    /** Supprime une note globale ; administratrice seulement. */
    public function deleteGlobalNote(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $db = Connections::team();
        $id = $this->existingGlobalNote($db, self::id($input['id'] ?? null));
        $db->prepare('DELETE FROM notes_globales WHERE id = ?')->execute([$id]);

        return ['notes' => $this->globalNoteRows($db, $user)];
    }

    /** Identifiant d'une note globale existante, sinon 404. */
    private function existingGlobalNote(\PDO $db, string $id): string
    {
        $query = $db->prepare('SELECT id FROM notes_globales WHERE id = ?');
        $query->execute([$id]);
        if ($query->fetchColumn() === false) {
            throw new HttpError(404, 'note_not_found');
        }
        return $id;
    }

    /** Notes globales avec leur auteur ; « modifiable » indique à l'interface si elle affiche les boutons. */
    private function globalNoteRows(\PDO $db, array $user): array
    {
        $rows = $db->query('SELECT n.id, n.contenu, n.cree_le, n.modifie_le, u.prenom, u.nom FROM notes_globales n JOIN utilisateurs u ON u.id = n.auteur_id ORDER BY n.cree_le DESC, n.id DESC LIMIT 20')->fetchAll();
        $admin = $user['role'] === 'administrateur';

        return array_map(fn(array $n) => [
            'id'         => $n['id'],
            'contenu'    => $n['contenu'],
            'cree_le'    => $n['cree_le'],
            'modifiee'   => $n['modifie_le'] !== $n['cree_le'],
            'auteur'     => $n['prenom'] . ' ' . mb_substr($n['nom'], 0, 1) . '.',
            'modifiable' => $admin,
        ], $rows);
    }

    /** Seule la personne assignée fait avancer sa tâche : à faire → en cours → terminé, une étape à la fois. */
    public function taskStatus(array $input): array
    {
        $user = (new Authentication())->requireUser(self::ROLES);
        $id = self::id($input['id'] ?? null);
        $to = $input['statut'] ?? null;
        if (!in_array($to, ['en_cours', 'termine'], true)) {
            throw new HttpError(422, 'invalid_status');
        }

        $db = Connections::team();
        $query = $db->prepare('SELECT evenement_id, assigne_a, statut FROM taches WHERE id = ?');
        $query->execute([$id]);
        $task = $query->fetch();
        if (!$task) {
            throw new HttpError(404, 'task_not_found');
        }
        if ((string) $task['assigne_a'] !== (string) $user['id']) {
            throw new HttpError(403, 'task_not_assigned');
        }

        // Statut de départ attendu pour ce changement ; la mise à jour échoue si la tâche n'y est plus (409).
        $from = array_search($to, self::NEXT, true);
        $query = $db->prepare('UPDATE taches SET statut = ? WHERE id = ? AND assigne_a = ? AND statut = ?');
        $query->execute([$to, $id, $user['id'], $from]);
        if ($query->rowCount() !== 1) {
            throw new HttpError(409, 'task_status_changed');
        }

        return $this->eventDetail(['id' => (string) $task['evenement_id']]);
    }
}
