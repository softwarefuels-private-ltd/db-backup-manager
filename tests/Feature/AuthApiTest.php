<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_check_setup_status_when_no_users_exist(): void
    {
        $response = $this->getJson('/api/v1/auth/setup-status');

        $response->assertStatus(200)
            ->assertJson(['needs_setup' => true]);
    }

    public function test_first_user_can_register_as_admin(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'tech@internal.local',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'tech@internal.local',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'tech@internal.local']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'tech@internal.local',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(422);
    }
}
