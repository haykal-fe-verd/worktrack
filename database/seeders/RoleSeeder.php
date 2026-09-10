<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's fixed roles.
     */
    public function run(): void
    {
        foreach (['admin', 'staff_input', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
