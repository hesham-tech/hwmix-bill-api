<?php
// كلاس تهيئة بيانات Module المخزون والوحدات
namespace Modules\Inventory\database\seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\SystemUnitsSeeder;

class InventoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SystemUnitsSeeder::class,
        ]);
    }
}
