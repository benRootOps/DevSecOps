<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithToken(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $user->createToken('t')->plainTextToken);
    }

    private function makeConversation(User $a, User $b): Conversation
    {
        $conv = Conversation::create(['type' => 'private']);
        $conv->participants()->attach([
            $a->id => ['role' => 'member', 'joined_at' => now()],
            $b->id => ['role' => 'member', 'joined_at' => now()],
        ]);
        return $conv;
    }

    public function test_user_can_send_message(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $conv  = $this->makeConversation($alice, $bob);

        $this->actingWithToken($alice)
             ->postJson("/api/conversations/{$conv->id}/messages", ['content' => 'Salut Bob !'])
             ->assertStatus(201)
             ->assertJsonFragment(['content' => 'Salut Bob !']);

        $this->assertDatabaseHas('messages', ['content' => 'Salut Bob !']);
    }

    public function test_user_can_list_messages(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $conv  = $this->makeConversation($alice, $bob);

        $conv->messages()->create(['user_id' => $alice->id, 'type' => 'text', 'content' => 'Hello!']);

        $this->actingWithToken($alice)
             ->getJson("/api/conversations/{$conv->id}/messages")
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'meta']);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $alice   = User::factory()->create();
        $bob     = User::factory()->create();
        $charlie = User::factory()->create();
        $conv    = $this->makeConversation($alice, $bob);

        $this->actingWithToken($charlie)
             ->postJson("/api/conversations/{$conv->id}/messages", ['content' => 'Je m\'invite !'])
             ->assertStatus(403);
    }

    public function test_user_can_delete_own_message(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $conv  = $this->makeConversation($alice, $bob);
        $msg   = $conv->messages()->create(['user_id' => $alice->id, 'type' => 'text', 'content' => 'À supprimer']);

        $this->actingWithToken($alice)
             ->deleteJson("/api/messages/{$msg->id}")
             ->assertStatus(200);

        $this->assertDatabaseHas('messages', ['id' => $msg->id, 'is_deleted' => true]);
    }

    public function test_user_cannot_delete_others_message(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $conv  = $this->makeConversation($alice, $bob);
        $msg   = $conv->messages()->create(['user_id' => $alice->id, 'type' => 'text', 'content' => 'Alice écrit']);

        $this->actingWithToken($bob)
             ->deleteJson("/api/messages/{$msg->id}")
             ->assertStatus(403);
    }

    public function test_user_can_react_to_message(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $conv  = $this->makeConversation($alice, $bob);
        $msg   = $conv->messages()->create(['user_id' => $alice->id, 'type' => 'text', 'content' => 'Hi']);

        $this->actingWithToken($bob)
             ->postJson("/api/messages/{$msg->id}/react", ['emoji' => '👍'])
             ->assertStatus(200);

        $this->assertDatabaseHas('message_reactions', ['message_id' => $msg->id, 'emoji' => '👍']);
    }
}
