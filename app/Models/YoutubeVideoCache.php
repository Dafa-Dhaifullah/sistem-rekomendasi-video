<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YoutubeVideoCache extends Model
{
    use HasFactory;

    /**
     * Menentukan nama tabel secara eksplisit.
     *
     * @var string
     */
    protected $table = 'youtube_video_caches';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     *
     * @var array
     */
    protected $fillable = [
        'youtube_video_id',
        'judul_video',
        'deskripsi_video',
        'thumbnail_url',
    ];

    /**
     * Relasi ke tabel material_video_recommendations.
     * Satu video cache dapat menjadi rekomendasi untuk banyak materi.
     */
    public function recommendations()
    {
        // Parameter kedua adalah foreign_key di tabel anak, 
        // parameter ketiga adalah local_key di tabel ini.
        return $this->hasMany(MaterialVideoRecommendation::class, 'youtube_video_id', 'id');
    }
}