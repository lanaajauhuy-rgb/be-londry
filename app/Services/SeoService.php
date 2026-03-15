<?php

namespace App\Services;

use App\Models\Service;

// ============================================================
// SeoService
// ============================================================
// Kelas ini adalah "pabrik" semua data SEO.
// Controller tidak perlu tahu cara bikin JSON-LD atau meta tags —
// cukup panggil method di sini dan terima hasilnya.
//
// Kenapa dipisah ke Service layer?
// Karena SEO logic bisa kompleks (beda schema per halaman,
// beda meta per service, dsb). Kalau semua di Controller,
// controller jadi gemuk dan susah di-test.
// ============================================================
class SeoService
{
    // Nama bisnis — ganti sesuai nama laundry kamu.
    protected string $businessName = 'Laundry Lastri';

    // Deskripsi default bisnis untuk meta description.
    protected string $defaultDescription = 'Laundry Lastri — jasa laundry kiloan, cuci sepatu, dan express wash terpercaya. Bersih, wangi, tepat waktu.';

    // URL domain — WAJIB diganti setelah punya domain.
    // Sekarang pakai localhost dulu untuk development.
    protected string $baseUrl = 'https://laundrylastri.com';

    // ============================================================
    // getMeta($page, $params)
    // ============================================================
    // Kembalikan array meta tags untuk halaman tertentu.
    // Frontend pakai data ini untuk isi <title>, <meta>, <og:*>.
    //
    // $page   : identifier halaman. Contoh: 'home', 'services', 'services.5'
    // $params : data tambahan (misal: data service dari DB untuk detail page)
    // ============================================================
    public function getMeta(string $page, array $params = []): array
    {
        $meta = match (true) {
            $page === 'home'     => $this->metaHome(),
            $page === 'services' => $this->metaServices(),
            $page === 'order'    => $this->metaOrder(),
            str_starts_with($page, 'services.') => $this->metaServiceDetail($params),
            default              => $this->metaDefault(),
        };

        // Tambahkan field standar yang selalu ada di semua halaman.
        return array_merge($meta, [
            'og_type'      => $meta['og_type'] ?? 'website',
            'og_url'       => $meta['og_url'] ?? $this->baseUrl,
            'og_image'     => $meta['og_image'] ?? $this->baseUrl . '/images/og-default.jpg',
            'twitter_card' => 'summary_large_image',
            'canonical'    => $meta['canonical'] ?? $this->baseUrl,
        ]);
    }

    // ============================================================
    // getSchema($page, $params)
    // ============================================================
    // Kembalikan JSON-LD structured data untuk halaman tertentu.
    // JSON-LD = format data terstruktur yang dibaca Google untuk
    // menampilkan rich results (rating bintang, harga, FAQ, dsb).
    //
    // Diletakkan di <script type="application/ld+json"> di <head>.
    // ============================================================
    public function getSchema(string $page, array $params = []): array
    {
        return match (true) {
            $page === 'home'     => $this->schemaLocalBusiness(),
            $page === 'services' => $this->schemaServiceList(),
            $page === 'order'    => $this->schemaOrder(),
            str_starts_with($page, 'services.') => $this->schemaServiceDetail($params),
            default              => $this->schemaLocalBusiness(),
        };
    }

    // ============================================================
    // getSitemapUrls()
    // ============================================================
    // Kembalikan semua URL yang harus masuk ke sitemap.xml.
    // Setiap URL punya: loc, lastmod, changefreq, priority.
    //
    // priority : 1.0 = paling penting, 0.1 = paling tidak penting.
    // changefreq: petunjuk ke Google seberapa sering halaman berubah.
    // ============================================================
    public function getSitemapUrls(): array
    {
        $urls = [
            [
                'loc'        => $this->baseUrl . '/',
                'lastmod'    => now()->toDateString(),
                'changefreq' => 'weekly',
                'priority'   => '1.0',
            ],
            [
                'loc'        => $this->baseUrl . '/services',
                'lastmod'    => now()->toDateString(),
                'changefreq' => 'weekly',
                'priority'   => '0.9',
            ],
            [
                'loc'        => $this->baseUrl . '/order',
                'lastmod'    => now()->toDateString(),
                'changefreq' => 'monthly',
                'priority'   => '0.8',
            ],
        ];

        // Tambahkan halaman detail untuk setiap service aktif di database.
        // Sitemap selalu up-to-date otomatis — service baru langsung masuk.
        $services = Service::where('is_active', 1)->get(['id', 'name', 'updated_at']);

        foreach ($services as $service) {
            $urls[] = [
                'loc'        => $this->baseUrl . '/services/' . $service->id,
                'lastmod'    => $service->updated_at
                    ? $service->updated_at->toDateString()
                    : now()->toDateString(),
                'changefreq' => 'monthly',
                'priority'   => '0.7',
            ];
        }

        return $urls;
    }

    // ============================================================
    // PRIVATE: Meta per halaman
    // ============================================================

    private function metaHome(): array
    {
        return [
            'title'       => $this->businessName . ' — Jasa Laundry Kiloan Terpercaya',
            'description' => $this->defaultDescription,
            'keywords'    => 'laundry kiloan, jasa laundry, cuci baju, laundry express, laundry terdekat',
            'canonical'   => $this->baseUrl . '/',
            'og_url'      => $this->baseUrl . '/',
        ];
    }

    private function metaServices(): array
    {
        return [
            'title'       => 'Layanan Laundry — ' . $this->businessName,
            'description' => 'Lihat semua layanan laundry kami: cuci kiloan, cuci sepatu, express wash. Harga transparan, hasil bersih.',
            'keywords'    => 'layanan laundry, jenis laundry, harga laundry, cuci kiloan murah',
            'canonical'   => $this->baseUrl . '/services',
            'og_url'      => $this->baseUrl . '/services',
        ];
    }

    private function metaServiceDetail(array $params): array
    {
        // Kalau ada data service dari DB (dikirim controller), pakai itu.
        // Kalau tidak ada, fallback ke meta generik.
        $name = $params['name'] ?? 'Layanan Laundry';
        $desc = $params['description'] ?? 'Detail layanan laundry ' . $name . '. Harga terjangkau, proses cepat.';

        return [
            'title'       => $name . ' — ' . $this->businessName,
            'description' => $desc,
            'keywords'    => strtolower($name) . ', laundry, ' . $this->businessName,
            'canonical'   => $this->baseUrl . '/services/' . ($params['id'] ?? ''),
            'og_url'      => $this->baseUrl . '/services/' . ($params['id'] ?? ''),
        ];
    }

    private function metaOrder(): array
    {
        return [
            'title'       => 'Pesan Laundry Online — ' . $this->businessName,
            'description' => 'Pesan layanan laundry dengan mudah. Isi form order online, kami siap melayani.',
            'keywords'    => 'pesan laundry online, order laundry, laundry antar jemput',
            'canonical'   => $this->baseUrl . '/order',
            'og_url'      => $this->baseUrl . '/order',
            // Halaman order form tidak perlu di-index Google.
            'robots'      => 'noindex, follow',
        ];
    }

    private function metaDefault(): array
    {
        return [
            'title'       => $this->businessName,
            'description' => $this->defaultDescription,
            'keywords'    => 'laundry, cuci baju, laundry kiloan',
            'canonical'   => $this->baseUrl,
            'og_url'      => $this->baseUrl,
        ];
    }

    // ============================================================
    // PRIVATE: Schema JSON-LD per halaman
    // ============================================================

    // Schema LocalBusiness — info bisnis lengkap untuk Google.
    // Bisa muncul sebagai Knowledge Panel di hasil pencarian.
    private function schemaLocalBusiness(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'LocalBusiness',
            'name'        => $this->businessName,
            'description' => $this->defaultDescription,
            'url'         => $this->baseUrl,
            'telephone'   => '+62-XXX-XXXX-XXXX', // TODO: ganti nomor HP
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Jl. Contoh No. 1',   // TODO: ganti alamat
                'addressLocality' => 'Jakarta',              // TODO: ganti kota
                'addressRegion'   => 'DKI Jakarta',
                'postalCode'      => '10000',                // TODO: ganti kode pos
                'addressCountry'  => 'ID',
            ],
            'openingHoursSpecification' => [
                [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'],
                    'opens'     => '08:00',
                    'closes'    => '20:00',
                ],
                [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Saturday','Sunday'],
                    'opens'     => '08:00',
                    'closes'    => '17:00',
                ],
            ],
            'image'              => $this->baseUrl . '/images/og-default.jpg',
            'priceRange'         => '$$',
            'currenciesAccepted' => 'IDR',
            'paymentAccepted'    => 'Cash, Transfer Bank',
        ];
    }

    // Schema ItemList — daftar semua layanan dari database.
    // Google bisa tampilkan ini sebagai carousel atau rich list.
    private function schemaServiceList(): array
    {
        $services = Service::where('is_active', 1)
            ->get(['id', 'name', 'description', 'unit_price', 'pricing_model']);

        $items = $services->map(function ($service, $index) {
            return [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'item'     => [
                    '@type'       => 'Service',
                    '@id'         => $this->baseUrl . '/services/' . $service->id,
                    'name'        => $service->name,
                    'description' => $service->description ?? $service->name,
                    'offers'      => [
                        '@type'         => 'Offer',
                        'price'         => (string) $service->unit_price,
                        'priceCurrency' => 'IDR',
                        'description'   => match ($service->pricing_model) {
                            'per_kg'   => 'Per kilogram',
                            'per_item' => 'Per item',
                            'flat'     => 'Harga tetap',
                            default    => '',
                        },
                    ],
                ],
            ];
        })->toArray();

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'Layanan ' . $this->businessName,
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];
    }

    private function schemaServiceDetail(array $params): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => $params['name'] ?? 'Layanan Laundry',
            'description' => $params['description'] ?? '',
            'provider'    => [
                '@type' => 'LocalBusiness',
                'name'  => $this->businessName,
                'url'   => $this->baseUrl,
            ],
            'offers' => [
                '@type'         => 'Offer',
                'price'         => (string) ($params['unit_price'] ?? '0'),
                'priceCurrency' => 'IDR',
            ],
        ];
    }

    private function schemaOrder(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'WebPage',
            'name'     => 'Pesan Laundry Online',
            'url'      => $this->baseUrl . '/order',
            'breadcrumb' => [
                '@type'           => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',  'item' => $this->baseUrl],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Order', 'item' => $this->baseUrl . '/order'],
                ],
            ],
        ];
    }
}
