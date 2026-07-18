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
    Schema::create('materis', function (Blueprint $table) {
        $table->id();
        $table->foreignId('mata_kuliah_id')->constrained('mata_kuliahs')->onDelete('cascade');
        $table->integer('pertemuan_ke')->nullable();
        $table->text('judul');
        $table->longText('konten'); // longText karena deskripsi materi bisa sangat panjang
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materis');
    }
};
