<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $user->createToken('t')->plainTextToken);
    }

    public function test_user_can_create_private_conversation(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->auth($alice)
             ->postJson('/api/conversations', ['user_id' => $bob->id])
             ->assertStatus(201)
             ->assertJsonFragment(['type' => 'private']);

        $this->assertDatabaseHas('conversations', ['type' => 'private']);
    }

    public function test_duplicate_private_conversation_returns_existing(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->auth($alice)->postJson('/api/conversations', ['user_id' => $bob->id]);
        $r2 = $this->auth($alice)->postJson('/api/conversations', ['user_id' => $bob->id]);

        $r2->assertStatus(200); // Retourne l'existante
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_user_can_list_own_conversations(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $conv = Conversation::create(['type' => 'private']);
        $conv->participants()->attach([
            $alice->id => ['role' => 'member', 'joined_at' => now()],
            $bob->id   => ['role' => 'member', 'joined_at' => now()],
        ]);

        $this->auth($alice)
             ->getJson('/api/conversations')
             ->assertStatus(200)
             ->assertJsonStructure(['data']);
    }

    public function test_user_can_create_group(): void
    {
        $alice   = User::factory()->create();
        $bob     = User::factory()->create();
        $charlie = User::factory()->create();

        $this->auth($alice)
             ->postJson('/api/groups', [
                 'name'       => 'Mon Groupe',
                 'member_ids' => [$bob->id, $charlie->id],
             ])
             ->assertStatus(201)
             ->assertJsonFragment(['type' => 'group', 'name' => 'Mon Groupe']);
    }

    public function test_non_admin_cannot_add_members_to_group(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $dave  = User::factory()->create();

        $group = Conversation::create(['type' => 'group', 'name' => 'Groupe']);
        $group->participants()->attach([
            $alice->id => ['role' => 'admin',  'joined_at' => now()],
            $bob->id   => ['role' => 'member', 'joined_at' => now()],
        ]);

        $this->auth($bob)
             ->postJson("/api/groups/{$group->id}/members", ['user_ids' => [$dave->id]])
             ->assertStatus(403);
    }
}
