<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Session;
use PDO;

class SessionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->pdo = getPDO();
        
        // Clean database before each test
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }

    public function testCreateSession(): void
    {
        $session = Session::create(
            $this->pdo,
            'Test Session',
            10,
            'strict'
        );

        $this->assertNotNull($session);
        $this->assertNotNull($session->id);
        $this->assertEquals('Test Session', $session->session_name);
        $this->assertEquals(10, $session->max_players);
        $this->assertEquals('strict', $session->vote_rule);
        $this->assertEquals('waiting', $session->status);
        $this->assertNotEmpty($session->session_code);
        $this->assertEquals(8, strlen($session->session_code));
    }

    public function testFindByCode(): void
    {
        $session = Session::create($this->pdo, 'Test', 10, 'strict');
        $code = $session->session_code;

        $found = Session::findByCode($this->pdo, $code);

        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);
        $this->assertEquals($code, $found->session_code);
    }

    public function testFindById(): void
    {
        $session = Session::create($this->pdo, 'Test', 10, 'strict');
        $id = $session->id;

        $found = Session::findById($this->pdo, $id);

        $this->assertNotNull($found);
        $this->assertEquals($id, $found->id);
        $this->assertEquals('Test', $found->session_name);
    }

    public function testSetStatus(): void
    {
        $session = Session::create($this->pdo, 'Test', 10, 'strict');

        $session->setStatus($this->pdo, 'voting');
        $updated = Session::findById($this->pdo, $session->id);

        $this->assertEquals('voting', $updated->status);
    }

    public function testSetCurrentStory(): void
    {
        $session = Session::create($this->pdo, 'Test', 10, 'strict');
        
        $session->setCurrentStory($this->pdo, 99);
        $updated = Session::findById($this->pdo, $session->id);

        $this->assertEquals(99, $updated->current_story_id);
    }

    public function testCountPlayers(): void
    {
        require_once __DIR__ . '/../../src/Models/Player.php';
        
        $session = Session::create($this->pdo, 'Test', 10, 'strict');
        
        $this->assertEquals(0, $session->countPlayers($this->pdo));
        
        \App\Models\Player::create($this->pdo, $session->id, 'Player 1', false);
        \App\Models\Player::create($this->pdo, $session->id, 'Player 2', false);
        
        $this->assertEquals(2, $session->countPlayers($this->pdo));
    }

    public function testUniqueSessionCode(): void
    {
        $session1 = Session::create($this->pdo, 'Session 1', 10, 'strict');
        $session2 = Session::create($this->pdo, 'Session 2', 10, 'strict');

        $this->assertNotEquals($session1->session_code, $session2->session_code);
    }

    protected function tearDown(): void
    {
        // Clean database after each test
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}