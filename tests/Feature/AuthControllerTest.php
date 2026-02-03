<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);
    }

    public function test_user_can_register()
    {
        $payload = [
            'nickname' => 'testuser',
            'full_name' => 'Test User',
            'username' => 'testuser123',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '0123456789',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'user',
                    'token'
                ],
                'message'
            ]);

        $this->assertDatabaseHas('users', ['phone' => '0123456789']);
    }

    public function test_user_can_login_with_phone()
    {
        $user = User::factory()->create([
            'phone' => '01011223344',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => '01011223344',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['token', 'user'],
                'message'
            ]);
    }

    public function test_user_can_login_with_email()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);
    }

    public function test_user_can_get_profile_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200);
    }

    public function test_user_can_check_login_status()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/auth/check');

        $response->assertStatus(200);
    }
}
