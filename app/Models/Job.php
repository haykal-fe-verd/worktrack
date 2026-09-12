<?php

namespace App\Models;

use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nama_pekerjaan', 'lokasi', 'klien', 'status'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    protected $table = 'client_jobs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
        ];
    }

    /**
     * @return HasMany<JobPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(JobPeriod::class);
    }

    /**
     * @return HasOne<JobPeriod, $this>
     */
    public function activePeriod(): HasOne
    {
        return $this->hasOne(JobPeriod::class)->where('status', JobPeriodStatus::Aktif);
    }
}
