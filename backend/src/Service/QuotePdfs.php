<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Génération et téléchargement du devis PDF par l'administratrice.
 *
 * Avant de générer, toutes les données sont revérifiées (événement complet, client complet, totaux recalculés).
 * Le PDF est stocké sous le nom de son empreinte SHA-256 et rattaché à une « empreinte source » :
 * si une donnée du devis change ensuite, l'ancien PDF est reconnu comme obsolète et n'est plus téléchargeable.
 * Chaque génération est journalisée (GENERATION_PDF_DEVIS), comme l'exige l'énoncé.
 */
final class QuotePdfs
{
    private const DIRECTORY = '/var/lib/innovevents/quote-pdfs';

    private function id(mixed $v): string
    {
        if (!(is_int($v) || is_string($v)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $v)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $v;
    }

    /**
     * Informations encore manquantes pour produire le PDF, en libellés lisibles :
     * côté événement (nom, dates, lieu, image) et côté fiche client (entreprise, contact, coordonnées).
     *
     * @return array{evenement: string[], client: string[]}
     */
    public static function missing(array $event, array $client): array
    {
        $blank = static fn($v) => !is_string($v) || trim($v) === '';
        $event_missing = [];
        if ($blank($event['nom'] ?? null)) {
            $event_missing[] = 'le nom de l’événement';
        }
        if ($blank($event['debut'] ?? null) || $blank($event['fin'] ?? null)) {
            $event_missing[] = 'les dates de début et de fin';
        } elseif ($event['fin'] <= $event['debut']) {
            $event_missing[] = 'une date de fin postérieure au début';
        }
        if ($blank($event['lieu'] ?? null)) {
            $event_missing[] = 'le lieu';
        }
        if ($blank($event['image_reference'] ?? null) || $blank($event['image_alternative'] ?? null)) {
            $event_missing[] = 'l’image de l’événement et sa description';
        }
        $client_missing = [];
        foreach (['entreprise' => 'le nom de l’entreprise', 'contact_prenom' => 'le prénom du contact', 'contact_nom' => 'le nom du contact', 'email' => 'l’adresse électronique', 'telephone' => 'le téléphone'] as $key => $label) {
            if ($blank($client[$key] ?? null)) {
                $client_missing[] = $label;
            }
        }
        return ['evenement' => $event_missing, 'client' => $client_missing];
    }

    /**
     * Rassemble et vérifie tout ce qui entre dans le PDF : événement, client, devis et prestations.
     * Les lignes sont lues « FOR SHARE » : personne ne peut les modifier pendant la génération.
     * Attention : l'ordre des colonnes sélectionnées entre dans l'empreinte source ; ne pas le modifier.
     */
    public function source(\PDO $db, string $id): array
    {
        $query = $db->prepare('SELECT id,client_id,nom,debut,fin,fuseau,lieu,statut,image_reference,image_alternative,revision FROM evenements WHERE id=? FOR SHARE');
        $query->execute([$id]);
        $event = $query->fetch();
        if (!$event) {
            throw new HttpError(404, 'event_not_found');
        }

        $query = $db->prepare('SELECT id,entreprise,contact_nom,contact_prenom,email,telephone FROM clients WHERE id=? FOR SHARE');
        $query->execute([$event['client_id']]);
        $client = $query->fetch();

        $query = $db->prepare('SELECT v.id,v.numero,v.revision_contenu,v.statut,v.figee_le,v.taux_tva,v.total_ht,v.total_tva,v.total_ttc,v.devise,v.regle_calcul,d.reference FROM devis d JOIN versions_devis v ON v.id=d.version_courante_id AND v.devis_id=d.id WHERE d.evenement_id=? FOR SHARE');
        $query->execute([$id]);
        $quote = $query->fetch();
        if (!$quote) {
            throw new HttpError(422, 'quote_required');
        }

        // Un PDF ne se génère que pour un devis encore en brouillon (un devis transmis est figé).
        if ($quote['statut'] !== 'brouillon' || $quote['figee_le'] !== null || $event['statut'] !== 'brouillon') {
            throw new HttpError(409, 'quote_not_editable');
        }

        // Tout ce qui manque est renvoyé d'un coup, pour que l'écran l'indique précisément.
        $missing = self::missing($event, $client ?: []);
        if ($missing['evenement']) {
            throw new HttpError(422, 'event_incomplete', ['manquants' => $missing]);
        }
        if ($event['fuseau'] !== 'Europe/Paris') {
            throw new HttpError(422, 'event_incomplete');
        }
        if ($missing['client']) {
            throw new HttpError(422, 'client_incomplete', ['manquants' => $missing]);
        }

        $query = $db->prepare('SELECT libelle,montant_ht FROM prestations_devis WHERE version_id=? ORDER BY ordre');
        $query->execute([$quote['id']]);
        $lines = $query->fetchAll();

        // Les totaux enregistrés doivent correspondre exactement à un nouveau calcul.
        $computed = QuoteAmounts::calculate(['prestations' => $lines, 'taux_tva' => $quote['taux_tva']]);
        foreach (['total_ht', 'total_tva', 'total_ttc', 'devise', 'regle_calcul'] as $key) {
            if ($quote[$key] !== $computed[$key]) {
                throw new HttpError(409, 'quote_totals_changed');
            }
        }

        return ['event' => $event, 'client' => $client, 'quote' => $quote, 'prestations' => $lines];
    }

    /** Empreinte des données du devis : change dès qu'une seule donnée du PDF change. */
    public function hash(array $source): string
    {
        return hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
    }

    /** Contenu d'un PDF stocké, seulement s'il est intact (empreinte vérifiée) ; null sinon. */
    public function bytes(array $document): ?string
    {
        $reference = $document['fichier_reference'];
        if (!is_string($reference) || !preg_match('/^[a-f0-9]{64}\.pdf$/D', $reference)) {
            return null;
        }
        $path = self::DIRECTORY . '/' . $reference;
        if (!is_file($path)) {
            return null;
        }
        $bytes = file_get_contents($path);

        return is_string($bytes) && hash_equals($document['empreinte_sha256'], hash('sha256', $bytes)) ? $bytes : null;
    }

    /**
     * Exécute une opération sur le devis d'un événement sous verrou et dans une transaction.
     * Le verrou « innovevents.quote.<id> » est partagé avec la préparation, la transmission et la réponse du client :
     * deux opérations sur le même devis ne s'exécutent jamais en même temps.
     */
    private function locked(string $id, callable $operation): mixed
    {
        $db = Connections::quotePdf();
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

    /** Indique si un PDF à jour existe déjà pour le devis de l'événement. */
    public function status(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);

        return $this->locked($id, function ($db) use ($id) {
            try {
                $source = $this->source($db, $id);
            } catch (HttpError $e) {
                // Devis incomplet ou déjà transmis : simplement « pas de PDF prêt ».
                if (in_array($e->status, [422, 409], true)) {
                    return ['ready' => false];
                }
                throw $e;
            }

            $query = $db->prepare("SELECT * FROM documents_devis WHERE version_id=? AND revision_contenu=? AND source_empreinte=? AND etat='disponible' ORDER BY id DESC LIMIT 1");
            $query->execute([$source['quote']['id'], $source['quote']['revision_contenu'], $this->hash($source)]);
            $document = $query->fetch();

            return $document && $this->bytes($document) !== null
                ? ['ready' => true, 'document_id' => (string) $document['id']]
                : ['ready' => false];
        });
    }

    /** Génère le PDF du devis (ou renvoie celui qui existe déjà s'il est à jour). */
    public function generate(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['event_id'] ?? null);
        $revision = $input['revision'] ?? null;
        if (!is_int($revision) || $revision < 1) {
            throw new HttpError(422, 'invalid_revision');
        }

        return $this->locked($id, function ($db) use ($id, $revision, $user) {
            $source = $this->source($db, $id);
            $quote = $source['quote'];
            // Le devis a été modifié depuis l'affichage de la page : on ne génère pas une version que l'administratrice n'a pas vue.
            if ((int) $quote['revision_contenu'] !== $revision) {
                throw new HttpError(409, 'quote_revision_conflict');
            }
            $hash = $this->hash($source);

            // PDF identique déjà disponible : inutile de le refaire.
            $query = $db->prepare("SELECT * FROM documents_devis WHERE version_id=? AND revision_contenu=? AND source_empreinte=? AND etat='disponible' ORDER BY id DESC LIMIT 1");
            $query->execute([$quote['id'], $revision, $hash]);
            $existing = $query->fetch();
            if ($existing && $this->bytes($existing) !== null) {
                return ['document_id' => (string) $existing['id']];
            }

            // L'image doit exister et correspondre à son empreinte.
            $image = $source['event']['image_reference'];
            if (!preg_match('/^[a-f0-9]{64}\.(png|jpg)$/D', $image)) {
                throw new HttpError(422, 'event_image_unavailable');
            }
            $path = EventImages::path($image);
            $imageBytes = $path !== null ? file_get_contents($path) : false;
            if (!is_string($imageBytes) || !hash_equals(substr($image, 0, 64), hash('sha256', $imageBytes))) {
                throw new HttpError(422, 'event_image_unavailable');
            }

            $bytes = QuotePdfRenderer::render($source, $imageBytes);
            if (!str_starts_with($bytes, '%PDF-')) {
                throw new \RuntimeException('PDF rendering');
            }

            // Écriture dans un fichier temporaire puis renommage : jamais de PDF à moitié écrit.
            if (!is_dir(self::DIRECTORY) && !mkdir(self::DIRECTORY, 0700, true) && !is_dir(self::DIRECTORY)) {
                throw new \RuntimeException('PDF storage');
            }
            $digest = hash('sha256', $bytes);
            $file = $digest . '.pdf';
            $temporary = tempnam(self::DIRECTORY, 'pending-');
            if ($temporary === false) {
                throw new \RuntimeException('PDF storage');
            }
            try {
                if (file_put_contents($temporary, $bytes) !== strlen($bytes) || !rename($temporary, self::DIRECTORY . '/' . $file)) {
                    throw new \RuntimeException('PDF storage');
                }
                chmod(self::DIRECTORY . '/' . $file, 0600);
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }

            // Copie figée du client et de l'événement dans la version du devis.
            $db->prepare('UPDATE versions_devis SET client_snapshot=?, evenement_snapshot=? WHERE id=?')
                ->execute([json_encode($source['client'], JSON_THROW_ON_ERROR), json_encode($source['event'], JSON_THROW_ON_ERROR), $quote['id']]);
            // Un seul PDF « disponible » par version : les précédents deviennent obsolètes.
            $db->prepare("UPDATE documents_devis SET etat='obsolete' WHERE version_id=? AND etat='disponible'")
                ->execute([$quote['id']]);
            $db->prepare("INSERT INTO documents_devis(version_id,revision_contenu,etat,fichier_reference,empreinte_sha256,source_empreinte,genere_le) VALUES(?,?,'disponible',?,?,?,UTC_TIMESTAMP(6))")
                ->execute([$quote['id'], $revision, $file, $digest, $hash]);
            $documentId = $db->lastInsertId();

            // Journal exigé : génération d'un devis en PDF (identifiant de l'événement).
            $db->prepare("INSERT INTO journal_a_emettre(id_trace,horodatage,type_action,id_utilisateur,details) VALUES(UUID(),UTC_TIMESTAMP(6),'GENERATION_PDF_DEVIS',?,?)")
                ->execute([$user['id'], json_encode(['id_evenement' => $id], JSON_THROW_ON_ERROR)]);

            return ['document_id' => (string) $documentId];
        });
    }

    /** Téléchargement du PDF par l'administratrice, refusé s'il ne correspond plus aux données actuelles. */
    public function download(array $input): void
    {
        (new Authentication())->requireUser(['administrateur']);
        $documentId = $this->id($input['id'] ?? null);

        $query = Connections::quotePdf()->prepare('SELECT d.evenement_id FROM documents_devis p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id WHERE p.id=?');
        $query->execute([$documentId]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new HttpError(404, 'document_not_found');
        }

        $result = $this->locked((string) $id, function ($db) use ($id, $documentId) {
            $source = $this->source($db, (string) $id);

            $query = $db->prepare('SELECT * FROM documents_devis WHERE id=?');
            $query->execute([$documentId]);
            $document = $query->fetch();

            if (!$document || $document['etat'] !== 'disponible' || (string) $document['version_id'] !== (string) $source['quote']['id']
                || !is_string($document['source_empreinte']) || !hash_equals($document['source_empreinte'], $this->hash($source))) {
                throw new HttpError(409, 'pdf_obsolete');
            }

            $bytes = $this->bytes($document);
            if ($bytes === null) {
                throw new HttpError(503, 'pdf_unavailable');
            }
            return ['bytes' => $bytes, 'name' => 'devis-' . $id . '-r' . $document['revision_contenu'] . '.pdf'];
        });

        header('Content-Type: application/pdf');
        header('Cache-Control: no-store');
        header('Content-Disposition: attachment; filename="' . $result['name'] . '"');
        header('Content-Length: ' . strlen($result['bytes']));
        echo $result['bytes'];
    }
}
