<?php

namespace App\Models;

use PDO;

/**
 * Modèle représentant un joueur participant à une session Planning Poker
 * 
 * Gère les joueurs d'une session, leur rôle (Scrum Master ou participant),
 * et leur statut de connexion. Fournit des méthodes pour créer, rechercher
 * et mettre à jour les informations des joueurs.
 * 
 * @package App\Models
 * @author Melissa Aliouche
 */
class Player
{
    /** @var int Identifiant unique du joueur */
    public int $id;
    
    /** @var int Identifiant de la session à laquelle appartient le joueur */
    public int $session_id;
    
    /** @var string Pseudo du joueur (unique par session) */
    public string $pseudo;
    
    /** @var bool Indique si le joueur est le Scrum Master de la session */
    public bool $is_scrum_master;
    
    /** @var bool Indique si le joueur est actuellement connecté */
    public bool $is_connected;

    /**
     * Crée un nouveau joueur dans une session
     * 
     * Insère un joueur dans la base de données avec son pseudo et son rôle.
     * Le joueur est automatiquement marqué comme connecté à la création.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param string $pseudo Pseudo du joueur
     * @param bool $scrumMaster Indique si le joueur est Scrum Master (défaut: false)
     * @return self Instance du joueur créé avec toutes ses propriétés
     */
    public static function create(PDO $pdo, int $sessionId, string $pseudo, bool $scrumMaster = false): self
    {
        $stmt = $pdo->prepare("
            INSERT INTO players (session_id, pseudo, is_scrum_master, is_connected)
            VALUES (:session_id, :pseudo, :sm, 1)
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':pseudo' => $pseudo,
            ':sm' => $scrumMaster ? 1 : 0,
        ]);

        return self::findById($pdo, (int)$pdo->lastInsertId());
    }

    /**
     * Recherche un joueur par son identifiant
     * 
     * Récupère toutes les informations d'un joueur depuis la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $id Identifiant du joueur
     * @return self|null Instance du joueur trouvé, null si inexistant
     */
    public static function findById(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM players WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }

    /**
     * Récupère tous les joueurs d'une session
     * 
     * Retourne la liste des joueurs triée par rôle (Scrum Master en premier)
     * puis par ordre de rejointe (plus anciens en premier).
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return array<self> Liste des joueurs de la session, triés par rôle et ancienneté
     */
    public static function findBySession(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM players WHERE session_id = :sid ORDER BY is_scrum_master DESC, joined_at ASC");
        $stmt->execute([':sid' => $sessionId]);
        $players = [];
        while ($row = $stmt->fetch()) {
            $players[] = self::fromArray($row);
        }
        return $players;
    }

    /**
     * Met à jour le statut de connexion du joueur (méthode d'instance)
     * 
     * Change l'état de connexion du joueur courant et persiste
     * le changement dans la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param bool $connected True si le joueur est connecté, false sinon
     * @return void
     */
    public function updateConnection(PDO $pdo, bool $connected): void
    {
        $this->is_connected = $connected;
        $stmt = $pdo->prepare("UPDATE players SET is_connected = :conn WHERE id = :id");
        $stmt->execute([
            ':conn' => $connected ? 1 : 0,
            ':id' => $this->id,
        ]);
    }

    /**
     * Met à jour le statut de connexion d'un joueur (méthode statique)
     * 
     * Version statique permettant de mettre à jour la connexion
     * sans avoir à instancier l'objet Player au préalable.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $playerId Identifiant du joueur
     * @param bool $connected True si le joueur est connecté, false sinon
     * @return void
     */
    public static function updateConnectionStatic(PDO $pdo, int $playerId, bool $connected): void
    {
        $stmt = $pdo->prepare("UPDATE players SET is_connected = :conn WHERE id = :id");
        $stmt->execute([
            ':conn' => $connected ? 1 : 0,
            ':id' => $playerId,
        ]);
    }

    /**
     * Crée une instance Player à partir d'un tableau de données
     * 
     * Hydrate un objet Player avec les données provenant de la base de données.
     * Effectue les conversions de types nécessaires (int, bool).
     *
     * @param array<string, mixed> $data Tableau associatif contenant les données du joueur
     * @return self Instance du joueur hydratée
     */
    public static function fromArray(array $data): self
    {
        $p = new self();
        $p->id = (int)$data['id'];
        $p->session_id = (int)$data['session_id'];
        $p->pseudo = $data['pseudo'];
        $p->is_scrum_master = (bool)$data['is_scrum_master'];
        $p->is_connected = (bool)$data['is_connected'];
        return $p;
    }
}