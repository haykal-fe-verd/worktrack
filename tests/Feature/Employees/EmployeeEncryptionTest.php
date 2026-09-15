<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_nik_is_stored_encrypted_and_reads_back_as_plaintext(): void
    {
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertNotSame('3513126804000099', $raw->nik);
        $this->assertSame('3513126804000099', Crypt::decryptString($raw->nik));
        $this->assertSame('3513126804000099', $employee->fresh()->nik);
    }

    public function test_no_rekening_is_stored_encrypted_and_reads_back_as_plaintext(): void
    {
        $employee = Employee::factory()->create(['no_rekening' => '1923973699']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertNotSame('1923973699', $raw->no_rekening);
        $this->assertSame('1923973699', Crypt::decryptString($raw->no_rekening));
        $this->assertSame('1923973699', $employee->fresh()->no_rekening);
    }

    public function test_nik_hash_is_computed_automatically_on_create(): void
    {
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertSame(Employee::hashNik('3513126804000099'), $raw->nik_hash);
    }

    public function test_nik_hash_is_recomputed_when_nik_changes_on_update(): void
    {
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $employee->update(['nik' => '1111111111111111']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertSame(Employee::hashNik('1111111111111111'), $raw->nik_hash);
        $this->assertNotSame(Employee::hashNik('3513126804000099'), $raw->nik_hash);
    }

    public function test_hash_nik_is_deterministic(): void
    {
        $this->assertSame(
            Employee::hashNik('3513126804000099'),
            Employee::hashNik('3513126804000099'),
        );
    }
}
