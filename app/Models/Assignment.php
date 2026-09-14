<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'job_period_id',
    'tanggal_mulai',
    'tanggal_selesai',
    'status',
    'is_current',
    'previous_assignment_id',
    'tarif_jual',
    'tarif_bayar',
    'created_by',
])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'is_current' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tarif_jual' => 'decimal:2',
            'tarif_bayar' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<JobPeriod, $this>
     */
    public function jobPeriod(): BelongsTo
    {
        return $this->belongsTo(JobPeriod::class);
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function previousAssignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'previous_assignment_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
