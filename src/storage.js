

import fs from 'fs';

/**
 * Sauvegarde des données JSON dans un fichier
 * @param {string} filePath - Chemin du fichier
 * @param {Object} data - Données à sauvegarder
 */
export function saveJSON(filePath, data) {
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf-8');
}

/**
 * Charge des données JSON depuis un fichier
 * @param {string} filePath - Chemin du fichier
 * @returns {Object} Données JSON
 * @throws {Error} Si le fichier n’existe pas
 */
export function loadJSON(filePath) {
  if (!fs.existsSync(filePath)) throw new Error('File not found');
  return JSON.parse(fs.readFileSync(filePath, 'utf-8'));
}
