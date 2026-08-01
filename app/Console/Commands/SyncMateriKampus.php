<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Prodi;
use App\Models\MataKuliah;
use App\Models\Materi;
use App\Services\TextPreprocessingService; // Pastikan namespace service dipanggil

class SyncMateriKampus extends Command
{
    /**
     * Nama dan perintah untuk menjalankan command di terminal
     *
     * @var string
     */
    protected $signature = 'app:sync-materi {tahun}';

    /**
     * Deskripsi command
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data jadwal kuliah dan materi dari Open API ITG untuk Sistem Rekomendasi Video';

    /**
     * Eksekusi command
     */
    public function handle()
    {
        $tahun = $this->argument('tahun');
        $this->info("Memulai sinkronisasi data akademik tahun {$tahun}...");

        // Ambil token dari environment (.env)
        $token = env('ITG_OPEN_DATA_TOKEN');

        // Ambil semua data prodi dari database lokal
        $prodis = Prodi::all();

        if ($prodis->isEmpty()) {
            $this->error("Data prodi kosong! Pastikan seeder prodi sudah dijalankan.");
            return;
        }

        // Inisialisasi service NLP sekali di luar loop agar lebih hemat memori
        $nlpService = new TextPreprocessingService();

        foreach ($prodis as $prodi) {
            $this->info("==================================================");
            $this->info("-> Mengambil jadwal Prodi: {$prodi->nama_prodi}");

            try {
                // 1. Tarik Data Jadwal Kuliah (Aman dari error 500)
                $responseJadwal = Http::withToken($token)
                    ->timeout(120)
                    ->retry(3, 2000)
                    ->get('https://opendata.itg.ac.id/api/v1/jadwal-kuliah', [
                        'tahun' => $tahun,
                        'program_studi' => $prodi->nama_prodi 
                    ]);

                if ($responseJadwal->successful()) {
                    $jadwals = $responseJadwal->json()['data'] ?? [];

                    if (empty($jadwals)) {
                        $this->warn("   [!] Data jadwal kosong. Jadwal belum diinput admin kampus.");
                        continue; 
                    }

                    foreach ($jadwals as $jadwal) {
                        // Simpan atau update mata kuliah dengan membersihkan spasi berlebih
                        $matkul = MataKuliah::updateOrCreate(
                            [
                                'kode_mata_kuliah' => trim($jadwal['kode_mata_kuliah']),
                                'kelas'            => trim($jadwal['kelas']),
                                'tahun_akademik'   => $tahun
                            ],
                            [
                                'prodi_id'         => $prodi->id,
                                'nama_mata_kuliah' => trim($jadwal['nama_mata_kuliah'])
                            ]
                        );

                        $this->info("   => Cek materi: {$matkul->nama_mata_kuliah} (Kelas {$matkul->kelas})");
                        
                        try {
                            // 2. Tarik Data Materi per Mata Kuliah
                            $responseMateri = Http::withToken($token)
                                ->timeout(120)
                                ->retry(3, 2000)
                                ->get('https://opendata.itg.ac.id/api/v1/jadwal-kuliah/materi', [
                                    'tahun'            => $tahun,
                                    'kode_prodi'       => $prodi->kode_prodi,
                                    'kode_mata_kuliah' => $matkul->kode_mata_kuliah,
                                    'kelas'            => $matkul->kelas
                                ]);

                            if ($responseMateri->successful()) {
                                $dataMateri = $responseMateri->json()['data'] ?? [];

                                if (empty($dataMateri)) {
                                    $this->warn("      [-] Data materi kosong dari API.");
                                } else {
                                    $materiTersimpan = 0; // Penghitung materi valid

                                    foreach ($dataMateri as $materiData) {
                                        // Lewati jika judul_materi null 
                                        if (empty($materiData['judul_materi'])) {
                                            continue; 
                                        }

                                        // --- PROSES DATA CLEANING AWAL (Untuk UI) ---
                                        $kontenKotor = $materiData['konten_materi'] ?? '';
                                        
                                        // 1. Ubah entitas HTML menjadi karakter asli
                                        $kontenAsli = html_entity_decode($kontenKotor);
                                        // 2. Hapus semua tag HTML
                                        $kontenAsli = strip_tags($kontenAsli);
                                        // 3. Hapus kode taksonomi kurikulum (contoh: (L2; C4, A3, P3))
                                        $kontenAsli = preg_replace('/\([a-zA-Z0-9;,\s]+\)/', '', $kontenAsli);
                                        // 4. Ubah spasi ganda/tab/enter menjadi satu spasi
                                        $kontenAsli = preg_replace('/\s+/', ' ', $kontenAsli);
                                        // 5. Bersihkan spasi di awal dan akhir kalimat
                                        $kontenAsli = trim($kontenAsli);

                                        // --- PROSES TEXT PREPROCESSING NLP (Untuk Algoritma CBF) ---
                                        $kontenAlgoritma = $nlpService->process($kontenAsli);

                                        Materi::updateOrCreate(
                                            [
                                                'mata_kuliah_id' => $matkul->id,
                                                'pertemuan_ke'   => $materiData['pertemuan'], 
                                            ],
                                            [
                                                'judul'         => $materiData['judul_materi'],
                                                'konten'        => $kontenAsli,         // Disimpan utuh untuk antarmuka/UI
                                                'konten_bersih' => $kontenAlgoritma,    // Disimpan dalam bentuk kata dasar untuk mesin TF-IDF
                                            ]
                                        );
                                        
                                        $materiTersimpan++;
                                    }

                                    if ($materiTersimpan > 0) {
                                        $this->info("      [v] Berhasil simpan {$materiTersimpan} materi valid.");
                                    } else {
                                        $this->warn("      [-] Kerangka pertemuan ada, tapi konten/judul masih null");
                                    }
                                }
                            } else {
                                $this->error("      [x] Gagal menarik materi (Ditolak Server: " . $responseMateri->status() . ")");
                            }
                        } catch (\Throwable $e) {
                            $this->error("      [!] Error Sistem API Kampus pada Materi ini. Dilewati.");
                            $this->error("          Pesan: " . $e->getMessage());
                        }

                        // Jeda 1 detik biar server kampus tidak terkena rate limit 429
                        sleep(1);
                    }
                } else {
                    $this->error("   [x] API Jadwal mengembalikan status: " . $responseJadwal->status());
                }

            } catch (\Throwable $e) {
                $this->error("   [!] Terjadi kesalahan sistem (Server Error) saat mengambil jadwal {$prodi->nama_prodi}.");
                $this->error("       Prodi ini akan dilewati sementara. Pesan: " . $e->getMessage());
            }

            // Jeda 2 detik sebelum berpindah ke prodi selanjutnya
            sleep(2);
        }

        $this->info("==================================================");
        $this->info("Mantap, sinkronisasi data tahun akademik {$tahun} selesai diproses!");
    }
}