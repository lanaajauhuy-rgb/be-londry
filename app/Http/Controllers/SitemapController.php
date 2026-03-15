<?php

namespace App\Http\Controllers;

use App\Services\SeoService;
use Illuminate\Http\Response;

// ============================================================
// SitemapController
// ============================================================
// Endpoint: GET /sitemap.xml
//
// Menghasilkan file sitemap.xml yang valid secara W3C.
// Google Search Console butuh format ini untuk crawl semua halaman.
//
// Kenapa tidak pakai file sitemap.xml statis?
// Karena kalau ada service baru di database, sitemap harus
// update otomatis — tidak perlu edit file manual.
// ============================================================
class SitemapController extends Controller
{
    public function __construct(
        protected SeoService $seo
    ) {}

    // ============================================================
    // index()
    // ============================================================
    // Render sitemap.xml dari semua URL yang dikumpulkan SeoService.
    //
    // Response headers yang penting:
    //   Content-Type: application/xml  → biar browser & crawler tahu ini XML
    //   Cache-Control: max-age=3600    → cache 1 jam, hemat request ke DB
    // ============================================================
    public function index(): Response
    {
        $urls = $this->seo->getSitemapUrls();

        // Build XML string secara manual (tanpa template Blade).
        // Kenapa manual? Supaya tidak butuh view file tambahan,
        // dan format sitemap sangat sederhana — tidak butuh templating engine.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";

            // htmlspecialchars: escape karakter & < > supaya XML valid.
            // URL yang punya & (misal: ?a=1&b=2) akan rusak XML kalau tidak di-escape.
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod']) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . htmlspecialchars($url['changefreq']) . '</changefreq>' . "\n";
            $xml .= '    <priority>' . htmlspecialchars($url['priority']) . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            // Cache 1 jam — sitemap tidak perlu di-generate tiap request.
            // Kalau ada service baru, cache akan expire sendiri dalam 1 jam.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
