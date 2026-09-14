<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'tanggal' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'status' => AttendanceStatus::Hadir,
            'catatan' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
