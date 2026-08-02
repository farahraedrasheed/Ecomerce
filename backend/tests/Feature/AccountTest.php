<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_the_account_endpoint(): void
    {
        $response = $this->getJson('/api/account');

        $response->assertStatus(401);
    }

    public function test_a_user_can_view_their_own_account_info(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $response = $this->actingAs($user)->getJson('/api/account');

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Jane Doe')
            ->assertJsonPath('email', 'jane@example.com')
            ->assertJsonPath('card_last_four', null);
    }

    public function test_a_user_can_save_a_valid_card(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/account/card', [
            'card_name' => 'Jane Doe',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '12/30',
            'card_cvv' => '123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('card_last_four', '4242')
            ->assertJsonPath('card_brand', 'Visa')
            ->assertJsonPath('card_holder_name', 'Jane Doe');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'card_last_four' => '4242']);
    }

    public function test_saving_an_invalid_card_number_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/account/card', [
            'card_name' => 'Jane Doe',
            'card_number' => '1234 5678 9012 3456',
            'card_expiry' => '12/30',
            'card_cvv' => '123',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'card_last_four' => null]);
    }

    public function test_saving_an_expired_card_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/account/card', [
            'card_name' => 'Jane Doe',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '01/20',
            'card_cvv' => '123',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_user_can_remove_their_saved_card(): void
    {
        $user = User::factory()->create([
            'card_holder_name' => 'Jane Doe',
            'card_last_four' => '4242',
            'card_brand' => 'Visa',
            'card_expiry' => '12/30',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/account/card');

        $response->assertStatus(204);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'card_last_four' => null]);
    }
}
