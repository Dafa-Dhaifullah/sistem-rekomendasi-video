<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialVideoRecommendation extends Model
{
    use HasFactory;

    /**
     * Menentukan nama tabel secara eksplisit.
     *
     * @var string
     */
    protected $table = 'material_video_recommendations';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     *
     * @var array
     */
    protected $fillable = [
        'materi_id',
        'youtube_video_id',
        'similarity_score',
        'ranking',
    ];

    /**
     * Relasi ke tabel materis.
     * Setiap rekomendasi dimiliki oleh satu materi spesifik.
     */
    public function materi()
    {
        return $this->belongsTo(Materi::class, 'materi_id', 'id');
    }

    /**
     * Relasi ke tabel youtube_video_caches.
     * Mengambil detail video dari tabel cache.
     */
    public function youtubeVideoCache()
    {
        // Dikarenakan foreign key bernama 'youtube_video_id' merujuk pada kolom 'id' di tabel youtube_video_caches,
        // kita perlu mendefinisikannya secara eksplisit agar Eloquent tidak keliru.
        return $this->belongsTo(YoutubeVideoCache::class, 'youtube_video_id', 'id');
    }
}