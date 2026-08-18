<?php

use App\Http\Controllers\FurusatoController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\LineLoginController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\WatchController;
use App\Http\Controllers\LineWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FurusatoController::class, 'index'])->name('furusato.index');
Route::get('/search', [FurusatoController::class, 'search'])->name('furusato.search');
Route::get('/gifts/{furusatoItem}', [FurusatoController::class, 'show'])->name('furusato.show');
// 寄附先の自治体データ（総務省の現況調査）
Route::get('/jichitai', [MunicipalityController::class, 'index'])->name('municipalities.index');
Route::get('/jichitai/{prefectureSlug}', [MunicipalityController::class, 'prefecture'])
    ->whereAlpha('prefectureSlug')
    ->name('municipalities.prefecture');
Route::get('/jichitai/{prefectureSlug}/{code}', [MunicipalityController::class, 'show'])
    ->whereAlpha('prefectureSlug')
    ->whereNumber('code')
    ->name('municipalities.show');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::view('/about', 'about')->name('about');

Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('reviews.store');

// LINE連携（ウォッチキーワードの新着・再登場通知）
Route::get('/line/login', [LineLoginController::class, 'redirect'])->name('line.login');
Route::get('/line/callback', [LineLoginController::class, 'callback'])->name('line.callback');
Route::post('/watches', [WatchController::class, 'toggle'])
    ->name('watches.toggle')
    ->middleware('throttle:10,1');
Route::post('/line/webhook', [LineWebhookController::class, 'handle'])->name('line.webhook');
