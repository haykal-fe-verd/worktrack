<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\JobPeriodStatus;
use Database\Factories\JobPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'job_id',
    'jenis_dokumen',
    'no_dokumen',
    'kode_po',
    'nilai_po',
    'tanggal_mulai',
    'tanggal_selesai',
    'jumlah_tk_rencana',
    'status',
    'previous_period_id',
    'keterangan',
])]
class JobPeriod extends Model
{
    /** @use HasFactory<JobPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_dokumen' => DocumentType::class,
            'status' => JobPeriodStatus::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'nilai_po' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<JobPeriod, $this>
     */
    public function previousPeriod(): BelongsTo
    {
        return $this->belongsTo(JobPeriod::class, 'previous_period_id');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
