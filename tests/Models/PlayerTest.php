<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Session;
use App\Models\Player;
use PDO;

class PlayerTest extends TestCase
{
    private PDO $pdo;
    private Session $session;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->pdo = getPDO();
        
        // Clean database
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
        
        // Create a test session
        $this->session = Session::create($this->pdo, 'Test Session', 10, 'strict');
    }

    public function testCreatePlayer(): void
    {
        $player = Player::create($this->pdo, $this->session->id, 'Alice', false);

        $this->assertNotNull($player);
        $this->assertNotNull($player->id);
        $this->assertEquals($this->session->id, $player->session_id);
        $this->assertEquals('Alice', $player->pseudo);
        $this->assertFalse($player->is_scrum_master);
        $this->assertTrue($player->is_connected);
    }

    public function testCreateScrumMaster(): void
    {
        $player = Player::create($this->pdo, $this->session->id, 'Bob', true);

        $this->assertTrue($player->is_scrum_master);
    }

    public function testFindById(): void
    {
        $player = Player::create($this->pdo, $this->session->id, 'Charlie', false);
        
        $found = Player::findById($this->pdo, $player->id);

        $this->assertNotNull($found);
        $this->assertEquals($player->id, $found->id);
        $this->assertEquals('Charlie', $found->pseudo);
    }

    public function testFindBySession(): void
    {
        Player::create($this->pdo, $this->session->id, 'Alice', false);
        Player::create($this->pdo, $this->session->id, 'Bob', true);
        Player::create($this->pdo, $this->session->id, 'Charlie', false);

        $players = Player::findBySession($this->pdo, $this->session->id);

        $this->assertCount(3, $players);
        // Scrum Master should be first
        $this->assertEquals('Bob', $players[0]->pseudo);
        $this->assertTrue($players[0]->is_scrum_master);
    }

    public function testUpdateConnection(): void
    {
        $player = Player::create($this->pdo, $this->session->id, 'David', false);
        $this->assertTrue($player->is_connected);

        $player->updateConnection($this->pdo, false);
        $updated = Player::findById($this->pdo, $player->id);

        $this->assertFalse($updated->is_connected);
    }

    public function testUpdateConnectionStatic(): void
    {
        $player = Player::create($this->pdo, $this->session->id, 'Eve', false);
        
        Player::updateConnectionStatic($this->pdo, $player->id, false);
        $updated = Player::findById($this->pdo, $player->id);

        $this->assertFalse($updated->is_connected);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 1,
            'session_id' => 99,
            'pseudo' => 'Test Player',
            'is_scrum_master' => 1,
            'is_connected' => 0
        ];

        $player = Player::fromArray($data);

        $this->assertEquals(1, $player->id);
        $this->assertEquals(99, $player->session_id);
        $this->assertEquals('Test Player', $player->pseudo);
        $this->assertTrue($player->is_scrum_master);
        $this->assertFalse($player->is_connected);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}