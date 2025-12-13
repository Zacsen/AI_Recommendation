<?php

use App\Services\RecommendationEngine;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/recommender', [\App\Http\Controllers\RecommendationController::class, 'index']);
Route::post('/recommender/run', [\App\Http\Controllers\RecommendationController::class, 'run'])->name('recommender.run');
Route::post('/recommender/ai', [\App\Http\Controllers\RecommendationController::class, 'aiInterpret'])->name('recommender.ai');