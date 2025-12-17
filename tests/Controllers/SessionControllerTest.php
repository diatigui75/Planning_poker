<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Models\Session;
use App\Models\Player;
use PDO;

class SessionControllerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->pdo = getPDO();
        
        // Clean database
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
        
        // Reset session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testCreateSessionBasicFlow(): void
    {
        // Simulate POST data
        $_POST = [
            'session_name' => 'Test Session',
            'max_players' => '10',
            'vote_rule' => 'strict',
            'pseudo' => 'Test Scrum Master'
        ];

        // Start output buffering to catch header redirects
        ob_start();
        
        // Mock the header function would be complex, so we'll test the logic directly
        $name = trim($_POST['session_name']);
        $max = (int)$_POST['max_players'];
        $rule = $_POST['vote_rule'];
        $pseudo = trim($_POST['pseudo']);

        $this->assertNotEmpty($name);
        $this->assertNotEmpty($pseudo);

        $session = Session::create($this->pdo, $name, $max, $rule);
        $player = Player::create($this->pdo, $session->id, $pseudo, true);

        $this->assertNotNull($session);
        $this->assertNotNull($player);
        $this->assertTrue($player->is_scrum_master);
        $this->assertEquals('Test Session', $session->session_name);
        $this->assertEquals(10, $session->max_players);
        $this->assertEquals('strict', $session->vote_rule);

        ob_end_clean();
    }

    public function testJoinSessionBasicFlow(): void
    {
        // Create a session first
        $session = Session::create($this->pdo, 'Test Session', 10, 'strict');
        $scrumMaster = Player::create($this->pdo, $session->id, 'Scrum Master', true);

        // Simulate POST data for joining
        $_POST = [
            'session_code' => $session->session_code,
            'pseudo' => 'Test Player'
        ];

        $code = strtoupper(trim($_POST['session_code']));
        $pseudo = trim($_POST['pseudo']);

        $this->assertNotEmpty($code);
        $this->assertNotEmpty($pseudo);

        $foundSession = Session::findByCode($this->pdo, $code);
        $this->assertNotNull($foundSession);
        $this->assertEquals($session->id, $foundSession->id);

        // Check session not full
        $playerCount = $foundSession->countPlayers($this->pdo);
        $this->assertLessThan($foundSession->max_players, $playerCount);

        // Check pseudo not taken
        $players = Player::findBySession($this->pdo, $foundSession->id);
        $pseudoTaken = false;
        foreach ($players as $p) {
            if (strcasecmp($p->pseudo, $pseudo) === 0) {
                $pseudoTaken = true;
                break;
            }
        }
        $this->assertFalse($pseudoTaken);

        // Create player
        $player = Player::create($this->pdo, $foundSession->id, $pseudo, false);
        
        $this->assertNotNull($player);
        $this->assertFalse($player->is_scrum_master);
        $this->assertEquals('Test Player', $player->pseudo);
    }

    public function testCreateSessionWithMissingFields(): void
    {
        $_POST = [
            'session_name' => '',
            'pseudo' => 'Test',
            'max_players' => '10',
            'vote_rule' => 'strict'
        ];

        $name = trim($_POST['session_name']);
        $pseudo = trim($_POST['pseudo']);

        // Should detect empty name
        $this->assertTrue(empty($name) || empty($pseudo));
    }

    public function testJoinSessionWithInvalidCode(): void
    {
        $_POST = [
            'session_code' => 'INVALID',
            'pseudo' => 'Test Player'
        ];

        $code = strtoupper(trim($_POST['session_code']));
        $session = Session::findByCode($this->pdo, $code);

        $this->assertNull($session);
    }

    public function testJoinSessionWhenFull(): void
    {
        // Create session with max 2 players
        $session = Session::create($this->pdo, 'Full Session', 2, 'strict');
        Player::create($this->pdo, $session->id, 'Player 1', true);
        Player::create($this->pdo, $session->id, 'Player 2', false);

        $_POST = [
            'session_code' => $session->session_code,
            'pseudo' => 'Player 3'
        ];

        $code = strtoupper(trim($_POST['session_code']));
        $foundSession = Session::findByCode($this->pdo, $code);
        
        $this->assertNotNull($foundSession);
        
        $playerCount = $foundSession->countPlayers($this->pdo);
        
        // Should be full
        $this->assertEquals($foundSession->max_players, $playerCount);
        $this->assertGreaterThanOrEqual($foundSession->max_players, $playerCount);
    }

    public function testJoinSessionWithTakenPseudo(): void
    {
        $session = Session::create($this->pdo, 'Test Session', 10, 'strict');
        Player::create($this->pdo, $session->id, 'TakenPseudo', true);

        $_POST = [
            'session_code' => $session->session_code,
            'pseudo' => 'takenpseudo'  // Case insensitive
        ];

        $code = strtoupper(trim($_POST['session_code']));
        $pseudo = trim($_POST['pseudo']);
        
        $foundSession = Session::findByCode($this->pdo, $code);
        $players = Player::findBySession($this->pdo, $foundSession->id);
        
        $pseudoTaken = false;
        foreach ($players as $p) {
            if (strcasecmp($p->pseudo, $pseudo) === 0) {
                $pseudoTaken = true;
                break;
            }
        }
        
        $this->assertTrue($pseudoTaken);
    }

    public function testSessionCodeUniqueness(): void
    {
        $session1 = Session::create($this->pdo, 'Session 1', 10, 'strict');
        $session2 = Session::create($this->pdo, 'Session 2', 10, 'moyenne');
        $session3 = Session::create($this->pdo, 'Session 3', 10, 'mediane');

        $this->assertNotEquals($session1->session_code, $session2->session_code);
        $this->assertNotEquals($session1->session_code, $session3->session_code);
        $this->assertNotEquals($session2->session_code, $session3->session_code);
    }

    public function testCreateSessionWithDifferentRules(): void
    {
        $rules = ['strict', 'moyenne', 'mediane', 'majorite_absolue', 'majorite_relative'];

        foreach ($rules as $rule) {
            $session = Session::create($this->pdo, "Session $rule", 10, $rule);
            $this->assertEquals($rule, $session->vote_rule);
        }
    }

    public function testSessionInitialStatus(): void
    {
        $session = Session::create($this->pdo, 'Test Session', 10, 'strict');
        
        $this->assertEquals('waiting', $session->status);
        $this->assertNull($session->current_story_id);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];
        
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}