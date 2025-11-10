
// Règles de calcul pour les votes
export function unanimous(votes) {
  if (!Array.isArray(votes) || votes.length === 0) throw new Error('No votes provided');
  const first = votes[0];
  return votes.every(v => v === first) ? first : null;
}

export function average(votes) {
  if (!Array.isArray(votes) || votes.length === 0) throw new Error('No votes provided');
  if (votes.some(v => typeof v !== 'number' || isNaN(v))) throw new Error('Invalid vote value');
  const sum = votes.reduce((a, b) => a + b, 0);
  return Math.round(sum / votes.length);
}

export function median(votes) {
  if (!Array.isArray(votes) || votes.length === 0) throw new Error('No votes provided');
  if (votes.some(v => typeof v !== 'number' || isNaN(v))) throw new Error('Invalid vote value');
  const sorted = [...votes].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 === 0
    ? Math.round((sorted[mid - 1] + sorted[mid]) / 2)
    : sorted[mid];
}
