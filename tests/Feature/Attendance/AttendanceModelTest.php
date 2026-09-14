<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Assignment;
use App\Models\Attendance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_factory_creates_a_valid_row_with_correct_casts(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertInstanceOf(Carbon::class, $attendance->tanggal);
    }

    public function test_assignment_has_many_attendances(): void
    {
        $assignment = Assignment::factory()->create();
        Attendance::factory()->count(2)->create(['assignment_id' => $assignment->id]);

        $this->assertCount(2, $assignment->attendances);
    }

    public function test_assignment_id_and_tanggal_are_unique_together(): void
    {
        $assignment = Assignment::factory()->create();
        Attendance::factory()->create(['assignment_id' => $assignment->id, 'tanggal' => '2025-09-01']);

        $this->expectException(QueryException::class);

        Attendance::factory()->create(['assignment_id' => $assignment->id, 'tanggal' => '2025-09-01']);
    }
}
