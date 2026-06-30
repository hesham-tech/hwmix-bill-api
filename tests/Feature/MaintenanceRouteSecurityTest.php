<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
    }

    public function test_fix_missing_default_cashboxes_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/fix-missing-default-cashboxes');

        $response->assertUnauthorized();
    }

    public function test_fix_missing_default_cashboxes_requires_super_admin_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/fix-missing-default-cashboxes');

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_fix_missing_default_cashboxes(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('admin.super');

        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/fix-missing-default-cashboxes');

        $response->assertOk();
    }

    public function test_artisan_routes_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/php/migrate');

        $response->assertUnauthorized();
    }

    public function test_artisan_routes_require_super_admin_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/php/migrate');

        $response->assertForbidden();
    }

    public function test_authenticated_users_can_read_health_check(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/artisan/health');

        $response->assertOk();
    }
}
