

/**
 * Représente une session de votes
 * @class
 */
export class Session {
  /**
   * Crée une nouvelle session
   * @param {string} name - Nom de la session
   * @param {string} rule - Règle de calcul ('unanimity', 'average', 'median')
   * @param {Array<string>} players - Liste initiale de joueurs
   */
  constructor(name, rule, players = []) {
    this.name = name;
    this.rule = rule;
    this.players = players;
    this.votes = {};
  }

  /**
   * Ajoute un joueur à la session
   * @param {string} pseudo - Pseudo du joueur
   * @throws {Error} Si le joueur existe déjà
   */
  addPlayer(pseudo) {
    if (this.players.includes(pseudo)) throw new Error('Player already exists');
    this.players.push(pseudo);
  }

  /**
   * Enregistre le vote d’un joueur
   * @param {string} pseudo - Pseudo du joueur
   * @param {number} value - Valeur du vote
   * @throws {Error} Si le joueur n’existe pas
   */
  vote(pseudo, value) {
    if (!this.players.includes(pseudo)) throw new Error('Unknown player');
    this.votes[pseudo] = value;
  }

  /**
   * Vérifie si tous les joueurs ont voté
   * @returns {boolean} true si tous les joueurs ont voté, false sinon
   */
  allVoted() {
    return this.players.length === Object.keys(this.votes).length;
  }
}
