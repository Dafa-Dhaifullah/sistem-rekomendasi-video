<?php

namespace App\Http\Controllers;

use App\Models\MataKuliah;
use App\Models\Materi;
use App\Models\YoutubeVideoCache;
use App\Models\MaterialVideoRecommendation;
use App\Services\TfIdfService;
use App\Services\TextPreprocessingService;
use App\Services\CosineSimilarityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoRecommendationController extends Controller
{
    protected $tfidfService;
    protected $nlpService;
    protected $cosineService;

    /**
     * Menginjeksi seluruh layanan (services) yang dibutuhkan.
     */
    public function __construct(
        TfIdfService $tfidfService,
        TextPreprocessingService $nlpService,
        CosineSimilarityService $cosineService
    ) {
        $this->tfidfService = $tfidfService;
        $this->nlpService = $nlpService;
        $this->cosineService = $cosineService;
    }

    /**
     * Menghasilkan rekomendasi video edukasi untuk setiap materi dalam satu mata kuliah.
     *
     * @param int $mataKuliahId
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateRecommendation($mataKuliahId)
    {
        // 1. Mengambil data mata kuliah beserta relasi materi
        $mataKuliah = MataKuliah::with('materis')->findOrFail($mataKuliahId);
        $materis = $mataKuliah->materis;

        if ($materis->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada materi yang ditemukan untuk mata kuliah ini.'
            ], 404);
        }

        // 2. Membentuk korpus lokal untuk perhitungan IDF (Kamus Bobot Mata Kuliah)
        $korpus = [];
        foreach ($materis as $materi) {
            if (!empty($materi->konten_bersih)) {
                $korpus[] = explode(' ', $materi->konten_bersih);
            }
        }
        $idf = $this->tfidfService->calculateIdf($korpus);

        // Memulai transaksi basis data untuk menjaga integritas data
        DB::beginTransaction();

        try {
            // 3. Melakukan iterasi pada setiap materi untuk proses ekstraksi dan pencarian
            foreach ($materis as $materi) {
                if (empty($materi->konten_bersih)) {
                    continue;
                }

                $tokensMateri = explode(' ', $materi->konten_bersih);
                
                // Menghitung nilai TF untuk materi saat ini
                $tfMateri = $this->tfidfService->calculateTf($tokensMateri);
                
                // Membentuk vektor TF-IDF untuk materi
                $vektorMateri = [];
                foreach ($tfMateri as $term => $tfVal) {
                    $vektorMateri[$term] = $tfVal * ($idf[$term] ?? 0);
                }

                // Mengekstrak 3 kata kunci teratas untuk kueri pencarian YouTube
                $kataKunciArray = $this->tfidfService->getTopKeywords($tfMateri, $idf, 3);
                
                if (empty($kataKunciArray)) {
                    continue;
                }

                $queryPencarian = implode(' ', $kataKunciArray) . ' tutorial indonesia';

                // 4. Memanggil YouTube Data API v3 untuk mengambil 15 kandidat video
                $youtubeApiKey = env('YOUTUBE_API_KEY');
                $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
                    'part'       => 'snippet',
                    'q'          => $queryPencarian,
                    'type'       => 'video',
                    'maxResults' => 15,
                    'key'        => $youtubeApiKey
                ]);

                if ($response->successful()) {
                    $kandidatVideos = $response->json()['items'] ?? [];
                    $videoDenganSkor = [];

                    // 5. Menghitung Cosine Similarity untuk setiap kandidat video
                    foreach ($kandidatVideos as $video) {
                        $judul = $video['snippet']['title'] ?? '';
                        $deskripsi = $video['snippet']['description'] ?? '';
                        $teksVideo = $judul . ' ' . $deskripsi;
                        
                        // Membersihkan teks video (tanpa stemming, sesuai aturan NLP sistem)
                        $teksVideoBersih = $this->nlpService->process($teksVideo);
                        $tokensVideo = explode(' ', $teksVideoBersih);
                        
                        // Menghitung nilai TF dan membentuk vektor TF-IDF untuk video
                        $tfVideo = $this->tfidfService->calculateTf($tokensVideo);
                        $vektorVideo = [];
                        foreach ($tfVideo as $term => $tfVal) {
                            $vektorVideo[$term] = $tfVal * ($idf[$term] ?? 0);
                        }

                        // Menghitung skor kedekatan antara materi dan video
                        $skorSimilarity = $this->cosineService->calculate($vektorMateri, $vektorVideo);

                        $videoDenganSkor[] = [
                            'skor'  => $skorSimilarity,
                            'video' => $video
                        ];
                    }

                    // 6. Mengurutkan kandidat berdasarkan skor tertinggi ke terendah
                    usort($videoDenganSkor, function($a, $b) {
                        return $b['skor'] <=> $a['skor'];
                    });

                    // Mengambil 5 video dengan skor tertinggi (Top 5)
                    $top5Videos = array_slice($videoDenganSkor, 0, 5);

                    // 7. Proses penyimpanan ke basis data
                    
                    // Menghapus rekomendasi terdahulu untuk materi ini guna mencegah duplikasi
                    MaterialVideoRecommendation::where('materi_id', $materi->id)->delete();

                    $ranking = 1;
                    foreach ($top5Videos as $item) {
                        $videoData = $item['video'];
                        $skorSimilarity = $item['skor'];
                        
                        // Menyimpan atau memperbarui metadata video pada tabel cache
                        $cache = YoutubeVideoCache::updateOrCreate(
                            ['youtube_video_id' => $videoData['id']['videoId']],
                            [
                                'judul_video'     => $videoData['snippet']['title'],
                                'deskripsi_video' => $videoData['snippet']['description'],
                                'thumbnail_url'   => $videoData['snippet']['thumbnails']['high']['url'] ?? null,
                            ]
                        );

                        // Menyimpan relasi rekomendasi beserta skor dan peringkat
                        MaterialVideoRecommendation::create([
                            'materi_id'        => $materi->id,
                            'youtube_video_id' => $cache->id, 
                            'similarity_score' => $skorSimilarity,
                            'ranking'          => $ranking
                        ]);

                        $ranking++;
                    }
                } else {
                    // Mencatat galat (error) apabila API YouTube gagal merespons
                    Log::error('YouTube API Error pada Materi ID ' . $materi->id . ': ' . $response->body());
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status'  => 'success',
                'message' => "Rekomendasi video untuk mata kuliah {$mataKuliah->nama_mata_kuliah} berhasil diperbarui."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Kesalahan Sistem Rekomendasi: ' . $e->getMessage());
            
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem saat memproses rekomendasi.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}