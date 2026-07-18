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
    Schema::create('material_video_recommendations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('materi_id')->constrained('materis')->onDelete('cascade');
        $table->foreignId('youtube_video_id')->constrained('youtube_video_caches')->onDelete('cascade');
        $table->decimal('similarity_score', 5, 4); // Menyimpan nilai 0.xxxx
        $table->integer('ranking'); // Untuk menandai Top 1 sampai Top 5
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_video_recommendations');
    }
};
