<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'nik', 'alamat', 'no_rekening', 'nama_bank', 'status'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->nik !== null && $employee->isDirty('nik')) {
                $employee->nik_hash = self::hashNik($employee->nik);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'nik' => 'encrypted',
            'no_rekening' => 'encrypted',
        ];
    }

    /**
     * Deterministic HMAC-SHA256 "blind index" for an employee's NIK.
     * This is the ONLY place that computes this hash — every exact-match
     * query against an encrypted `nik` must go through this method rather
     * than re-implementing the hash inline.
     */
    public static function hashNik(string $nik): string
    {
        return hash_hmac('sha256', $nik, (string) config('app.hash_key'));
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
