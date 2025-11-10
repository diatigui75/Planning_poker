import fs from 'fs';

export function saveJSON(filePath, data) {
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf-8');
}

export function loadJSON(filePath) {
  if (!fs.existsSync(filePath)) throw new Error('File not found');
  return JSON.parse(fs.readFileSync(filePath, 'utf-8'));
}
