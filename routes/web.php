<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoRecommendationController;

Route::post('/mata-kuliah/{mataKuliahId}/generate-recommendations', [VideoRecommendationController::class, 'generateRecommendation'])
    ->name('recommendations.generate');

