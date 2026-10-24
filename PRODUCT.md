# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13, Inertia Laravel, React 19.3, TypeScript, Vite, Tailwind CSS 4.3, dan komponen source shadcn/ui dalam satu aplikasi. PostgreSQL adalah database target produksi; automated test memakai database disposable.

## Users

Pemain FISCH yang ingin memahami value inventory, menemukan partner trade, menyusun listing, mencari item, dan menilai kelayakan pertukaran. Produk dipakai setara pada ponsel dan desktop, termasuk saat pengguna sedang bermain dan membutuhkan alur singkat yang mudah dipindai.

Guest dapat melihat landing page, katalog, dan listing publik yang sudah diredaksi. Player mengelola profil, inventory, listing, pencarian, dan simulasi W/F/L. Administrator mengelola katalog, import, snapshot value, dan konfigurasi Fase 1.

## Product Purpose

Fisch Trade Center mengurangi ketidakpastian trade dengan menyatukan katalog, published value snapshot yang immutable, inventory self-declared, listing anonim, pencarian deterministik, dan simulasi W/F/L yang menyebut perspektif secara eksplisit.

Fase 0–1 berhasil ketika alur akun, katalog, inventory, listing, search, dan simulasi berjalan end-to-end; data identitas lawan tetap teredaksi; invalid value import tidak mengganti snapshot aktif; dan seluruh jalur utama dapat dipakai pada viewport 360–1440 px.

## Positioning

Produk bukan sekadar papan listing: setiap hasil penilaian dan listing yang bernilai ditautkan pada satu published value snapshot sehingga perhitungan dapat direproduksi tanpa mencampur versi. Identitas Roblox tidak menjadi identitas publik pada discovery.

## Operating Context

- Player mendaftar dengan email/password, memverifikasi email, mengisi USN/IGN manual yang tetap berstatus `unverified`, lalu mengelola inventory.
- Player membuat listing dari inventory, mencari listing pemain lain, dan membandingkan dua sisi trade lewat simulator W/F/L.
- Administrator memeriksa hasil import fixture, memublikasikan snapshot valid, dan mengatur Fair threshold serta listing expiry secara prospektif.
- Bahasa UI pertama dipilih dari locale browser (`id` untuk Indonesia, selain itu `en`), dapat diganti, dan dipersist.
- Dark theme adalah pengalaman default; light theme tetap tersedia dan dipersist.

## Capabilities and Constraints

- Scope aktif: fondasi dan Fase 1—authentication, profile, catalog/value snapshot, inventory, listing, search, W/F/L, dan administrasi minimum terkait.
- Di luar scope: negotiation, deal/reveal, wallet, payment, konektor Value List produksi, report/dispute penuh, dan admin finansial.
- Email wajib terverifikasi sebelum inventory, listing, atau simulator privat.
- `fair_threshold_percent` awal adalah `10`; perubahan admin berlaku prospektif dan diaudit.
- Listing aktif kedaluwarsa setelah 14 hari.
- Value sync memakai provider contract dan fixture berversi. Provider produksi fail-closed sampai izin dan akses disetujui.
- Kategori awal adalah `skin`, `boat`, dan `fishing_rod`; model kategori tidak boleh menjadi enum tertutup.
- Tidak ada migration, seed, scheduler, queue worker, atau import yang dijalankan terhadap database persisten oleh agent tanpa perintah eksplisit.

## Brand Commitments

- Nama produk: Fisch Trade Center.
- Struktur UX mengambil referensi dari kepadatan, app shell, dan hierarchy Metronic Tailwind Demo 1 tanpa menyalin source, aset, logo, atau composition secara literal.
- Palet berangkat dari midnight navy, indigo, violet, putih dingin, dan aksen kuning terbatas pada lampiran pengguna.
- Identitas harus original dan dekat dengan atmosfer dunia memancing—laut dalam, bioluminesensi, perlengkapan, rarity, dan exchange—tanpa meniru logo atau artwork Fisch, Roblox, maupun Metronic dan tanpa menyatakan afiliasi resmi.
- UI bilingual Indonesia–English. Dokumentasi produk tetap Bahasa Indonesia; nama kode dan kontrak teknis tetap English.

## Evidence on Hand

- PRD, knowledge, memory, dan ADR aktif di `docs/agents/`.
- Referensi struktur UI: `https://keenthemes.com/metronic/tailwind/demo1/`.
- Referensi warna: `C:/Users/dsw/Pictures/_temp/ss_vibe/Preview.__large_preview.jpg` dengan warna dominan terkuantisasi sekitar `#000010`, `#100040`, `#002080`, dan `#F0D000`.
- Tidak ada logo, item artwork berizin, data produksi, testimonial, atau benchmark aktual. Comp dan fixture memakai konten synthetic yang diberi label jelas.

## Product Principles

1. Jelaskan state dan perspektif, jangan meminta pengguna menebak arti angka atau label.
2. Fail-closed ketika value, threshold, permission, atau source belum valid.
3. Lindungi identitas lebih dulu; discovery tidak membutuhkan USN/IGN lawan.
4. Density boleh tinggi, tetapi aksi primer, hierarchy, dan scanability harus tetap jelas di mobile maupun desktop.
5. Simpan sejarah keputusan melalui versioning dan snapshot immutable.

## Accessibility & Inclusion

Alur utama memenuhi WCAG 2.2 AA: contrast, keyboard, visible focus, label, error announcement, target sentuh minimum 44×44 px, reduced motion, dan informasi status yang tidak bergantung pada warna saja.
