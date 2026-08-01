<?php

namespace App\Services;

class TfIdfService
{
    /**
     * Menghitung nilai Term Frequency (TF) untuk satu dokumen.
     *
     * @param array $tokens Array berisi kata dari satu dokumen (hasil explode konten_bersih)
     * @return array Array asosiatif dengan format [kata => bobot TF]
     */
    public function calculateTf(array $tokens): array
    {
        $tf = [];
        $totalWords = count($tokens);
        
        if ($totalWords === 0) {
            return $tf;
        }

        // Hitung frekuensi mentah kemunculan tiap kata
        $termCounts = array_count_values($tokens);

        // Normalisasi frekuensi dengan total kata dalam dokumen
        foreach ($termCounts as $term => $count) {
            $tf[$term] = $count / $totalWords;
        }

        return $tf;
    }

    /**
     * Menghitung nilai Inverse Document Frequency (IDF) dari seluruh korpus.
     *
     * @param array $corpus Array multidimensi berisi token dari seluruh dokumen
     * @return array Array asosiatif dengan format [kata => bobot IDF]
     */
    public function calculateIdf(array $corpus): array
    {
        $idf = [];
        $totalDocuments = count($corpus);
        $documentFrequency = [];

        // Hitung Document Frequency (df): di berapa dokumen kata 't' muncul
        foreach ($corpus as $tokens) {
            // Gunakan array_unique agar kata yang muncul berulang di 1 dokumen dihitung 1 kali
            $uniqueTokens = array_unique($tokens);
            foreach ($uniqueTokens as $token) {
                if (!isset($documentFrequency[$token])) {
                    $documentFrequency[$token] = 0;
                }
                $documentFrequency[$token]++;
            }
        }

        // Hitung bobot IDF menggunakan logaritma basis 10
        foreach ($documentFrequency as $term => $df) {
            $idf[$term] = log10($totalDocuments / $df);
        }

        return $idf;
    }

    /**
     * Menghitung skor akhir TF-IDF dan mengekstrak kata kunci teratas.
     *
     * @param array $tf Hasil ekstraksi dari fungsi calculateTf()
     * @param array $idf Hasil ekstraksi dari fungsi calculateIdf()
     * @param int $limit Jumlah kata kunci yang dibutuhkan untuk query YouTube
     * @return array Kumpulan kata kunci paling relevan
     */
    public function getTopKeywords(array $tf, array $idf, int $limit = 5): array
    {
        $tfidfScores = [];

        foreach ($tf as $term => $tfWeight) {
            // Jika kata memiliki nilai IDF, kalikan. Jika tidak, beri bobot 0.
            $idfWeight = $idf[$term] ?? 0;
            $tfidfScores[$term] = $tfWeight * $idfWeight;
        }

        // Urutkan array berdasarkan nilai tertinggi secara menurun (descending)
        arsort($tfidfScores);

        // Ambil sejumlah kata kunci teratas sesuai batas limit
        $topKeywords = array_slice(array_keys($tfidfScores), 0, $limit);

        return $topKeywords;
    }
}