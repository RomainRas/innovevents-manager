<?php
declare(strict_types=1);

namespace InnovEvents\Security;

/**
 * Limite le nombre de tentatives de connexion pour freiner les attaques par force brute.
 *
 * Règle retenue : 10 tentatives par compte et 60 par adresse réseau sur 15 minutes.
 * Les compteurs sont gardés dans un petit fichier JSON partagé, verrouillé pendant la mise à jour.
 * Seules des empreintes (SHA-256) de l'adresse électronique et de l'IP y sont écrites, jamais les valeurs.
 */
final class LoginLimiter
{
    /** Indique si une nouvelle tentative est autorisée, et la compte si c'est le cas. */
    public function allow(string $email, string $ip): bool
    {
        $dir = '/var/lib/innovevents';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Rate storage');
        }

        $file = fopen($dir . '/login-rate.json', 'c+');
        // Verrou exclusif : deux connexions simultanées ne peuvent pas écraser le compteur de l'autre.
        if (!$file || !flock($file, LOCK_EX)) {
            throw new \RuntimeException('Rate lock');
        }

        try {
            $raw = stream_get_contents($file);
            $data = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $now = time();

            // Oubli des compteurs dont la fenêtre de 15 minutes est terminée.
            foreach ($data as $key => $entry) {
                if ($entry['until'] <= $now) {
                    unset($data[$key]);
                }
            }

            // Deux compteurs par tentative : le compte visé et l'adresse réseau, chacun avec son plafond.
            $limits = [
                hash('sha256', 'account:' . $email) => 10,
                hash('sha256', 'ip:' . $ip)         => 60,
            ];

            $allowed = true;
            foreach ($limits as $key => $max) {
                if (($data[$key]['count'] ?? 0) >= $max) {
                    $allowed = false;
                }
            }

            if ($allowed) {
                foreach ($limits as $key => $max) {
                    $data[$key] ??= ['count' => 0, 'until' => $now + 900];
                    $data[$key]['count']++;
                }
            }

            // Réécriture complète du fichier avec les compteurs à jour.
            rewind($file);
            ftruncate($file, 0);
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            if (fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                throw new \RuntimeException('Rate write');
            }

            return $allowed;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
