<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\JobPeriodStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPeriod>
 */
class JobPeriodFactory extends Factory
{
    protected $model = JobPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'jenis_dokumen' => DocumentType::PR,
            'no_dokumen' => fake()->unique()->numerify('#####'),
            'kode_po' => fake()->bothify('PTC##?'),
            'nilai_po' => fake()->randomFloat(2, 1000000, 100000000),
            'tanggal_mulai' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'tanggal_selesai' => null,
            'jumlah_tk_rencana' => fake()->numberBetween(1, 30),
            'status' => JobPeriodStatus::Aktif,
        ];
    }

    public function berakhir(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPeriodStatus::Berakhir,
        ]);
    }
}
