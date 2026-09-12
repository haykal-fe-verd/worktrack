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
        Schema::create('job_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('jenis_dokumen', 10);
            $table->string('no_dokumen', 50)->unique();
            $table->string('kode_po', 50)->nullable();
            $table->decimal('nilai_po', 15, 2);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->integer('jumlah_tk_rencana');
            $table->string('status', 20)->default('aktif');
            $table->foreignId('previous_period_id')->nullable()->constrained('job_periods')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_periods');
    }
};
