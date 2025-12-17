<?php

namespace App\Models;

use PDO;

/**
 * Modèle représentant une user story à estimer en Planning Poker
 * 
 * Gère les user stories d'une session avec leurs propriétés (titre, description,
 * priorité), leur estimation et leur statut dans le processus de vote.
 * Fournit des méthodes pour importer, rechercher, estimer et suivre
 * la progression des stories.
 * 
 * @package App\Models
 * @author Melissa Aliouche
 */
class UserStory
{
    /** @var int Identifiant unique de la user story */
    public int $id;
    
    /** @var int Identifiant de la session à laquelle appartient la story */
    public int $session_id;
    
    /** @var string Identifiant métier de la story (ex: US-001) */
    public string $story_id;
    
    /** @var string Titre de la user story */
    public string $title;
    
    /** @var string Description détaillée de la story */
    public string $description;
    
    /** @var string Priorité de la story (haute, moyenne, basse) */
    public string $priority;
    
    /** @var int|null Estimation en points de complexité (null si non estimée) */
    public ?int $estimation;
    
    /** @var string Statut de la story (pending, voting, estimated) */
    public string $status;
    
    /** @var int Position de la story dans l'ordre du backlog */
    public int $order_index;

    /**
     * Crée une instance UserStory à partir d'un tableau de données
     * 
     * Hydrate un objet UserStory avec les données provenant de la base de données.
     * Effectue les conversions de types nécessaires et gère les valeurs nullables.
     *
     * @param array<string, mixed> $d Tableau associatif contenant les données de la story
     * @return self Instance de la user story hydratée
     */
    public static function fromArray(array $d): self
    {
        $u = new self();
        $u->id = (int)$d['id'];
        $u->session_id = (int)$d['session_id'];
        $u->story_id = $d['story_id'];
        $u->title = $d['title'];
        $u->description = $d['description'] ?? '';
        $u->priority = $d['priority'];
        $u->estimation = $d['estimation'] !== null ? (int)$d['estimation'] : null;
        $u->status = $d['status'];
        $u->order_index = (int)$d['order_index'];
        return $u;
    }

    /**
     * Importe un backlog de stories depuis un tableau JSON
     * 
     * Supprime toutes les stories existantes de la session et les remplace
     * par les nouvelles stories importées. Chaque story est initialisée
     * avec le statut "pending" et un ordre séquentiel.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session cible
     * @param array<array{id: string, titre: string, description?: string, priorite?: string}> $stories Tableau de stories à importer
     * @return void
     */
    public static function importJson(PDO $pdo, int $sessionId, array $stories): void
    {
        // Supprimer les anciennes stories de la session
        $stmt = $pdo->prepare("DELETE FROM user_stories WHERE session_id = :sid");
        $stmt->execute([':sid' => $sessionId]);

        $order = 0;
        $stmt = $pdo->prepare("
            INSERT INTO user_stories (session_id, story_id, title, description, priority, order_index, status)
            VALUES (:sid, :story_id, :title, :desc, :prio, :ord, 'pending')
        ");

        foreach ($stories as $s) {
            $stmt->execute([
                ':sid' => $sessionId,
                ':story_id' => $s['id'],
                ':title' => $s['titre'],
                ':desc' => $s['description'] ?? '',
                ':prio' => $s['priorite'] ?? 'moyenne',
                ':ord' => $order++,
            ]);
        }
    }

    /**
     * Récupère la user story actuellement en cours de vote
     * 
     * Recherche d'abord la story définie comme courante dans la session,
     * puis si aucune n'est trouvée, sélectionne automatiquement la première
     * story non estimée et met à jour la session en conséquence.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return self|null Instance de la story courante, null si aucune story disponible
     */
    public static function findCurrent(PDO $pdo, int $sessionId): ?self
    {
        // D'abord chercher la story définie comme current_story_id dans la session
        $stmt = $pdo->prepare("
            SELECT us.* FROM user_stories us
            JOIN sessions s ON s.current_story_id = us.id
            WHERE s.id = :sid AND us.status IN ('pending', 'voting')
        ");
        $stmt->execute([':sid' => $sessionId]);
        $data = $stmt->fetch();
        
        if ($data) {
            return self::fromArray($data);
        }
        
        // Sinon, chercher la première story non estimée
        $stmt = $pdo->prepare("
            SELECT * FROM user_stories
            WHERE session_id = :sid AND status = 'pending'
            ORDER BY order_index ASC
            LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        $data = $stmt->fetch();
        
        if ($data) {
            $story = self::fromArray($data);
            // Mettre à jour la session pour pointer vers cette story
            $updateStmt = $pdo->prepare("UPDATE sessions SET current_story_id = :story_id WHERE id = :sid");
            $updateStmt->execute([':story_id' => $story->id, ':sid' => $sessionId]);
            return $story;
        }
        
        return null;
    }

    /**
     * Recherche une user story par son identifiant
     * 
     * Récupère toutes les informations d'une story depuis la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $id Identifiant de la user story
     * @return self|null Instance de la story trouvée, null si inexistante
     */
    public static function findById(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM user_stories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }

    /**
     * Récupère toutes les user stories d'une session
     * 
     * Retourne la liste complète des stories ordonnée par leur position
     * dans le backlog (order_index).
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return array<self> Liste des user stories, triées par ordre du backlog
     */
    public static function findBySession(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM user_stories WHERE session_id = :sid ORDER BY order_index ASC");
        $stmt->execute([':sid' => $sessionId]);
        $stories = [];
        while ($row = $stmt->fetch()) {
            $stories[] = self::fromArray($row);
        }
        return $stories;
    }

    /**
     * Définit l'estimation finale de la user story
     * 
     * Enregistre la valeur d'estimation validée et change automatiquement
     * le statut de la story en "estimated".
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $value Valeur de l'estimation en points de complexité
     * @return void
     */
    public function setEstimation(PDO $pdo, int $value): void
    {
        $this->estimation = $value;
        $this->status = 'estimated';
        $stmt = $pdo->prepare("
            UPDATE user_stories SET estimation = :e, status = 'estimated'
            WHERE id = :id
        ");
        $stmt->execute([
            ':e' => $value,
            ':id' => $this->id,
        ]);
    }

    /**
     * Change le statut de la user story
     * 
     * Met à jour l'état de la story (pending, voting, estimated)
     * et persiste le changement dans la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param string $status Nouveau statut de la story
     * @return void
     */
    public function setStatus(PDO $pdo, string $status): void
    {
        $this->status = $status;
        $stmt = $pdo->prepare("UPDATE user_stories SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':id' => $this->id,
        ]);
    }

    /**
     * Calcule les statistiques de progression des user stories
     * 
     * Retourne le nombre total de stories et leur répartition par statut
     * (estimées, en attente, en cours de vote). Utile pour afficher
     * une barre de progression de la session.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return array{total: int, estimated: int, pending: int, voting: int} Statistiques de progression
     */
    public static function getStats(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'estimated' THEN 1 ELSE 0 END) as estimated,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'voting' THEN 1 ELSE 0 END) as voting
            FROM user_stories 
            WHERE session_id = :sid
        ");
        $stmt->execute([':sid' => $sessionId]);
        $result = $stmt->fetch();
        
        // S'assurer que tous les champs sont définis
        if (!$result) {
            return [
                'total' => 0,
                'estimated' => 0,
                'pending' => 0,
                'voting' => 0
            ];
        }
        
        return [
            'total' => (int)$result['total'],
            'estimated' => (int)$result['estimated'],
            'pending' => (int)$result['pending'],
            'voting' => (int)$result['voting']
        ];
    }
}