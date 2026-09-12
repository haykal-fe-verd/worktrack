<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_pekerjaan' => fake()->words(3, true),
            'lokasi' => fake()->city(),
            'klien' => 'PLN '.fake()->randomElement(['Unit A', 'Unit B', 'Unit C']),
            'status' => JobStatus::Aktif,
        ];
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Selesai,
        ]);
    }
}
