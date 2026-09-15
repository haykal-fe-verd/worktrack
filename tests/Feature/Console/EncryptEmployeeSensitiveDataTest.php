<?php

namespace Tests\Feature\Console;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptEmployeeSensitiveDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insert a row directly via the query builder (bypassing Eloquent's
     * `encrypted` cast) to simulate legacy plaintext data that predates
     * this feature.
     */
    private function insertPlaintextEmployee(string $nik, string $noRekening): int
    {
        return DB::table('employees')->insertGetId([
            'nama' => 'Legacy Employee',
            'nik' => $nik,
            'alamat' => 'Jl. Lama No. 1',
            'no_rekening' => $noRekening,
            'nama_bank' => 'BCA',
            'status' => 'aktif',
            'nik_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_encrypts_plaintext_rows_and_backfills_nik_hash(): void
    {
        $id = $this->insertPlaintextEmployee('3513126804000099', '1923973699');

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $row = DB::table('employees')->where('id', $id)->first();

        $this->assertSame('3513126804000099', Crypt::decryptString($row->nik));
        $this->assertSame('1923973699', Crypt::decryptString($row->no_rekening));
        $this->assertSame(Employee::hashNik('3513126804000099'), $row->nik_hash);
    }

    public function test_it_is_idempotent_and_does_not_re_encrypt_already_encrypted_rows(): void
    {
        $id = $this->insertPlaintextEmployee('3513126804000099', '1923973699');

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $afterFirstRun = DB::table('employees')->where('id', $id)->first();

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $afterSecondRun = DB::table('employees')->where('id', $id)->first();

        $this->assertSame($afterFirstRun->nik, $afterSecondRun->nik);
        $this->assertSame($afterFirstRun->no_rekening, $afterSecondRun->no_rekening);
        $this->assertSame($afterFirstRun->nik_hash, $afterSecondRun->nik_hash);
    }

    public function test_it_reports_safe_to_end_maintenance_mode_when_all_rows_have_a_nik_hash(): void
    {
        $this->insertPlaintextEmployee('3513126804000099', '1923973699');

        $this->artisan('employees:encrypt-sensitive-data')
            ->expectsOutputToContain('Safe to end maintenance mode')
            ->assertExitCode(0);
    }

    public function test_it_leaves_already_encrypted_rows_untouched(): void
    {
        $employee = Employee::factory()->create(['nik' => '2222222222222222', 'no_rekening' => '5555555555']);
        $before = DB::table('employees')->where('id', $employee->id)->first();

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $after = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertSame($before->nik, $after->nik);
        $this->assertSame($before->no_rekening, $after->no_rekening);
        $this->assertSame($before->nik_hash, $after->nik_hash);
    }
}
