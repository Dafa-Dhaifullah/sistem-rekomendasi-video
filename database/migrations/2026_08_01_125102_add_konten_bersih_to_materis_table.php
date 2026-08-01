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
        Schema::table('materis', function (Blueprint $table) {
            // Menambahkan kolom konten_bersih bertipe longText, boleh kosong (nullable),
            // dan diposisikan tepat setelah kolom konten.
            $table->longText('konten_bersih')->nullable()->after('konten');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materis', function (Blueprint $table) {
            // Menghapus kolom jika sewaktu-waktu dilakukan rollback
            $table->dropColumn('konten_bersih');
        });
    }
};