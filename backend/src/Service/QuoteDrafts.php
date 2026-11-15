<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Préparation du devis d'un événement par l'administratrice : prestations, taux de TVA, totaux.
 *
 * Un devis a des versions (1, 2, 3…). Seule la version courante en « brouillon » est modifiable ;
 * une version transmise au client est figée et conservée telle quelle.
 * Chaque enregistrement augmente la « révision » : un formulaire resté ouvert longtemps ne peut pas
 * écraser une modification plus récente.
 */
final class QuoteDrafts
{
    private function id(mixed $id): string
    {
        if (!(is_int($id) || is_string($id)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $id)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $id;
    }

    /** Version courante du devis d'un événement, avec ses prestations dans l'ordre ; null s'il n'y a pas encore de devis. */
    private function current(\PDO $db, string $event): ?array
    {
        $query = $db->prepare('SELECT v.*, d.reference FROM devis d JOIN versions_devis v ON v.id = d.version_courante_id AND v.devis_id = d.id WHERE d.evenement_id = ?');
        $query->execute([$event]);
        $version = $query->fetch();
        if (!$version) {
            return null;
        }

        $query = $db->prepare('SELECT libelle, montant_ht FROM prestations_devis WHERE version_id = ? ORDER BY ordre');
        $query->execute([$version['id']]);
        $version['prestations'] = $query->fetchAll();

        return $version;
    }

    /** Pose le verrou partagé du devis de l'événement (voir QuotePdfs::locked) ; échoue après 5 secondes d'attente. */
    private function lock(\PDO $db, string $lock): void
    {
        $query = $db->prepare('SELECT GET_LOCK(?, 5)');
        $query->execute([$lock]);
        if ((int) $query->fetchColumn() !== 1) {
            throw new HttpError(503, 'quote_busy');
        }
    }

    /** L'événement doit exister et être encore en brouillon ; ligne verrouillée en lecture pendant la transaction. */
    private function requireDraftEvent(\PDO $db, string $id): void
    {
        $query = $db->prepare('SELECT statut FROM evenements WHERE id = ? FOR SHARE');
        $query->execute([$id]);
        $status = $query->fetchColumn();
        if ($status === false) {
            throw new HttpError(404, 'event_not_found');
        }
        if ($status !== 'brouillon') {
            throw new HttpError(409, 'event_not_draft');
        }
    }

    /** Écran de préparation : l'événement et le devis courant (ou null). */
    public function read(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);
        $db = Connections::quoteDrafts();

        $query = $db->prepare('SELECT id, nom, statut FROM evenements WHERE id = ?');
        $query->execute([$id]);
        $event = $query->fetch();
        if (!$event) {
            throw new HttpError(404, 'event_not_found');
        }

        $version = $this->current($db, $id);

        return [
            'event' => $event,
            'quote' => $version ? [
                'reference'   => $version['reference'],
                'revision'    => (int) $version['revision_contenu'],
                'statut'      => $version['statut'],
                'editable'    => $version['statut'] === 'brouillon' && $version['figee_le'] === null,
                'prestations' => $version['prestations'],
                'taux_tva'    => $version['taux_tva'],
                'total_ht'    => $version['total_ht'],
                'total_tva'   => $version['total_tva'],
                'total_ttc'   => $version['total_ttc'],
            ] : null,
        ];
    }

    /**
     * Contre-proposition après une demande de modification du client : nouvelle version brouillon,
     * qui reprend les prestations et le taux de la version transmise (celle-ci reste conservée).
     */
    public function counterProposal(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);
        $from = $input['version'] ?? null;
        if (!is_int($from) || $from < 1 || $from > 100000) {
            throw new HttpError(422, 'invalid_version');
        }

        $db = Connections::quoteDrafts();
        $lock = 'innovevents.quote.' . $id;
        $this->lock($db, $lock);
        try {
            $db->beginTransaction();
            $this->requireDraftEvent($db, $id);

            $version = $this->current($db, $id);
            if (!$version) {
                throw new HttpError(409, 'quote_required');
            }

            // Répétition après une réponse perdue : la nouvelle version existe déjà.
            if ((int) $version['numero'] === $from + 1 && $version['statut'] === 'brouillon') {
                $db->commit();
                return ['version' => (int) $version['numero'], 'revision' => (int) $version['revision_contenu'], 'repetition' => true];
            }
            // Contre-proposition possible seulement depuis la version courante, si le client a demandé une modification.
            if ((int) $version['numero'] !== $from || $version['statut'] !== 'modification') {
                throw new HttpError(409, 'counter_proposal_not_allowed');
            }

            $db->prepare('INSERT INTO versions_devis(devis_id, numero) VALUES(?, ?)')->execute([$version['devis_id'], $from + 1]);
            $newId = $db->lastInsertId();

            $db->prepare('UPDATE versions_devis SET revision_contenu = 1, devise = ?, taux_tva = ?, regle_calcul = ?, total_ht = ?, total_tva = ?, total_ttc = ? WHERE id = ?')
                ->execute([$version['devise'], $version['taux_tva'], $version['regle_calcul'], $version['total_ht'], $version['total_tva'], $version['total_ttc'], $newId]);

            $insert = $db->prepare('INSERT INTO prestations_devis(version_id, ordre, libelle, montant_ht) VALUES(?, ?, ?, ?)');
            foreach ($version['prestations'] as $i => $line) {
                $insert->execute([$newId, $i + 1, $line['libelle'], $line['montant_ht']]);
            }

            $db->prepare('UPDATE devis SET version_courante_id = ? WHERE id = ?')->execute([$newId, $version['devis_id']]);

            $db->commit();
            return ['version' => $from + 1, 'revision' => 1, 'repetition' => false];
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
     * Enregistre les prestations et le taux du devis brouillon et recalcule les totaux (voir QuoteAmounts).
     * Crée le devis et sa version 1 au premier enregistrement. Rend obsolète le PDF déjà généré.
     *
     * @param array $input event_id, revision (0 si aucun devis n'existe encore), prestations, taux_tva.
     */
    public function save(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);
        $revision = $input['revision'] ?? null;
        if (!is_int($revision) || $revision < 0 || $revision > 2147483646) {
            throw new HttpError(422, 'invalid_revision');
        }
        $data = QuoteAmounts::calculate($input);

        $db = Connections::quoteDrafts();
        $lock = 'innovevents.quote.' . $id;
        $this->lock($db, $lock);
        try {
            $db->beginTransaction();
            $this->requireDraftEvent($db, $id);

            $version = $this->current($db, $id);
            if ($version) {
                if ($version['statut'] !== 'brouillon' || $version['figee_le'] !== null) {
                    throw new HttpError(409, 'quote_not_editable');
                }
                if ((int) $version['revision_contenu'] !== $revision) {
                    // Même enregistrement déjà fait (réponse perdue) : on renvoie le résultat sans rien refaire.
                    if ((int) $version['revision_contenu'] === $revision + 1 && $version['prestations'] === $data['prestations'] && $version['taux_tva'] === $data['taux_tva']) {
                        $db->commit();
                        return ['revision' => (int) $version['revision_contenu'], 'reference' => $version['reference']] + $data;
                    }
                    throw new HttpError(409, 'quote_revision_conflict');
                }

                $versionId = $version['id'];
                $reference = $version['reference'];
                $next = $revision + 1;

                // Le contenu change : le PDF déjà généré ne correspond plus, et les prestations sont réécrites.
                $db->prepare("UPDATE documents_devis SET etat = 'obsolete' WHERE version_id = ? AND etat = 'disponible'")->execute([$versionId]);
                $db->prepare('DELETE FROM prestations_devis WHERE version_id = ?')->execute([$versionId]);
            } else {
                // Premier enregistrement : création du devis (référence IE-<id de l'événement>) et de sa version 1.
                if ($revision !== 0) {
                    throw new HttpError(409, 'quote_revision_conflict');
                }

                $query = $db->prepare('SELECT id, reference FROM devis WHERE evenement_id = ?');
                $query->execute([$id]);
                $quote = $query->fetch();
                if (!$quote) {
                    $reference = 'IE-' . $id;
                    $db->prepare('INSERT INTO devis(evenement_id, reference) VALUES(?, ?)')->execute([$id, $reference]);
                    $quoteId = $db->lastInsertId();
                } else {
                    $quoteId = $quote['id'];
                    $reference = $quote['reference'];
                }

                $db->prepare('INSERT INTO versions_devis(devis_id, numero) VALUES(?, 1)')->execute([$quoteId]);
                $versionId = $db->lastInsertId();
                $next = 1;
                $db->prepare('UPDATE devis SET version_courante_id = ? WHERE id = ?')->execute([$versionId, $quoteId]);
            }

            $db->prepare('UPDATE versions_devis SET revision_contenu = ?, devise = ?, taux_tva = ?, regle_calcul = ?, total_ht = ?, total_tva = ?, total_ttc = ? WHERE id = ?')
                ->execute([$next, $data['devise'], $data['taux_tva'], $data['regle_calcul'], $data['total_ht'], $data['total_tva'], $data['total_ttc'], $versionId]);

            $insert = $db->prepare('INSERT INTO prestations_devis(version_id, ordre, libelle, montant_ht) VALUES(?, ?, ?, ?)');
            foreach ($data['prestations'] as $i => $line) {
                $insert->execute([$versionId, $i + 1, $line['libelle'], $line['montant_ht']]);
            }

            $db->commit();
            return ['revision' => $next, 'reference' => $reference] + $data;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } finally {
            $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
    }
}
