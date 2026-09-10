<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_three_fixed_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertTrue(Role::where('name', 'admin')->exists());
        $this->assertTrue(Role::where('name', 'staff_input')->exists());
        $this->assertTrue(Role::where('name', 'viewer')->exists());
        $this->assertSame(3, Role::count());
    }

    public function test_role_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(3, Role::count());
    }
}
