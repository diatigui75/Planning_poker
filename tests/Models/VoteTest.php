<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Session;
use App\Models\Player;
use App\Models\UserStory;
use App\Models\Vote;
use PDO;

class VoteTest extends TestCase
{
    private PDO $pdo;
    private Session $session;
    private Player $player;
    private UserStory $story;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->pdo = getPDO();
        
        // Clean database
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
        
        // Create test data
        $this->session = Session::create($this->pdo, 'Test Session', 10, 'strict');
        $this->player = Player::create($this->pdo, $this->session->id, 'Test Player', false);
        
        $stories = [
            ['id' => 'US-001', 'titre' => 'Test Story', 'description' => '', 'priorite' => 'moyenne']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        $this->story = UserStory::findCurrent($this->pdo, $this->session->id);
    }

    public function testAddVote(): void
    {
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);

        $hasVoted = Vote::hasPlayerVoted($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1);
        
        $this->assertTrue($hasVoted);
    }

    public function testUpdateVote(): void
    {
        // Add initial vote
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);
        
        // Update vote
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '8', 1);
        
        $vote = Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1);
        
        $this->assertEquals('8', $vote);
    }

    public function testGetVotes(): void
    {
        // Create players with names that will sort alphabetically
        // The query orders by: is_scrum_master DESC, pseudo ASC
        $player2 = Player::create($this->pdo, $this->session->id, 'Player A', false); // Will be first alphabetically
        $player3 = Player::create($this->pdo, $this->session->id, 'Player B', false); // Will be second

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);  // "Test Player"
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $player2->id, '8', 1);       // "Player A" 
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $player3->id, '5', 1);       // "Player B"

        $votes = Vote::getVotes($this->pdo, $this->session->id, $this->story->id, 1);

        $this->assertCount(3, $votes);
        // First vote should be from "Player A" (alphabetically first) who voted '8'
        $this->assertEquals('8', $votes[0]['vote_value']);
        $this->assertEquals('Player A', $votes[0]['pseudo']);
    }

    public function testClearVotes(): void
    {
        $player2 = Player::create($this->pdo, $this->session->id, 'Player 2', false);

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $player2->id, '8', 1);

        $count = Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1);
        $this->assertEquals(2, $count);

        Vote::clearVotes($this->pdo, $this->session->id, $this->story->id, 1);

        $countAfter = Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1);
        $this->assertEquals(0, $countAfter);
    }

    public function testGetVoteCount(): void
    {
        $player2 = Player::create($this->pdo, $this->session->id, 'Player 2', false);
        $player3 = Player::create($this->pdo, $this->session->id, 'Player 3', false);

        $this->assertEquals(0, Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1));

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);
        $this->assertEquals(1, Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1));

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $player2->id, '8', 1);
        $this->assertEquals(2, Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1));

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $player3->id, '5', 1);
        $this->assertEquals(3, Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1));
    }

    public function testHasPlayerVoted(): void
    {
        $player2 = Player::create($this->pdo, $this->session->id, 'Player 2', false);

        $this->assertFalse(Vote::hasPlayerVoted($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));
        $this->assertFalse(Vote::hasPlayerVoted($this->pdo, $this->session->id, $this->story->id, $player2->id, 1));

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);

        $this->assertTrue(Vote::hasPlayerVoted($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));
        $this->assertFalse(Vote::hasPlayerVoted($this->pdo, $this->session->id, $this->story->id, $player2->id, 1));
    }

    public function testGetPlayerVote(): void
    {
        $this->assertNull(Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));

        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '13', 1);

        $vote = Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1);
        $this->assertEquals('13', $vote);
    }

    public function testMultipleRounds(): void
    {
        // Round 1
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '5', 1);
        $this->assertEquals('5', Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));

        // Round 2 (after revote)
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '8', 2);
        
        // Round 1 vote should still exist
        $this->assertEquals('5', Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));
        // Round 2 vote should exist
        $this->assertEquals('8', Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 2));
    }

    public function testSpecialVotes(): void
    {
        // Test vote "?"
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, '?', 1);
        $this->assertEquals('?', Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));

        // Test vote "cafe"
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 'cafe', 1);
        $this->assertEquals('cafe', Vote::getPlayerVote($this->pdo, $this->session->id, $this->story->id, $this->player->id, 1));
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}