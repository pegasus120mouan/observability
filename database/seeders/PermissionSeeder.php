<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $name => $description) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
