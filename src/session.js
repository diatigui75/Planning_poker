export class Session {
  constructor(name, rule, players = []) {
    this.name = name;
    this.rule = rule;
    this.players = players;
    this.votes = {};
  }

  addPlayer(pseudo) {
    if (this.players.includes(pseudo)) throw new Error('Player already exists');
    this.players.push(pseudo);
  }

  vote(pseudo, value) {
    if (!this.players.includes(pseudo)) throw new Error('Unknown player');
    this.votes[pseudo] = value;
  }

  allVoted() {
    return this.players.length === Object.keys(this.votes).length;
  }
}
