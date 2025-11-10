import { computeResult } from '../src/game.js';

test('computeResult with unanimity returns value if all votes equal', () => {
  const votes = [5, 5, 5];
  const result = computeResult(votes, 'unanimity');
  expect(result).toBe(5);
});

test('computeResult with unanimity returns null if not all votes equal', () => {
  const votes = [5, 3, 5];
  const result = computeResult(votes, 'unanimity');
  expect(result).toBeNull();
});


test('computeResult with average returns correct mean value', () => {
  const votes = [2, 4, 6];
  const result = computeResult(votes, 'average');
  expect(result).toBe(4);
});


test('computeResult with median returns correct median for odd number', () => {
  const votes = [1, 5, 3];
  const result = computeResult(votes, 'median');
  expect(result).toBe(3);
});

test('computeResult with median returns correct median for even number', () => {
  const votes = [2, 4, 6, 8];
  const result = computeResult(votes, 'median');
  expect(result).toBe(5);
});


test('computeResult throws error for unknown rule', () => {
  const votes = [1, 2, 3];
  expect(() => computeResult(votes, 'invalidRule')).toThrow('Unknown rule');
});
