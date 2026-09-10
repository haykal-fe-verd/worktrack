<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_the_admin_role(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('worktrack:create-admin')
            ->expectsQuestion('Nama admin', 'Budi Admin')
            ->expectsQuestion('Email admin', 'budi@worktrack.test')
            ->expectsQuestion('Password admin', 'password123')
            ->assertExitCode(0);

        $user = User::where('email', 'budi@worktrack.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->create(['email' => 'existing@worktrack.test']);

        $this->artisan('worktrack:create-admin')
            ->expectsQuestion('Nama admin', 'Budi Admin')
            ->expectsQuestion('Email admin', 'existing@worktrack.test')
            ->expectsQuestion('Password admin', 'password123')
            ->assertExitCode(1);
    }
}
