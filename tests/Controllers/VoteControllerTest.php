<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\VoteController;
use App\Models\Session;
use App\Models\Player;
use App\Models\UserStory;
use App\Models\Vote;
use PDO;

class VoteControllerTest extends TestCase
{
    private PDO $pdo;
    private Session $session;
    private Player $scrumMaster;
    private Player $player1;
    private Player $player2;
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
        $this->scrumMaster = Player::create($this->pdo, $this->session->id, 'Scrum Master', true);
        $this->player1 = Player::create($this->pdo, $this->session->id, 'Player 1', false);
        $this->player2 = Player::create($this->pdo, $this->session->id, 'Player 2', false);
        
        $stories = [
            ['id' => 'US-001', 'titre' => 'Test Story', 'description' => 'Description', 'priorite' => 'haute']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        $this->story = UserStory::findCurrent($this->pdo, $this->session->id);
        
        $this->session->setCurrentStory($this->pdo, $this->story->id);
        $this->session->setStatus($this->pdo, 'voting');
    }

    public function testSubmitVote(): void
    {
        $result = VoteController::submitVote($this->pdo, $this->session->id, $this->player1->id, '5');

        $this->assertTrue($result['success']);
        $this->assertEquals('Vote enregistré', $result['message']);
        
        $hasVoted = Vote::hasPlayerVoted($this->pdo, $this->session->id, $this->story->id, $this->player1->id, 1);
        $this->assertTrue($hasVoted);
    }

    public function testSubmitVoteWithNoStory(): void
    {
        // Delete all stories to ensure there's no current story
        $this->pdo->exec("DELETE FROM user_stories WHERE session_id = " . $this->session->id);
        
        $result = VoteController::submitVote($this->pdo, $this->session->id, $this->player1->id, '5');

        $this->assertFalse($result['success']);
        $this->assertEquals('Aucune story en cours', $result['error']);
    }

    public function testSubmitVoteDuringCoffeeBreak(): void
    {
        $this->session->setStatus($this->pdo, 'coffee_break');
        
        $result = VoteController::submitVote($this->pdo, $this->session->id, $this->player1->id, '5');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pause café', $result['error']);
    }

    public function testRevealVotesWithUnanimity(): void
    {
        // All players vote the same
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player1->id, '5', 1);
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player2->id, '5', 1);

        $data = VoteController::reveal($this->pdo, $this->session->id);

        $this->assertNotNull($data['story']);
        $this->assertCount(2, $data['votes']);
        $this->assertTrue($data['result']['valid']);
        $this->assertEquals('5', $data['result']['value']);
        $this->assertEquals('Unanimité', $data['result']['reason']);
    }

    public function testRevealVotesWithCoffeeBreak(): void
    {
        // All players vote for coffee
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player1->id, 'cafe', 1);
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player2->id, 'cafe', 1);

        $data = VoteController::reveal($this->pdo, $this->session->id);

        $this->assertTrue($data['result']['valid']);
        $this->assertTrue($data['result']['coffee_break']);
        $this->assertEquals('cafe', $data['result']['value']);
    }

    public function testRevealVotesWithDisagreement(): void
    {
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player1->id, '3', 1);
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player2->id, '8', 1);

        $data = VoteController::reveal($this->pdo, $this->session->id);

        $this->assertFalse($data['result']['valid']);
        $this->assertEquals('Pas d\'unanimité', $data['result']['reason']);
    }

    public function testRevote(): void
    {
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player1->id, '5', 1);
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player2->id, '8', 1);

        $result = VoteController::revote($this->pdo, $this->session->id);

        $this->assertTrue($result['success']);
        
        // Votes should be cleared
        $count = Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1);
        $this->assertEquals(0, $count);
        
        // Status should be back to voting
        $session = Session::findById($this->pdo, $this->session->id);
        $this->assertEquals('voting', $session->status);
    }

    public function testValidateEstimation(): void
    {
        $result = VoteController::validateEstimation($this->pdo, $this->session->id, $this->story->id, 5);

        $this->assertTrue($result['success']);
        
        // Story should be estimated
        $story = UserStory::findById($this->pdo, $this->story->id);
        $this->assertEquals(5, $story->estimation);
        $this->assertEquals('estimated', $story->status);
    }

    public function testValidateEstimationMovesToNextStory(): void
    {
        // Add a second story
        $stmt = $this->pdo->prepare("
            INSERT INTO user_stories (session_id, story_id, title, description, priority, order_index, status)
            VALUES (:sid, 'US-002', 'Story 2', 'Desc', 'moyenne', 1, 'pending')
        ");
        $stmt->execute([':sid' => $this->session->id]);

        $result = VoteController::validateEstimation($this->pdo, $this->session->id, $this->story->id, 5);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['has_next']);
        
        // Should move to next story
        $session = Session::findById($this->pdo, $this->session->id);
        $this->assertNotNull($session->current_story_id);
        $this->assertNotEquals($this->story->id, $session->current_story_id);
    }

    public function testValidateEstimationWithNoMoreStories(): void
    {
        $result = VoteController::validateEstimation($this->pdo, $this->session->id, $this->story->id, 5);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['has_next']);
        
        // Session should be finished
        $session = Session::findById($this->pdo, $this->session->id);
        $this->assertEquals('finished', $session->status);
        $this->assertNull($session->current_story_id);
    }

    public function testValidateCoffeeBreak(): void
    {
        $result = VoteController::validateCoffeeBreak($this->pdo, $this->session->id, $this->story->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['coffee_break_active']);
        
        // Session should be in coffee_break status
        $session = Session::findById($this->pdo, $this->session->id);
        $this->assertEquals('coffee_break', $session->status);
    }

    public function testResumeFromCoffeeBreak(): void
    {
        // Set coffee break
        $this->session->setStatus($this->pdo, 'coffee_break');
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player1->id, 'cafe', 1);

        $result = VoteController::resumeFromCoffeeBreak($this->pdo, $this->session->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['same_story']);
        
        // Votes should be cleared
        $count = Vote::getVoteCount($this->pdo, $this->session->id, $this->story->id, 1);
        $this->assertEquals(0, $count);
        
        // Status should be voting
        $session = Session::findById($this->pdo, $this->session->id);
        $this->assertEquals('voting', $session->status);
    }

    public function testGetSessionState(): void
    {
        Vote::addVote($this->pdo, $this->session->id, $this->story->id, $this->player1->id, '5', 1);

        $state = VoteController::getSessionState($this->pdo, $this->session->id, $this->player1->id);

        $this->assertTrue($state['success']);
        $this->assertArrayHasKey('session', $state);
        $this->assertArrayHasKey('story', $state);
        $this->assertArrayHasKey('players', $state);
        $this->assertArrayHasKey('vote_info', $state);
        $this->assertArrayHasKey('stats', $state);
        
        $this->assertEquals($this->session->id, $state['session']['id']);
        $this->assertEquals($this->story->id, $state['story']['id']);
        $this->assertCount(3, $state['players']); // SM + 2 players
        $this->assertEquals(1, $state['vote_info']['votes_count']);
        $this->assertTrue($state['vote_info']['has_voted']);
    }

    public function testStartVoting(): void
    {
        // Reset status
        $this->session->setStatus($this->pdo, 'waiting');
        $this->story->setStatus($this->pdo, 'pending');

        $result = VoteController::startVoting($this->pdo, $this->session->id, $this->story->id);

        $this->assertTrue($result['success']);
        
        $session = Session::findById($this->pdo, $this->session->id);
        $story = UserStory::findById($this->pdo, $this->story->id);
        
        $this->assertEquals('voting', $session->status);
        $this->assertEquals('voting', $story->status);
    }

    public function testStartVotingWithInvalidStory(): void
    {
        $result = VoteController::startVoting($this->pdo, $this->session->id, 99999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Story introuvable', $result['error']);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}