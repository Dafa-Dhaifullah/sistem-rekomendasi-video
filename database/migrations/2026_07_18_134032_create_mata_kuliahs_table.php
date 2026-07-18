<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('mata_kuliahs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('prodi_id')->constrained('prodis')->onDelete('cascade');
        $table->string('kode_mata_kuliah', 20);
        $table->string('nama_mata_kuliah')->nullable();
        $table->string('kelas', 5); // misal: A, B, C
        $table->string('tahun_akademik', 10); // misal: 20252
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mata_kuliahs');
    }
};
