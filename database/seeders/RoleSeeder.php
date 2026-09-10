<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's fixed roles.
     */
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
