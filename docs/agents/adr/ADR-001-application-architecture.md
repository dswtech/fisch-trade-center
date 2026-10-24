# ADR-001 — Laravel + Inertia React dalam satu aplikasi web

**Status:** Berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** PRD Fase 0 dan kebutuhan lintas modul

## Keadaan

Produk diminta menggunakan React, Node.js, Tailwind CSS, shadcn/ui, dan backend Laravel dalam satu repository. Tim perlu memilih batas runtime yang tidak menggandakan authentication, routing, deployment, dan observability sebelum kebutuhan produk terbukti. Laravel 13 adalah stable major saat keputusan dibuat; React 19.3 dan Tailwind CSS 4.3 adalah rilis stable yang dituju. Versi patch aktual baru dikunci ketika instalasi dependency disetujui.

## Keputusan

Bangun satu aplikasi Laravel 13 yang menyajikan halaman React melalui Inertia. Vite/Node.js menjadi toolchain build frontend, bukan application server produksi kedua. Tailwind CSS 4.3 dan shadcn/ui stable terbaru menyediakan design primitives; komponen aplikasi disusun dari source component shadcn yang berada dalam repository.

Struktur awal mengikuti konvensi Laravel, dengan domain/service backend, Inertia pages, dan reusable React UI dalam repository yang sama. Session authentication, authorization, validation, dan server-provided public configuration tetap dimiliki Laravel.

## Ditolak — React SPA dan Laravel API sebagai dua aplikasi

Pemisahan `apps/web` dan `apps/api` memberi deployment independen, tetapi menambah kontrak API, CORS, token/cookie boundary, dua pipeline, dan sinkronisasi routing sebelum skala atau organisasi membutuhkannya. Biaya tersebut tidak membantu validasi alur trade awal.

## Ditolak — React SSR dengan Node server dan Laravel API

SSR dapat membantu halaman publik, tetapi menambah runtime Node produksi, invalidation/cache boundary, serta dua tempat yang berpotensi memproses authentication dan secret. Kebutuhan SEO awal dapat dilayani halaman publik Laravel/Inertia tanpa kompleksitas itu.

## Konsekuensi

- Satu deploy dan satu security boundary mempercepat fondasi serta mengurangi risiko secret client.
- Node.js tetap wajib untuk build/test frontend, tetapi tidak berjalan sebagai backend produksi.
- Kontrak browser-server memakai Inertia props/actions terlebih dahulu; endpoint eksternal baru membutuhkan keputusan kontrak tersendiri.
- Jika kelak frontend perlu deployment terpisah, pemisahan menjadi keputusan baru beserta biaya migrasinya.
- Tidak ada package yang dipasang sampai pengguna menyetujuinya; versi exact dicatat di lockfile sesudah persetujuan.
