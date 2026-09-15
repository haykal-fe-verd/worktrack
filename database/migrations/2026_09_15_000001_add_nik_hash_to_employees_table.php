<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['nik']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->text('nik')->change();
            $table->text('no_rekening')->change();
            $table->string('nik_hash', 64)->nullable()->unique()->after('nik');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Note: this rollback only restores the original column types/constraint
     * shape. It does NOT decrypt any data that was encrypted after this
     * migration ran — rolling back after real ciphertext exists in these
     * columns will leave that ciphertext sitting in a shorter/plain column,
     * which is a data-loss risk to be aware of, not something this
     * migration can safely prevent.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('nik_hash');
            $table->string('nik', 16)->change();
            $table->string('no_rekening', 50)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('nik');
        });
    }
};
