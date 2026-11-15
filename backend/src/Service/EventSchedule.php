<?php
declare(strict_types=1);

namespace InnovEvents\Service;

/**
 * Conversion des dates entre l'heure de Paris (saisie et affichage) et l'UTC (stockage en base).
 *
 * Stocker en UTC évite toute ambiguïté ; l'interface ne manipule que l'heure locale de Paris,
 * au format des champs « datetime-local » : AAAA-MM-JJTHH:MM.
 */
final class EventSchedule
{
    /**
     * Heure de Paris saisie → date UTC pour MySQL (« AAAA-MM-JJ HH:MM:SS »), ou null si le champ est vide.
     *
     * Refuse les heures qui n'existent pas (passage à l'heure d'été : 2 h 30 n'existe pas) et les heures
     * ambiguës (retour à l'heure d'hiver : 2 h 30 existe deux fois), plutôt que de choisir à la place de l'utilisateur.
     */
    public static function utc(mixed $value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/', $value)) {
            throw new \InvalidArgumentException('Date invalide.');
        }

        $utc = new \DateTimeZone('UTC');
        $zone = new \DateTimeZone('Europe/Paris');

        // Lecture stricte : « 2027-02-30 » serait sinon converti en silence en 2 mars.
        $naive = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $utc);
        if (!$naive || $naive->format('Y-m-d\TH:i') !== $value) {
            throw new \InvalidArgumentException('Date invalide.');
        }

        // On essaie chaque décalage horaire en vigueur autour de cette date (heure d'hiver, heure d'été)
        // et on garde les instants UTC qui, ramenés à Paris, redonnent exactement l'heure saisie.
        $seconds = $naive->getTimestamp();
        $matches = [];
        foreach ($zone->getTransitions($seconds - 86400, $seconds + 86400) as $transition) {
            $candidate = $naive->setTimestamp($seconds - $transition['offset']);
            if ($candidate->setTimezone($zone)->format('Y-m-d\TH:i') === $value) {
                $matches[$candidate->format('Y-m-d H:i:s')] = $candidate;
            }
        }

        // Aucun résultat : heure inexistante ; deux résultats : heure ambiguë.
        if (count($matches) !== 1) {
            throw new \InvalidArgumentException('Cette heure est inexistante ou ambiguë lors du changement d’heure. Choisissez une heure non ambiguë.');
        }

        $result = array_key_first($matches);
        if ($result < '1000-01-01 00:00:00') {
            throw new \InvalidArgumentException('Date hors limites.');
        }
        return $result;
    }

    /** Date UTC de la base → heure de Paris au format des formulaires (chaîne vide si aucune date). */
    public static function local(?string $value): string
    {
        return $value === null
            ? ''
            : (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('Europe/Paris'))
                ->format('Y-m-d\TH:i');
    }
}
