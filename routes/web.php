<?php

use App\Http\Controllers\FurusatoController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FurusatoController::class, 'index'])->name('furusato.index');
Route::get('/search', [FurusatoController::class, 'search'])->name('furusato.search');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::view('/about', 'about')->name('about');

Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('reviews.store');
