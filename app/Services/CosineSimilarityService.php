<?php

namespace App\Services;

class CosineSimilarityService
{
    /**
     * Menghitung nilai Cosine Similarity antara dua vektor bobot (Materi vs Video).
     *
     * @param array $vectorA Array bobot kata dari Materi [kata => bobot]
     * @param array $vectorB Array bobot kata dari Video [kata => bobot]
     * @return float Skor kemiripan (0.0 sampai 1.0)
     */
    public function calculate(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        // Gabungkan semua kata unik dari kedua vektor untuk menyamakan dimensi
        $allTerms = array_unique(array_merge(array_keys($vectorA), array_keys($vectorB)));

        foreach ($allTerms as $term) {
            $valA = $vectorA[$term] ?? 0.0;
            $valB = $vectorB[$term] ?? 0.0;

            // Hitung Dot Product (Perkalian titik)
            $dotProduct += ($valA * $valB);

            // Kuadratkan masing-masing nilai untuk mencari Magnitude (Panjang Vektor)
            $magnitudeA += pow($valA, 2);
            $magnitudeB += pow($valB, 2);
        }

        // Akarkan total kuadrat
        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        // Cegah pembagian dengan nol (Division by Zero)
        if (($magnitudeA * $magnitudeB) == 0) {
            return 0.0;
        }

        // Rumus Cosine Similarity: Dot Product dibagi (Magnitude A * Magnitude B)
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}