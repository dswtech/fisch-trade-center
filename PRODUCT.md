# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13, Inertia Laravel, React 19.3, TypeScript, Vite, Tailwind CSS 4.3, dan komponen source shadcn/ui dalam satu aplikasi. PostgreSQL adalah database target produksi; automated test memakai database disposable.

## Users

Pemain FISCH yang ingin memahami value inventory, menemukan partner trade, menyusun listing, mencari item, dan menilai kelayakan pertukaran. Produk dipakai setara pada ponsel dan desktop, termasuk saat pengguna sedang bermain dan membutuhkan alur singkat yang mudah dipindai.

Guest dapat melihat landing page, katalog, dan listing publik yang sudah diredaksi. Player mengelola profil, inventory, listing, pencarian, simulasi W/F/L, negotiation, dan deal miliknya. Operator menangani trust operations terbatas. Administrator mengelola katalog, import, snapshot value, master Rod, thumbnail, dan konfigurasi operasional.

## Product Purpose

Fisch Trade Center mengurangi ketidakpastian trade dengan menyatukan katalog, published value snapshot yang immutable, inventory self-declared, listing anonim, pencarian deterministik, dan simulasi W/F/L yang menyebut perspektif secara eksplisit.

Target MVP berakhir pada Fase 4. Alur akun, katalog, inventory, listing, search, simulasi, negotiation, deal, dan reveal harus berjalan end-to-end. Wallet dan fee tetap nonaktif dengan `fee = 0`. Identitas lawan tetap teredaksi sampai final acceptance berhasil secara atomik.

## Positioning

Produk bukan sekadar papan listing: setiap hasil penilaian dan listing yang bernilai ditautkan pada satu published value snapshot sehingga perhitungan dapat direproduksi tanpa mencampur versi. Identitas Roblox tidak menjadi identitas publik pada discovery.

## Operating Context

- Player mendaftar dengan email/password, memverifikasi email, mengisi USN/IGN manual yang tetap berstatus `unverified`, lalu mengelola inventory.
- Player membuat listing dari inventory, mencari listing pemain lain, dan membandingkan dua sisi trade lewat simulator W/F/L.
- Administrator mengunggah CSV katalog/value ke staging, meninjau preview dan warning, lalu memublikasikan snapshot valid secara manual.
- Administrator mengelola master Rod non-tradable, relasi Rod Skin, thumbnail item, Fair threshold, dan status katalog.
- Bahasa UI pertama dipilih dari locale browser (`id` untuk Indonesia, selain itu `en`), dapat diganti, dan dipersist.
- Dark theme adalah pengalaman default; light theme tetap tersedia dan dipersist.

## Capabilities and Constraints

- Roadmap memakai Fase 0–6. Fase 0–2 mencakup fondasi, katalog/admin, inventory, listing, search, dan W/F/L. Fase 3–4 melengkapi MVP melalui negotiation, deal/reveal, dan trust operations minimum.
- Wallet, payment, refund, compensation, dan dispute finansial berada di Fase 5 pasca-MVP. Production readiness berada di Fase 6.
- Email wajib terverifikasi sebelum inventory, listing, atau simulator privat.
- `fair_threshold_percent` awal adalah `10`; perubahan admin berlaku prospektif dan diaudit.
- Listing aktif kedaluwarsa setelah 14 hari.
- Proposal aktif kedaluwarsa setelah 24 jam dan negotiation setelah 7 hari. Scheduler Fase 3 menutup state tersebut tanpa menghapus riwayat.
- Pra-deal kedaluwarsa setelah 30 menit. Akses identity snapshot setelah reveal berlaku 30 hari; expiry tidak menghapus snapshot immutable.
- Environment local, development, staging, dan testing menyediakan halaman admin khusus untuk memicu expiry pra-deal atau akses reveal secara manual. Surface ini tidak tersedia di production dan setiap tindakan diaudit.
- Deal MVP memakai `fee = 0`, mewajibkan disclaimer non-escrow, dan baru membuka identity snapshot setelah final acceptance kedua pihak berhasil atomik.
- Katalog/value berasal dari CSV UTF-8 yang diunggah admin. Upload hanya membuat staging; publish manual melakukan partial upsert dan membuat snapshot immutable secara atomik.
- Perubahan value di atas 25% menghasilkan warning yang wajib dikonfirmasi. Baris dengan value kosong menghapus value item pada snapshot baru.
- Kategori awal adalah `rod_skin`, `boat`, `relic_set`, dan `other`; model kategori tidak boleh menjadi enum tertutup.
- Rod adalah metadata non-tradable. Rod Skin tanpa relasi menampilkan `Undefined Rod`.
- Thumbnail menerima JPG/PNG/WebP maksimal 2 MB. Item tanpa thumbnail memakai fallback kategori.
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
- Tidak ada logo, item artwork berizin, data produksi, testimonial, atau benchmark PostgreSQL 100.000 listing yang tervalidasi. Comp dan fixture memakai konten synthetic yang diberi label jelas.

## Product Principles

1. Jelaskan state dan perspektif, jangan meminta pengguna menebak arti angka atau label.
2. Fail-closed ketika value, threshold, permission, atau source belum valid.
3. Lindungi identitas lebih dulu; discovery tidak membutuhkan USN/IGN lawan.
4. Density boleh tinggi, tetapi aksi primer, hierarchy, dan scanability harus tetap jelas di mobile maupun desktop.
5. Simpan sejarah keputusan melalui versioning dan snapshot immutable.

## Accessibility & Inclusion

Alur utama memenuhi WCAG 2.2 AA: contrast, keyboard, visible focus, label, error announcement, target sentuh minimum 44×44 px, reduced motion, dan informasi status yang tidak bergantung pada warna saja.
