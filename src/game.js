import { unanimous, average, median } from './rules.js';

export function computeResult(votes, rule) {
  switch (rule) {
    case 'unanimity': return unanimous(votes);
    case 'average': return average(votes);
    case 'median': return median(votes);
    default: throw new Error('Unknown rule');
  }
}
