
import { unanimous, average, median } from './rules.js';

/**
 * Calcule le résultat final des votes selon la règle choisie.
 * @param {Array<number>} votes - Liste des votes des joueurs
 * @param {string} rule - La règle de calcul ('unanimity', 'average', 'median')
 * @returns {number|null} Le résultat calculé
 * @throws {Error} Si la règle est inconnue
 */
export function computeResult(votes, rule) {
  switch (rule) {
    case 'unanimity': return unanimous(votes);
    case 'average': return average(votes);
    case 'median': return median(votes);
    default: throw new Error('Unknown rule');
  }
}
