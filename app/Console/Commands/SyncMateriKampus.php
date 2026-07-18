<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Prodi;
use App\Models\MataKuliah;
use App\Models\Materi;

class SyncMateriKampus extends Command
{
    // Nama perintah yang akan diketik di terminal
    protected $signature = 'app:sync-materi {tahun=20252}';
    protected $description = 'Menarik data mata kuliah dan materi dari Open Data ITG';

    public function handle()
    {
        $token = env('ITG_OPEN_DATA_TOKEN');
        $tahun = $this->argument('tahun');
        
        // Mengambil 5 prodi yang sudah di-seed di database
        $prodis = Prodi::all();

        $this->info("Memulai sinkronisasi data akademik tahun {$tahun}...");

        foreach ($prodis as $prodi) {
            $this->info("-> Mengambil jadwal Prodi: {$prodi->nama_prodi}");

            // 1. Tarik Data Jadwal Kuliah
            $responseJadwal = Http::withToken($token)->get('https://opendata.itg.ac.id/api/v1/jadwal-kuliah', [
                'tahun' => $tahun,
                'program_studi' => $prodi->nama_prodi
            ]);

            if ($responseJadwal->successful()) {
                $jadwals = $responseJadwal->json()['data'] ?? [];

                foreach ($jadwals as $jadwal) {
                    // Simpan atau update mata kuliah agar tidak duplikat
                    $matkul = MataKuliah::updateOrCreate(
                        [
                            'kode_mata_kuliah' => $jadwal['kode_mata_kuliah'],
                            'kelas' => $jadwal['kelas'],
                            'tahun_akademik' => $tahun
                        ],
                        [
                            'prodi_id' => $prodi->id,
                            'nama_mata_kuliah' => $jadwal['nama_mata_kuliah'] ?? '-'
                        ]
                    );

                    // 2. Tarik Data Materi berdasarkan Matkul
                    $responseMateri = Http::withToken($token)->get('https://opendata.itg.ac.id/api/v1/jadwal-kuliah/materi', [
                        'tahun' => $tahun,
                        'kode_prodi' => $prodi->kode_prodi,
                        'kode_mata_kuliah' => $matkul->kode_mata_kuliah,
                        'kelas' => $matkul->kelas
                    ]);

                    if ($responseMateri->successful()) {
                        $materis = $responseMateri->json()['data'] ?? [];

                        if (empty($jadwals)) {
                    $this->warn("   [!] Data jadwal kosong. Mungkin jadwal belum diinput atau nama prodi tidak sama dengan di API kampus.");
                }
                        
                        foreach ($materis as $materiData) {
                            // Filter validasi: Abaikan jika judul atau konten null
                            if (!empty($materiData['judul']) && !empty($materiData['konten'])) {
                                Materi::updateOrCreate(
                                    [
                                        'mata_kuliah_id' => $matkul->id,
                                        'pertemuan_ke' => $materiData['pertemuan'] ?? null
                                    ],
                                    [
                                        'judul' => $materiData['judul'],
                                        'konten' => $materiData['konten']
                                    ]
                                );
                            }
                        }
                    }
                }
            } else {
                $this->error("Gagal mengambil data untuk prodi {$prodi->nama_prodi}");
                $this->error("Status Code: " . $responseJadwal->status());
                $this->error("Pesan dari Server: " . $responseJadwal->body());
            }
        }

        $this->info("Sinkronisasi Selesai! Data materi siap diproses dengan TF-IDF.");
    }
}