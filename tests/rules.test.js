import { unanimous, average, median } from '../src/rules.js';


test('unanimous returns correct result', () => {
  expect(unanimous([5, 5, 5])).toBe(5);
  expect(unanimous([5, 3, 5])).toBeNull();
});

test('average works correctly', () => {
  expect(average([2, 4, 6])).toBe(4);
});

test('median works correctly', () => {
  expect(median([1, 3, 5])).toBe(3);
  expect(median([2, 4, 6, 8])).toBe(5);
});


test('throws error if votes array is empty', () => {
  expect(() => average([])).toThrow('No votes provided');
  expect(() => median([])).toThrow('No votes provided');
  expect(() => unanimous([])).toThrow('No votes provided');
});

test('throws error if votes contain invalid numbers', () => {
  expect(() => average([1, 'a', 3])).toThrow('Invalid vote value');
  expect(() => median(['x', 2, 3])).toThrow('Invalid vote value');
});
