<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    
    protected $fillable = [
        'mata_kuliah_id',
        'pertemuan_ke',
        'judul',
        'konten',
        'konten_bersih',
    ];

    public function recommendations()
{
    return $this->hasMany(MaterialVideoRecommendation::class, 'materi_id', 'id')
                ->orderBy('ranking', 'asc');
}
}