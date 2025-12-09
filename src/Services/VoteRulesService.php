<?php

namespace App\Services;

class VoteRulesService
{
    public static function computeResult(array $votes, string $rule): array
    {
        // Filtrer les votes numériques uniquement pour les calculs
        $numeric = array_values(array_filter($votes, function ($v) {
            return is_numeric($v);
        }));

        sort($numeric);
        $count = count($numeric);

        if ($count === 0) {
            return ['valid' => false, 'value' => null, 'reason' => 'Aucun vote numérique'];
        }

        switch ($rule) {
            case 'strict':
                $unique = array_unique($votes);
                if (count($unique) === 1) {
                    return ['valid' => true, 'value' => reset($unique), 'reason' => 'Unanimité'];
                }
                return ['valid' => false, 'value' => null, 'reason' => 'Pas d’unanimité'];

            case 'moyenne':
                $avg = array_sum($numeric) / $count;
                return ['valid' => true, 'value' => round($avg), 'reason' => 'Moyenne'];

            case 'mediane':
                $mid = intdiv($count, 2);
                $median = ($count % 2 === 0)
                    ? ($numeric[$mid - 1] + $numeric[$mid]) / 2
                    : $numeric[$mid];
                return ['valid' => true, 'value' => round($median), 'reason' => 'Médiane'];

            case 'majorite_absolue':
                $freq = array_count_values($votes);
                arsort($freq);
                $topValue = array_key_first($freq);
                $topCount = $freq[$topValue];
                if ($topCount > $count / 2) {
                    return ['valid' => true, 'value' => $topValue, 'reason' => 'Majorité absolue'];
                }
                return ['valid' => false, 'value' => null, 'reason' => 'Pas de majorité absolue'];

            case 'majorite_relative':
            default:
                $freq = array_count_values($votes);
                arsort($freq);
                $topValue = array_key_first($freq);
                return ['valid' => true, 'value' => $topValue, 'reason' => 'Majorité relative'];
        }
    }
}
