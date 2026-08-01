<?php

namespace App\Services;

use Sastrawi\StopWordRemover\StopWordRemoverFactory;
use Sastrawi\Dictionary\ArrayDictionary;
use Sastrawi\StopWordRemover\StopWordRemover;

class TextPreprocessingService
{
    protected $stopWordRemover;

    public function __construct()
    {
        // Inisialisasi Stopword Remover dengan Domain-Specific Words
        $stopWordFactory = new StopWordRemoverFactory();
        $defaultStopWords = $stopWordFactory->getStopWords();

        // Daftar kata akademik yang menjadi noise
        $academicStopWords = [
            'mahasiswa', 'mampu', 'membandingkan','ketepatan','memproseskan','mengevaluasi','menjelaskan', 'memahami', 'konsep',
            'capaian', 'pembelajaran', 'mata', 'kuliah', 'diharapkan',
            'teori', 'praktek', 'dasar', 'lanjut', 'mengerti', 'tujuan',
            'pertemuan', 'ini', 'adalah', 'untuk', 'serta', 'dapat'
        ];

        // Gabungkan stopword bawaan dengan stopword akademik
        $mergedStopWords = array_merge($defaultStopWords, $academicStopWords);
        $dictionary = new ArrayDictionary($mergedStopWords);
        
        $this->stopWordRemover = new StopWordRemover($dictionary);
    }

    public function process(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Tahap 1: Case Folding (Mengubah semua huruf menjadi kecil)
        $text = strtolower($text);

        // Tahap 2: Cleansing (Hanya menyisakan huruf alfabet dari a-z)
        $text = preg_replace('/[^a-z\s]/', '', $text);

        // Tahap 3: Stopword Removal (Menghapus kata hubung dan kata akademik)
        $text = $this->stopWordRemover->remove($text);

        // Tahap Stemming DIHAPUS agar istilah teknis seperti "keputusan" tidak rusak

        // Tahap 4: Normalisasi Spasi
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return $text;
    }
}