<?php

namespace App\Services;

/**
 * Service de gestion des règles de vote Planning Poker
 * 
 * Implémente différentes stratégies de calcul du résultat d'un vote :
 * - Strict : Unanimité requise sur les votes numériques uniquement
 * - Moyenne : Moyenne arithmétique des votes numériques
 * - Médiane : Valeur médiane des votes numériques
 * - Majorité absolue : Plus de 50% des votes pour une même valeur
 * - Majorité relative : Valeur la plus fréquente (pluralité)
 * 
 * Note : Les votes non numériques (?, café, ∞) sont filtrés pour toutes les règles.
 * 
 * @package App\Services
 * @author Melissa Aliouche
 */
class VoteRulesService
{
    /**
     * Calcule le résultat d'un vote selon la règle spécifiée
     * 
     * Analyse les votes soumis et applique la règle de décision configurée.
     * Les votes non numériques (?, café, ∞) sont automatiquement filtrés
     * avant l'application de toute règle. La règle "strict" vérifie ensuite
     * l'unanimité sur les votes numériques restants.
     *
     * @param array<int|string> $votes Liste des votes soumis (valeurs numériques ou spéciales: ?, café, ∞)
     * @param string $rule Règle à appliquer (strict, moyenne, mediane, majorite_absolue, majorite_relative)
     * @return array{valid: bool, value: string|int|null, reason: string} Résultat du calcul avec validation et explication
     */
    public static function computeResult(array $votes, string $rule): array
    {
        // Filtrer et ne garder que les votes numériques pour toutes les règles
        $numeric = array_values(array_filter($votes, 'is_numeric'));
        $numeric = array_map('intval', $numeric);
        sort($numeric);

        $count = count($numeric);

        if ($count === 0) {
            return ['valid' => false, 'value' => null, 'reason' => 'Aucun vote numérique'];
        }

        switch ($rule) {
            case 'strict':
                $unique = array_unique($numeric);
                if (count($unique) === 1) {
                    return [
                        'valid' => true,
                        'value' => reset($unique),
                        'reason' => 'Unanimité'
                    ];
                }
                return [
                    'valid' => false,
                    'value' => null,
                    'reason' => "Pas d'unanimité"
                ];

            case 'moyenne':
                $avg = array_sum($numeric) / $count;
                return ['valid' => true, 'value' => (int)round($avg), 'reason' => 'Moyenne'];

            case 'mediane':
                $mid = intdiv($count, 2);
                $median = ($count % 2 === 0)
                    ? ($numeric[$mid - 1] + $numeric[$mid]) / 2
                    : $numeric[$mid];
                return ['valid' => true, 'value' => (int)round($median), 'reason' => 'Médiane'];

            case 'majorite_absolue':
                $freq = array_count_values($numeric);
                arsort($freq);
                $topValue = array_key_first($freq);
                $topCount = $freq[$topValue];

                if ($topCount > $count / 2) {
                    return [
                        'valid' => true,
                        'value' => $topValue,
                        'reason' => 'Majorité absolue'
                    ];
                }

                return [
                    'valid' => false,
                    'value' => null,
                    'reason' => 'Pas de majorité absolue'
                ];

            case 'majorite_relative':
            default:
                $freq = array_count_values($numeric);
                arsort($freq);

                // En cas d'égalité, prendre la plus grande valeur
                $maxCount = max($freq);
                $candidates = array_keys(array_filter($freq, fn($c) => $c === $maxCount));
                $value = max($candidates);

                return [
                    'valid' => true,
                    'value' => $value,
                    'reason' => 'Majorité relative'
                ];
        }
    }
}