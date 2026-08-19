<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugPermsTest extends TestCase {
    use RefreshDatabase;
    public function test_perms() {
        $this->seed(AddPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->givePermissionTo(perm_key('owner_fund_transactions.create'));
        $this->assertTrue($u->hasPermissionTo(perm_key('owner_fund_transactions.create')));
        dump('Perm check passed');
    }
}
