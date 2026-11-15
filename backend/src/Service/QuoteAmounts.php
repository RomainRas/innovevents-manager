<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;

/**
 * Calcul exact des montants d'un devis.
 *
 * Tous les calculs se font en nombres entiers (centimes), jamais en nombres à virgule :
 * 0,1 + 0,2 ne donne pas exactement 0,3 en virgule flottante, ce qui est inacceptable pour un devis.
 */
final class QuoteAmounts
{
    /**
     * Convertit un montant saisi (« 1234,5 » ou « 1234.50 ») en centimes.
     * Refuse les négatifs, plus de deux décimales, la notation scientifique et les montants trop grands.
     *
     * @param int $max Plafond en centimes (9 999 999,99 € par défaut ; 10000 pour un taux de 100,00 %).
     */
    public static function cents(mixed $value, int $max = 999999999): int
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,8})(?:[.,]([0-9]{1,2}))?$/D', $value, $match)) {
            throw new \InvalidArgumentException('Montant invalide');
        }

        // Partie entière × 100 + décimales complétées à deux chiffres (« 5 » après la virgule = 50 centimes).
        $cents = (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
        if ($cents > $max) {
            throw new \InvalidArgumentException('Montant hors limites');
        }

        return $cents;
    }

    /** Écrit des centimes sous forme décimale avec deux chiffres : 12345 → « 123.45 ». */
    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Valide les prestations et le taux de TVA, puis calcule les totaux HT, TVA et TTC.
     * Toutes les erreurs sont renvoyées ensemble, champ par champ, pour corriger le formulaire en une fois.
     */
    public static function calculate(array $input): array
    {
        $errors = [];
        $lines = [];
        $total = 0;

        // Taux en centièmes de pourcent : 20 % → 2000, 5,5 % → 550, plafond 100 %.
        try {
            $rate = self::cents($input['taux_tva'] ?? null, 10000);
        } catch (\InvalidArgumentException) {
            $errors['taux_tva'] = 'Saisissez un taux de 0 à 100, avec deux décimales maximum.';
        }

        $rows = $input['prestations'] ?? null;
        if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 100) {
            $errors['prestations'] = 'Ajoutez de 1 à 100 prestations.';
        } else {
            foreach ($rows as $i => $row) {
                $label = is_array($row) ? ($row['libelle'] ?? null) : null;
                // Libellé : texte UTF-8 valide, non vide, 255 caractères au plus, sans caractère de contrôle.
                if (!is_string($label) || trim($label) === '' || preg_match('//u', $label) !== 1
                    || preg_match_all('/./us', $label) > 255 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
                    $errors['libelle-' . $i] = 'Libellé obligatoire, 255 caractères maximum.';
                }

                try {
                    $amount = self::cents(is_array($row) ? ($row['montant_ht'] ?? null) : null);
                    $total += $amount;
                } catch (\InvalidArgumentException) {
                    $errors['montant-' . $i] = 'Montant positif ou nul, jusqu’à 9 999 999,99 €, deux décimales maximum.';
                    $amount = 0;
                }

                $lines[] = [
                    'libelle'    => is_string($label) ? trim($label) : '',
                    'montant_ht' => self::decimal($amount),
                ];
            }
        }

        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }

        // TVA calculée une seule fois sur le total HT : centimes × centièmes de pourcent, divisé par 10 000,
        // arrondi au centime le plus proche (le demi-centime est arrondi vers le haut, grâce au + 5000).
        $tax = intdiv($total * $rate + 5000, 10000);

        return [
            'prestations'  => $lines,
            'taux_tva'     => self::decimal($rate),
            'total_ht'     => self::decimal($total),
            'total_tva'    => self::decimal($tax),
            'total_ttc'    => self::decimal($total + $tax),
            'devise'       => 'EUR',
            'regle_calcul' => 'TVA_TOTAL_2D_HALF_UP_V1',   // règle enregistrée avec le devis, pour pouvoir le recalculer à l'identique
        ];
    }
}
