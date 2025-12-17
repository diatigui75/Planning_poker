<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Session;
use App\Models\UserStory;
use PDO;

class UserStoryTest extends TestCase
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
        
        // Create test session
        $this->session = Session::create($this->pdo, 'Test Session', 10, 'strict');
    }

    public function testImportJson(): void
    {
        $stories = [
            [
                'id' => 'US-001',
                'titre' => 'Story 1',
                'description' => 'Description 1',
                'priorite' => 'haute'
            ],
            [
                'id' => 'US-002',
                'titre' => 'Story 2',
                'description' => 'Description 2',
                'priorite' => 'basse'
            ]
        ];

        UserStory::importJson($this->pdo, $this->session->id, $stories);
        $imported = UserStory::findBySession($this->pdo, $this->session->id);

        $this->assertCount(2, $imported);
        $this->assertEquals('US-001', $imported[0]->story_id);
        $this->assertEquals('Story 1', $imported[0]->title);
        $this->assertEquals('haute', $imported[0]->priority);
        $this->assertEquals('pending', $imported[0]->status);
    }

    public function testFindCurrent(): void
    {
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => '', 'priorite' => 'moyenne'],
            ['id' => 'US-002', 'titre' => 'Story 2', 'description' => '', 'priorite' => 'moyenne']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);

        $current = UserStory::findCurrent($this->pdo, $this->session->id);

        $this->assertNotNull($current);
        $this->assertEquals('US-001', $current->story_id);
    }

    public function testSetEstimation(): void
    {
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => '', 'priorite' => 'moyenne']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        
        $story = UserStory::findCurrent($this->pdo, $this->session->id);
        $story->setEstimation($this->pdo, 5);

        $updated = UserStory::findById($this->pdo, $story->id);

        $this->assertEquals(5, $updated->estimation);
        $this->assertEquals('estimated', $updated->status);
    }

    public function testSetStatus(): void
    {
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => '', 'priorite' => 'moyenne']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        
        $story = UserStory::findCurrent($this->pdo, $this->session->id);
        $story->setStatus($this->pdo, 'voting');

        $updated = UserStory::findById($this->pdo, $story->id);

        $this->assertEquals('voting', $updated->status);
    }

    public function testGetStats(): void
    {
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => '', 'priorite' => 'moyenne'],
            ['id' => 'US-002', 'titre' => 'Story 2', 'description' => '', 'priorite' => 'moyenne'],
            ['id' => 'US-003', 'titre' => 'Story 3', 'description' => '', 'priorite' => 'moyenne']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        
        // Set estimation on one story
        $allStories = UserStory::findBySession($this->pdo, $this->session->id);
        $allStories[0]->setEstimation($this->pdo, 5);
        $allStories[1]->setStatus($this->pdo, 'voting');

        $stats = UserStory::getStats($this->pdo, $this->session->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['estimated']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(1, $stats['voting']);
    }

    public function testFindBySession(): void
    {
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => '', 'priorite' => 'haute'],
            ['id' => 'US-002', 'titre' => 'Story 2', 'description' => '', 'priorite' => 'basse']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);

        $found = UserStory::findBySession($this->pdo, $this->session->id);

        $this->assertCount(2, $found);
        $this->assertEquals('US-001', $found[0]->story_id);
        $this->assertEquals('US-002', $found[1]->story_id);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 1,
            'session_id' => 99,
            'story_id' => 'US-999',
            'title' => 'Test Story',
            'description' => 'Test Description',
            'priority' => 'haute',
            'estimation' => 8,
            'status' => 'estimated',
            'order_index' => 0
        ];

        $story = UserStory::fromArray($data);

        $this->assertEquals(1, $story->id);
        $this->assertEquals(99, $story->session_id);
        $this->assertEquals('US-999', $story->story_id);
        $this->assertEquals('Test Story', $story->title);
        $this->assertEquals('haute', $story->priority);
        $this->assertEquals(8, $story->estimation);
        $this->assertEquals('estimated', $story->status);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}