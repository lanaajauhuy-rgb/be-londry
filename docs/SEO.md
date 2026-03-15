# Panduan SEO — Laundry Lastri

Dokumentasi sistem SEO yang sudah diimplementasikan di backend Laravel.

---

## Overview

Karena project ini masih API-only (belum ada frontend), sistem SEO dibangun sebagai **data provider** — backend menyediakan semua data SEO via API, dan frontend (saat sudah ada) yang menerapkannya ke HTML.

### Komponen yang sudah ada

| Komponen | File | Akses |
|----------|------|-------|
| Meta tags API | `SeoController`, `SeoService` | `GET /api/v1/seo/meta/{page}` |
| JSON-LD Schema API | `SeoController`, `SeoService` | `GET /api/v1/seo/schema/{page}` |
| Sitemap dinamis | `SitemapController`, `SeoService` | `GET /sitemap.xml` |
| robots.txt | `public/robots.txt` | `GET /robots.txt` |

---

## 1. robots.txt

File statis di `public/robots.txt`. Diakses langsung tanpa lewat Laravel.

**Isi saat ini:**
```
User-agent: *
Allow: /

Disallow: /admin/
Disallow: /api/

Sitemap: https://laundrylastri.com/sitemap.xml
```

**Kapan perlu diubah:**
- Saat punya halaman admin di frontend yang tidak perlu di-index → tambah `Disallow: /admin`
- Saat domain sudah fix → update URL di baris `Sitemap:`

**Cara edit:** buka langsung `public/robots.txt` dan edit isinya.

---

## 2. sitemap.xml

Dihasilkan secara dinamis oleh `SitemapController` dari database.
Setiap request ke `/sitemap.xml` akan mengambil data terbaru dari database.

**URL yang dimasukkan ke sitemap:**

| URL | Priority | Change Freq |
|-----|----------|------------|
| `/` | 1.0 | weekly |
| `/services` | 0.9 | weekly |
| `/order` | 0.8 | monthly |
| `/services/{id}` (setiap service aktif) | 0.7 | monthly |

**Sitemap otomatis update** ketika ada service baru yang ditambahkan ke database.

**Format output:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://laundrylastri.com/</loc>
    <lastmod>2026-03-15</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  ...
</urlset>
```

**Cara daftarkan ke Google Search Console:**
1. Setelah punya domain, buka [Google Search Console](https://search.google.com/search-console)
2. Tambah properti domain
3. Verifikasi kepemilikan domain
4. Masuk ke menu **Sitemaps**
5. Submit URL: `https://domain-kamu.com/sitemap.xml`

---

## 3. Meta Tags API

### Endpoint

```
GET /api/v1/seo/meta/{page}
```

### Halaman yang didukung

| `{page}` | Keterangan |
|----------|-----------|
| `home` | Beranda |
| `services` | Daftar layanan |
| `order` | Form order |
| `services.{id}` | Detail layanan (misal: `services.1`) |

### Cara pakai di frontend

**Next.js (App Router):**
```jsx
// app/page.jsx (Server Component)
export async function generateMetadata() {
  const res = await fetch(`${process.env.API_URL}/api/v1/seo/meta/home`);
  const { data } = await res.json();

  return {
    title: data.title,
    description: data.description,
    keywords: data.keywords,
    alternates: { canonical: data.canonical },
    openGraph: {
      title: data.title,
      description: data.description,
      url: data.og_url,
      type: data.og_type,
      images: [data.og_image],
    },
    twitter: {
      card: data.twitter_card,
    },
  };
}
```

**Next.js (Pages Router):**
```jsx
import Head from 'next/head';

export async function getServerSideProps() {
  const res = await fetch(`${process.env.API_URL}/api/v1/seo/meta/home`);
  const { data } = await res.json();
  return { props: { meta: data } };
}

export default function HomePage({ meta }) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
        <meta name="description" content={meta.description} />
        <meta name="keywords" content={meta.keywords} />
        <link rel="canonical" href={meta.canonical} />
        <meta property="og:title" content={meta.title} />
        <meta property="og:description" content={meta.description} />
        <meta property="og:url" content={meta.og_url} />
        <meta property="og:image" content={meta.og_image} />
        <meta name="twitter:card" content={meta.twitter_card} />
      </Head>
      {/* konten halaman */}
    </>
  );
}
```

---

## 4. JSON-LD Structured Data API

### Endpoint

```
GET /api/v1/seo/schema/{page}
```

### Schema yang dihasilkan per halaman

| `{page}` | `@type` | Manfaat |
|----------|---------|---------|
| `home` | `LocalBusiness` | Knowledge Panel, info bisnis di Google |
| `services` | `ItemList` | Rich results daftar layanan |
| `services.{id}` | `Service` | Rich results detail layanan + harga |
| `order` | `WebPage` | Breadcrumb |

### Cara pakai di frontend

```jsx
// Ambil schema dari API
const res = await fetch(`${process.env.API_URL}/api/v1/seo/schema/home`);
const { data: schema } = await res.json();

// Inject ke <head> sebagai JSON-LD
<script
  type="application/ld+json"
  dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
/>
```

---

## 5. Yang Perlu Diupdate Setelah Punya Domain

Buka `app/Services/SeoService.php` dan update 3 bagian ini:

```php
// Baris 13 — ganti dengan domain asli
protected string $baseUrl = 'https://laundrylastri.com';

// Baris sekitar 103 — ganti nomor HP asli
'telephone' => '+62-XXX-XXXX-XXXX',

// Baris sekitar 105 — ganti alamat asli
'streetAddress'   => 'Jl. Contoh No. 1',
'addressLocality' => 'Jakarta',
'postalCode'      => '10000',
```

Juga update `APP_URL` di file `.env`:
```env
APP_URL=https://domain-kamu.com
```

Dan update `public/robots.txt` baris Sitemap:
```
Sitemap: https://domain-kamu.com/sitemap.xml
```

---

## 6. SEO untuk Frontend (Catatan Penting)

Karena SEO sangat bergantung pada **Server-Side Rendering (SSR)**, pilihan framework frontend sangat berpengaruh:

| Framework | SSR Support | Rekomendasi untuk SEO |
|-----------|-------------|----------------------|
| **Next.js** | ✅ Built-in | ⭐ Paling direkomendasikan |
| **Nuxt.js** (Vue) | ✅ Built-in | ⭐ Alternatif bagus |
| React (SPA biasa) | ❌ Client-side only | ❌ Buruk untuk SEO |
| Vue (SPA biasa) | ❌ Client-side only | ❌ Buruk untuk SEO |

> **Kesimpulan:** Kalau serius mau SEO-friendly, pakai **Next.js** atau **Nuxt.js**. Jangan pakai SPA murni karena Googlebot tidak selalu bisa render JavaScript.

---

## 7. Checklist SEO Setelah Punya Domain & Frontend

- [ ] Update `baseUrl` di `SeoService.php`
- [ ] Update `APP_URL` di `.env`
- [ ] Update URL di `public/robots.txt`
- [ ] Tambah gambar OG di `public/images/og-default.jpg` (ukuran 1200×630px)
- [ ] Daftarkan sitemap ke Google Search Console
- [ ] Daftarkan sitemap ke Bing Webmaster Tools
- [ ] Pasang Google Analytics atau alternatif (Plausible, Umami)
- [ ] Verifikasi structured data di [Rich Results Test](https://search.google.com/test/rich-results)
- [ ] Cek robots.txt di [robots.txt Tester](https://support.google.com/webmasters/answer/6062598)
