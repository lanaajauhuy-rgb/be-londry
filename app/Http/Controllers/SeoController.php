<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ============================================================
// SeoController
// ============================================================
// Menangani semua request yang berkaitan dengan SEO:
//   GET /api/v1/seo/meta/{page}     → meta tags per halaman
//   GET /api/v1/seo/schema/{page}   → JSON-LD structured data per halaman
//
// Controller ini TIPIS — semua logic ada di SeoService.
// Controller hanya: terima request → panggil service → balas JSON.
// ============================================================
class SeoController extends Controller
{
    public function __construct(
        // Dependency Injection: Laravel otomatis buat instance SeoService
        // dan "inject" ke sini. Kita tidak perlu `new SeoService()` manual.
        protected SeoService $seo
    ) {}

    // ============================================================
    // meta($page)
    // ============================================================
    // Endpoint: GET /api/v1/seo/meta/{page}
    //
    // Contoh request dari frontend:
    //   fetch('/api/v1/seo/meta/home')
    //   fetch('/api/v1/seo/meta/services')
    //   fetch('/api/v1/seo/meta/services.5')   ← detail service id=5
    //
    // Response:
    //   { "title": "...", "description": "...", "canonical": "...", ... }
    // ============================================================
    public function meta(Request $request, string $page): JsonResponse
    {
        // Kalau halaman detail service (misal: "services.5"),
        // ambil data service dari DB supaya meta tags bisa pakai nama asli.
        $params = [];

        if (str_starts_with($page, 'services.')) {
            // Ambil ID dari "services.5" → "5"
            $serviceId = explode('.', $page)[1] ?? null;

            if ($serviceId) {
                // firstOrNull: kalau tidak ketemu, $service = null (tidak throw exception).
                $service = Service::where('is_active', 1)->find($serviceId);

                if ($service) {
                    // Kirim data ke SeoService supaya bisa bikin meta yang lebih spesifik.
                    $params = $service->only(['id', 'name', 'description', 'unit_price']);
                }
            }
        }

        $meta = $this->seo->getMeta($page, $params);

        return response()->json([
            'success' => true,
            'page'    => $page,
            'data'    => $meta,
        ]);
    }

    // ============================================================
    // schema($page)
    // ============================================================
    // Endpoint: GET /api/v1/seo/schema/{page}
    //
    // Contoh request dari frontend:
    //   fetch('/api/v1/seo/schema/home')        → LocalBusiness schema
    //   fetch('/api/v1/seo/schema/services')     → ItemList schema
    //   fetch('/api/v1/seo/schema/services.5')   → Service detail schema
    //
    // Response:
    //   { "success": true, "data": { "@context": "...", "@type": "...", ... } }
    //
    // Frontend letakkan di:
    //   <script type="application/ld+json">{{ JSON.stringify(data) }}</script>
    // ============================================================
    public function schema(Request $request, string $page): JsonResponse
    {
        $params = [];

        if (str_starts_with($page, 'services.')) {
            $serviceId = explode('.', $page)[1] ?? null;

            if ($serviceId) {
                $service = Service::where('is_active', 1)->find($serviceId);

                if ($service) {
                    $params = $service->only(['id', 'name', 'description', 'unit_price', 'pricing_model']);
                }
            }
        }

        $schema = $this->seo->getSchema($page, $params);

        return response()->json([
            'success' => true,
            'page'    => $page,
            'data'    => $schema,
        ]);
    }
}
