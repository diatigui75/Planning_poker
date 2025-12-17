<?php

namespace App\Models;

use PDO;

/**
 * Modèle représentant une session Planning Poker
 * 
 * Gère les sessions de vote Planning Poker avec leurs paramètres
 * (règles de vote, limites de joueurs), leur état (statut, story en cours)
 * et fournit des méthodes pour créer, rechercher et modifier les sessions.
 * 
 * @package App\Models
 * @author Melissa Aliouche
 */
class Session
{
    /** @var int Identifiant unique de la session */
    public int $id;
    
    /** @var string Code de session unique (8 caractères) pour rejoindre la session */
    public string $session_code;
    
    /** @var string Nom descriptif de la session */
    public string $session_name;
    
    /** @var int Nombre maximum de joueurs autorisés dans la session */
    public int $max_players;
    
    /** @var string Règle de vote appliquée (strict, moyenne, médiane, etc.) */
    public string $vote_rule;
    
    /** @var int|null Identifiant de la user story actuellement en cours de vote */
    public ?int $current_story_id;
    
    /** @var string Statut de la session (waiting, voting, revealed, coffee_break, finished) */
    public string $status;
    
    /** @var int Numéro du tour de vote actuel */
    public int $current_round;

    /**
     * Crée une nouvelle session Planning Poker
     * 
     * Génère automatiquement un code de session unique de 8 caractères
     * et initialise la session avec le statut "waiting".
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param string $name Nom de la session
     * @param int $maxPlayers Nombre maximum de joueurs autorisés
     * @param string $rule Règle de vote à appliquer
     * @return self Instance de la session créée avec toutes ses propriétés
     */
    public static function create(PDO $pdo, string $name, int $maxPlayers, string $rule): self
    {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $stmt = $pdo->prepare("
            INSERT INTO sessions (session_code, session_name, max_players, vote_rule, status)
            VALUES (:code, :name, :max_players, :rule, 'waiting')
        ");
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':max_players' => $maxPlayers,
            ':rule' => $rule,
        ]);

        return self::findById($pdo, (int)$pdo->lastInsertId());
    }

    /**
     * Recherche une session par son code
     * 
     * Permet aux joueurs de rejoindre une session existante en saisissant
     * son code. La recherche est insensible à la casse.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param string $code Code de la session (8 caractères)
     * @return self|null Instance de la session trouvée, null si inexistante
     */
    public static function findByCode(PDO $pdo, string $code): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_code = :code");
        $stmt->execute([':code' => strtoupper($code)]);
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }

    /**
     * Recherche une session par son identifiant
     * 
     * Récupère toutes les informations d'une session depuis la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $id Identifiant de la session
     * @return self|null Instance de la session trouvée, null si inexistante
     */
    public static function findById(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }

    /**
     * Crée une instance Session à partir d'un tableau de données
     * 
     * Hydrate un objet Session avec les données provenant de la base de données.
     * Effectue les conversions de types nécessaires et initialise le tour à 1.
     *
     * @param array<string, mixed> $data Tableau associatif contenant les données de la session
     * @return self Instance de la session hydratée
     */
    public static function fromArray(array $data): self
    {
        $s = new self();
        $s->id = (int)$data['id'];
        $s->session_code = $data['session_code'];
        $s->session_name = $data['session_name'];
        $s->max_players = (int)$data['max_players'];
        $s->vote_rule = $data['vote_rule'];
        $s->current_story_id = $data['current_story_id'] ? (int)$data['current_story_id'] : null;
        $s->status = $data['status'];
        $s->current_round = 1;
        return $s;
    }

    /**
     * Change le statut de la session
     * 
     * Met à jour l'état de la session (waiting, voting, revealed, coffee_break, finished)
     * et persiste le changement dans la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param string $status Nouveau statut de la session
     * @return void
     */
    public function setStatus(PDO $pdo, string $status): void
    {
        $this->status = $status;
        $stmt = $pdo->prepare("UPDATE sessions SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':id' => $this->id,
        ]);
    }

    /**
     * Définit la user story actuellement en cours de vote
     * 
     * Change la story active de la session. Peut être null si aucune
     * story n'est en cours (session terminée ou en attente).
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int|null $storyId Identifiant de la story à définir comme courante, null pour aucune
     * @return void
     */
    public function setCurrentStory(PDO $pdo, ?int $storyId): void
    {
        $this->current_story_id = $storyId;
        $stmt = $pdo->prepare("UPDATE sessions SET current_story_id = :story_id WHERE id = :id");
        $stmt->execute([
            ':story_id' => $storyId,
            ':id' => $this->id,
        ]);
    }

    /**
     * Compte le nombre total de joueurs dans la session
     * 
     * Retourne le nombre de joueurs (connectés ou déconnectés)
     * qui ont rejoint la session.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @return int Nombre total de joueurs dans la session
     */
    public function countPlayers(PDO $pdo): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM players WHERE session_id = :sid");
        $stmt->execute([':sid' => $this->id]);
        $result = $stmt->fetch();
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Compte le nombre de joueurs actuellement connectés
     * 
     * Retourne uniquement le nombre de joueurs actifs dans la session,
     * utile pour afficher la présence en temps réel.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return int Nombre de joueurs connectés
     */
    public static function getConnectedPlayersCount(PDO $pdo, int $sessionId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM players WHERE session_id = :sid AND is_connected = 1");
        $stmt->execute([':sid' => $sessionId]);
        $result = $stmt->fetch();
        return (int)($result['cnt'] ?? 0);
    }
}