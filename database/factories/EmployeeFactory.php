<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'nik' => fake()->unique()->numerify('################'),
            'alamat' => fake()->address(),
            'no_rekening' => fake()->unique()->numerify('##########'),
            'nama_bank' => fake()->randomElement(['BCA', 'BRI', 'Mandiri', 'BNI']),
            'status' => EmployeeStatus::Aktif,
        ];
    }

    public function nonAktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::NonAktif,
        ]);
    }
}
