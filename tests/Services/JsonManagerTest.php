<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\JsonManager;
use App\Models\Session;
use App\Models\UserStory;
use PDO;

class JsonManagerTest extends TestCase
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

    public function testImportBacklog(): void
    {
        $json = json_encode([
            'stories' => [
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
                    'priorite' => 'moyenne'
                ]
            ]
        ]);

        $count = JsonManager::importBacklog($this->pdo, $this->session->id, $json);

        $this->assertEquals(2, $count);
        
        $stories = UserStory::findBySession($this->pdo, $this->session->id);
        $this->assertCount(2, $stories);
        $this->assertEquals('US-001', $stories[0]->story_id);
        $this->assertEquals('Story 1', $stories[0]->title);
    }

    public function testImportBacklogInvalidJson(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON invalide');
        
        JsonManager::importBacklog($this->pdo, $this->session->id, 'invalid json');
    }

    public function testImportBacklogMissingStoriesKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clé "stories" manquante');
        
        $json = json_encode(['data' => []]);
        JsonManager::importBacklog($this->pdo, $this->session->id, $json);
    }

    public function testExportBacklog(): void
    {
        // Import some stories first
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => 'Desc 1', 'priorite' => 'haute'],
            ['id' => 'US-002', 'titre' => 'Story 2', 'description' => 'Desc 2', 'priorite' => 'basse']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        
        // Set estimation on first story
        $story1 = UserStory::findCurrent($this->pdo, $this->session->id);
        $story1->setEstimation($this->pdo, 5);

        $json = JsonManager::exportBacklog($this->pdo, $this->session->id);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertArrayHasKey('session_name', $data);
        $this->assertArrayHasKey('session_code', $data);
        $this->assertArrayHasKey('vote_rule', $data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('stories', $data);
        
        $this->assertEquals($this->session->id, $data['session_id']);
        $this->assertEquals('Test Session', $data['session_name']);
        $this->assertCount(2, $data['stories']);
        
        // Check first story
        $this->assertEquals('US-001', $data['stories'][0]['id']);
        $this->assertEquals('Story 1', $data['stories'][0]['titre']);
        $this->assertEquals(5, $data['stories'][0]['estimation']);
        $this->assertEquals('estimated', $data['stories'][0]['status']);
        
        // Check second story
        $this->assertEquals('US-002', $data['stories'][1]['id']);
        $this->assertNull($data['stories'][1]['estimation']);
    }

    public function testSaveSession(): void
    {
        // Import stories
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => 'Desc 1', 'priorite' => 'haute'],
            ['id' => 'US-002', 'titre' => 'Story 2', 'description' => 'Desc 2', 'priorite' => 'moyenne']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);
        
        // Set current story
        $story = UserStory::findCurrent($this->pdo, $this->session->id);
        $this->session->setCurrentStory($this->pdo, $story->id);
        $this->session->setStatus($this->pdo, 'voting');

        $json = JsonManager::saveSession($this->pdo, $this->session->id);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('session', $data);
        $this->assertArrayHasKey('stories', $data);
        $this->assertArrayHasKey('saved_at', $data);
        
        // Check session data
        $this->assertEquals($this->session->id, $data['session']['id']);
        $this->assertEquals('Test Session', $data['session']['name']);
        $this->assertEquals($this->session->session_code, $data['session']['code']);
        $this->assertEquals('strict', $data['session']['vote_rule']);
        $this->assertEquals('voting', $data['session']['status']);
        $this->assertEquals($story->id, $data['session']['current_story_id']);
        
        // Check stories
        $this->assertCount(2, $data['stories']);
        $this->assertArrayHasKey('order_index', $data['stories'][0]);
    }

    public function testSaveSessionInDatabase(): void
    {
        // Import a story
        $stories = [
            ['id' => 'US-001', 'titre' => 'Story 1', 'description' => 'Desc 1', 'priorite' => 'haute']
        ];
        UserStory::importJson($this->pdo, $this->session->id, $stories);

        // Save session
        JsonManager::saveSession($this->pdo, $this->session->id);

        // Check if save was stored in database
        $stmt = $this->pdo->prepare("SELECT * FROM session_saves WHERE session_id = :sid ORDER BY saved_at DESC LIMIT 1");
        $stmt->execute([':sid' => $this->session->id]);
        $save = $stmt->fetch();

        $this->assertNotNull($save);
        $this->assertEquals($this->session->id, $save['session_id']);
        $this->assertNotEmpty($save['save_data']);
        
        // Verify JSON is valid
        $data = json_decode($save['save_data'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('session', $data);
        $this->assertArrayHasKey('stories', $data);
    }

    public function testExportBacklogWithNoStories(): void
    {
        $json = JsonManager::exportBacklog($this->pdo, $this->session->id);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('stories', $data);
        $this->assertEmpty($data['stories']);
    }

    public function testImportBacklogReplacesExisting(): void
    {
        // Import first batch
        $json1 = json_encode([
            'stories' => [
                ['id' => 'US-001', 'titre' => 'Story 1', 'description' => '', 'priorite' => 'haute']
            ]
        ]);
        JsonManager::importBacklog($this->pdo, $this->session->id, $json1);
        
        $this->assertCount(1, UserStory::findBySession($this->pdo, $this->session->id));

        // Import second batch (should replace)
        $json2 = json_encode([
            'stories' => [
                ['id' => 'US-002', 'titre' => 'Story 2', 'description' => '', 'priorite' => 'basse'],
                ['id' => 'US-003', 'titre' => 'Story 3', 'description' => '', 'priorite' => 'moyenne']
            ]
        ]);
        JsonManager::importBacklog($this->pdo, $this->session->id, $json2);

        $stories = UserStory::findBySession($this->pdo, $this->session->id);
        $this->assertCount(2, $stories);
        $this->assertEquals('US-002', $stories[0]->story_id);
        $this->assertEquals('US-003', $stories[1]->story_id);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM votes");
        $this->pdo->exec("DELETE FROM user_stories");
        $this->pdo->exec("DELETE FROM session_saves");
        $this->pdo->exec("DELETE FROM players");
        $this->pdo->exec("DELETE FROM sessions");
    }
}