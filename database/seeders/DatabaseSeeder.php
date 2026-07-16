<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            YayasanSeeder::class,
            JabatanTambahanMasterSeeder::class,
            DemoDataSeeder::class,
            M3DemoDataSeeder::class,
            // EssentialUserSeeder runs last for now because it needs at least one Lembaga to
            // exist for 4 of its 5 accounts -- DemoDataSeeder is what creates Lembaga rows
            // today. Once a dedicated LembagaSeeder exists (seeder-architecture-cleanup
            // sub-project 2), move this line to run right after it instead.
            EssentialUserSeeder::class,
        ]);
    }
}
