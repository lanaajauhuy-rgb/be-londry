# Laundry Lastri — Backend Documentation

Dokumentasi resmi backend API sistem manajemen laundry **Laundry Lastri**.
Dibangun dengan Laravel 12, PHP 8.2, dan SQLite.

---

## Daftar Dokumen

| File | Isi |
|------|-----|
| [INSTALLATION.md](./INSTALLATION.md) | Cara setup dan menjalankan project dari nol |
| [API.md](./API.md) | Dokumentasi lengkap semua endpoint API |
| [DATABASE.md](./DATABASE.md) | Skema database, relasi antar tabel, dan penjelasan kolom |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Arsitektur project, struktur folder, dan alur request |
| [SEO.md](./SEO.md) | Panduan sistem SEO yang sudah diimplementasikan |
| [DEVELOPMENT.md](./DEVELOPMENT.md) | Panduan pengembangan, konvensi kode, dan workflow |

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Framework | Laravel 12 |
| Language | PHP 8.2+ |
| Database | SQLite (dev) → MySQL (production) |
| Auth | Session-based (Laravel built-in) |
| API Style | REST JSON |
| Queue | Database |
| SEO | Custom service layer |

---

## Status Project

> **Backend: Fase 1 (setengah jadi)**
> Frontend: Belum ada
> Domain: Belum ada
> Hosting: Belum ada

Fitur yang sudah ada: Auth, Customer CRUD, Service CRUD, Order CRUD, Order Item CRUD, Public Order, Delivery, SEO API.
Fitur yang belum: Payment endpoint, Status history, Laporan/dashboard, Reporting.
