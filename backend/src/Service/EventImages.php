<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Image d'un événement brouillon, envoyée par l'administratrice (illustration du devis et de la page publique).
 *
 * Sécurité : taille et dimensions limitées, format vérifié par le contenu (pas par l'extension),
 * image ré-encodée (métadonnées et contenu caché supprimés) et nommée par son empreinte SHA-256 :
 * le nom choisi par l'utilisateur ne sert jamais de chemin de fichier.
 */
final class EventImages
{
    private const DIRECTORY = '/var/lib/innovevents/event-images';

    private function id(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/', $value)) {
            throw new HttpError(422, 'invalid_id');
        }
        return $value;
    }

    /**
     * Enregistre ou remplace l'image d'un événement brouillon avec sa description (texte alternatif, accessibilité).
     * Le numéro de révision évite d'écraser une modification faite entre-temps par quelqu'un d'autre.
     */
    public function upload(array $input, array $files): array
    {
        (new Authentication())->requireUser(['administrateur']);

        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 6 * 1024 * 1024) {
            throw new HttpError(413, 'image_too_large');
        }
        $id = $this->id($input['id'] ?? null);
        $revision = $this->id($input['revision'] ?? null);

        $alt = $input['alternative'] ?? null;
        if (!is_string($alt) || trim($alt) === '' || preg_match_all('/./us', $alt) > 255 || preg_match('/[\x00-\x1F\x7F]/', $alt)) {
            throw new HttpError(422, 'validation_failed', ['image-alternative' => 'Décrivez l’image en 255 caractères maximum.']);
        }
        $alt = trim($alt);

        // Contrôles du fichier envoyé.
        $file = $files['image'] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            throw new HttpError(422, 'validation_failed', ['event-image' => 'Choisissez une image JPEG ou PNG.']);
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new HttpError(413, 'image_too_large');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new HttpError(422, 'validation_failed', ['event-image' => 'Le transfert a échoué. Sélectionnez à nouveau l’image.']);
        }
        if (filesize($file['tmp_name']) > 5 * 1024 * 1024) {
            throw new HttpError(413, 'image_too_large');
        }

        // Le type réel est lu dans le contenu ; dimensions limitées pour protéger la mémoire du serveur.
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8000000) {
            throw new HttpError(422, 'validation_failed', ['event-image' => 'Utilisez un JPEG ou PNG de 4 096 pixels maximum par côté et 8 millions de pixels au total.']);
        }
        $source = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$source) {
            throw new HttpError(422, 'validation_failed', ['event-image' => 'Ce fichier image est endommagé.']);
        }

        // Le ré-encodage supprime les métadonnées (position GPS…) et tout contenu ajouté au fichier.
        $extension = $info[2] === IMAGETYPE_JPEG ? 'jpg' : 'png';
        if (!is_dir(self::DIRECTORY) && !mkdir(self::DIRECTORY, 0700, true) && !is_dir(self::DIRECTORY)) {
            throw new \RuntimeException('Image storage');
        }
        $temporary = tempnam(self::DIRECTORY, 'pending-');
        if ($temporary === false) {
            throw new \RuntimeException('Image storage');
        }

        try {
            imagesavealpha($source, true);
            $ok = $extension === 'jpg' ? imagejpeg($source, $temporary, 90) : imagepng($source, $temporary, 6);
            if (!$ok) {
                throw new \RuntimeException('Image encoding');
            }

            // Nom du fichier = empreinte de son contenu : deux images identiques partagent le même fichier.
            $reference = hash_file('sha256', $temporary) . '.' . $extension;
            if (!rename($temporary, self::DIRECTORY . '/' . $reference)) {
                throw new \RuntimeException('Image storage');
            }
            chmod(self::DIRECTORY . '/' . $reference, 0600);

            $db = Connections::eventImages();
            $db->beginTransaction();
            try {
                $query = $db->prepare('SELECT statut, revision, image_reference, image_alternative FROM evenements WHERE id = ? FOR UPDATE');
                $query->execute([$id]);
                $row = $query->fetch();
                if (!$row) {
                    throw new HttpError(404, 'event_not_found');
                }
                if ($row['statut'] !== 'brouillon') {
                    throw new HttpError(409, 'event_not_draft');
                }

                if ((string) $row['revision'] !== $revision) {
                    // Même envoi déjà enregistré (réponse perdue) : on confirme sans rien changer.
                    if ((int) $row['revision'] === (int) $revision + 1 && $row['image_reference'] === $reference && $row['image_alternative'] === $alt) {
                        $db->commit();
                        return ['revision' => (int) $row['revision'], 'alternative' => $alt];
                    }
                    throw new HttpError(409, 'event_revision_conflict');
                }

                $db->prepare('UPDATE evenements SET image_reference = ?, image_alternative = ?, revision = revision + 1 WHERE id = ?')
                    ->execute([$reference, $alt, $id]);
                $db->commit();

                return ['revision' => (int) $revision + 1, 'alternative' => $alt];
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        } finally {
            imagedestroy($source);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Chemin d'une image enregistrée, ou d'une illustration de démonstration livrée avec l'application (lecture seule).
     * Le nom doit être une empreinte : impossible de remonter vers un autre dossier (« ../ »).
     */
    public static function path(mixed $reference): ?string
    {
        if (!is_string($reference) || !preg_match('/^[a-f0-9]{64}\.(jpg|png)$/', $reference)) {
            return null;
        }
        foreach ([self::DIRECTORY, dirname(__DIR__, 2) . '/demo-images'] as $directory) {
            if (is_file($directory . '/' . $reference)) {
                return $directory . '/' . $reference;
            }
        }
        return null;
    }

    /** Aperçu privé de l'image d'un événement pour l'administratrice. */
    public function show(array $input): void
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['id'] ?? null);

        $query = Connections::eventImages()->prepare('SELECT image_reference FROM evenements WHERE id = ?');
        $query->execute([$id]);
        $reference = $query->fetchColumn();

        $path = self::path($reference);
        if ($path === null) {
            throw new HttpError(404, 'image_not_found');
        }

        $png = str_ends_with($reference, '.png');
        header('Cache-Control: no-store');
        header('Content-Type: ' . ($png ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="event-image' . ($png ? '.png' : '.jpg') . '"');
        // Le navigateur affiche l'image sans jamais exécuter de contenu actif.
        header("Content-Security-Policy: default-src 'none'; sandbox");
        readfile($path);
    }
}
