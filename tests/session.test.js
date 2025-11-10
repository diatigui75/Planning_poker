import { Session } from '../src/session.js';

test('can add and vote players', () => {
  const s = new Session('test', 'unanimity', ['Alice']);
  s.addPlayer('Bob');
  s.vote('Alice', 5);
  s.vote('Bob', 8);
  expect(s.allVoted()).toBe(true);
});
