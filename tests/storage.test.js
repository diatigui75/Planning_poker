import fs from 'fs';
import { saveJSON, loadJSON } from '../src/storage.js';

const path = './tests/tmp.json';

afterAll(() => {
  if (fs.existsSync(path)) fs.unlinkSync(path);
});

test('save and load JSON', () => {
  const data = { name: 'Test', value: 42 };
  saveJSON(path, data);
  const loaded = loadJSON(path);
  expect(loaded).toEqual(data);
});
