<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptEmployeeSensitiveData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:encrypt-sensitive-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt any employees.nik/no_rekening values still stored as plaintext, and backfill nik_hash. Safe to run more than once.';

    public function handle(): int
    {
        $encrypted = 0;
        $skipped = 0;

        DB::table('employees')
            ->select(['id', 'nik', 'no_rekening'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$encrypted, &$skipped) {
                foreach ($rows as $row) {
                    $nikPlain = $this->plaintextIfNotYetEncrypted($row->nik);
                    $rekeningPlain = $this->plaintextIfNotYetEncrypted($row->no_rekening);

                    if ($nikPlain === null && $rekeningPlain === null) {
                        $skipped++;

                        continue;
                    }

                    $updates = [];

                    if ($nikPlain !== null) {
                        $updates['nik'] = Crypt::encryptString($nikPlain);
                        $updates['nik_hash'] = Employee::hashNik($nikPlain);
                    }

                    if ($rekeningPlain !== null) {
                        $updates['no_rekening'] = Crypt::encryptString($rekeningPlain);
                    }

                    DB::table('employees')->where('id', $row->id)->update($updates);
                    $encrypted++;
                }
            });

        $this->info("Encrypted {$encrypted} row(s), skipped {$skipped} row(s) already encrypted.");

        $remaining = DB::table('employees')->whereNull('nik_hash')->count();

        if ($remaining > 0) {
            $this->warn("{$remaining} row(s) still have a NULL nik_hash — this should not happen after a full run. Investigate before ending maintenance mode.");
        } else {
            $this->info('All employees now have a nik_hash. Safe to end maintenance mode.');
        }

        return self::SUCCESS;
    }

    /**
     * Returns the plaintext value if `$value` is NOT yet a valid
     * Laravel-encrypted payload (i.e. it still needs encrypting), or null
     * if it decrypts successfully (already encrypted — nothing to do).
     */
    private function plaintextIfNotYetEncrypted(string $value): ?string
    {
        try {
            Crypt::decryptString($value);

            return null;
        } catch (DecryptException) {
            return $value;
        }
    }
}
