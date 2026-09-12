<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'job_period_id' => JobPeriod::factory(),
            'tanggal_mulai' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'tanggal_selesai' => null,
            'status' => AssignmentStatus::Aktif,
            'is_current' => true,
            'tarif_jual' => fake()->randomFloat(2, 100000, 5000000),
            'tarif_bayar' => fake()->randomFloat(2, 80000, 4000000),
            'created_by' => User::factory(),
        ];
    }

    public function diperbarui(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Diperbarui,
            'is_current' => false,
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Selesai,
        ]);
    }
}
