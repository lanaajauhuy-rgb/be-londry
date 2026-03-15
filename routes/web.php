<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// ============================================================
// Web Routes — untuk file statis & SEO
// ============================================================
// routes/web.php biasanya untuk halaman HTML (Blade).
// Tapi karena project ini API-only (FE terpisah), web.php kita
// pakai khusus untuk file-file yang harus bisa diakses langsung
// tanpa prefix /api: sitemap.xml dan robots.txt.
//
// Kenapa tidak di api.php?
// Karena crawler (Googlebot) selalu akses /sitemap.xml dan
// /robots.txt di root domain — bukan /api/sitemap.xml.
// ============================================================

// Health check endpoint — cek apakah server hidup.
Route::get('/', function () {
    return response()->json([
        'app'     => config('app.name'),
        'version' => '1.0.0',
        'status'  => 'running',
    ]);
});

// ============================================================
// sitemap.xml
// ============================================================
// Google Search Console akan cari file ini di:
//   https://domain-kamu.com/sitemap.xml
//
// Diisi dari database secara dinamis oleh SitemapController.
// Daftarkan juga ke Google Search Console setelah punya domain.
// ============================================================
Route::get('/sitemap.xml', [SitemapController::class, 'index'])
    ->name('sitemap');

// robots.txt dilayani sebagai file statis dari public/robots.txt
// Edit langsung file tersebut kalau perlu update rules crawler.
