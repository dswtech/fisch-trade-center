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

Target MVP berakhir pada Fase 4. Alur akun, katalog, inventory, listing, search, simulasi, negotiation, deal, dan reveal harus berjalan end-to-end. Wallet dan fee tetap nonaktif dengan `fee = 0`. Identitas lawan tetap teredaksi sampai final acceptance berhasil secara atomik. MVP siap rollout ke Linux VM, tetapi launch diblokir sampai encrypted off-host backup dan akses host tersedia.

## Positioning

Produk bukan sekadar papan listing: setiap hasil penilaian dan listing yang bernilai ditautkan pada satu published value snapshot sehingga perhitungan dapat direproduksi tanpa mencampur versi. Identitas Roblox tidak menjadi identitas publik pada discovery.

## Operating Context

- Player mendaftar dengan email/password, memverifikasi email, mengisi USN/IGN manual yang tetap berstatus `unverified`, lalu mengelola inventory.
- Player membuat listing dari inventory, mencari listing pemain lain, dan membandingkan dua sisi trade lewat simulator W/F/L.
- Discovery diawali ringkasan pencarian trade `Kamu Berikan → W/F/L → Kamu Terima` yang mengarah ke simulator, lalu dilanjutkan feed dua kolom pada desktop: penawaran anonim di kiri serta snapshot, insight item paling dicari/ditawarkan, dan rekomendasi inventory personal di kanan. Pada UI pemain, listing milik sendiri disebut `Penawaran Saya`.
- Pada halaman recommendation, setiap kandidat dapat disesuaikan dari inventory aktif: tambah/hapus item, ubah quantity, lalu tinjau modal ringkasan W/F/L sebelum proposal dikirim dan divalidasi ulang server.
- Di ruang negotiation, counteroffer memakai composer yang sama: player hanya mengubah item miliknya, sementara sisi lawan tetap read-only. Draft `LOSE` dapat dikirim sebagai permintaan non-binding agar lawan menambah item; permintaan tersebut tidak dapat diterima sebagai deal.
- Guest dan player menelusuri katalog publik melalui pencarian canonical name/alias/kategori, filter kategori, empat pilihan sort, pagination 40 item, dan halaman detail stable `public_id`.
- Header menampilkan form Login kontekstual untuk guest tanpa memindahkan halaman saat form dibuka. Login sukses kembali ke URL lokal asal. Player mendapat menu akun Profil/Logout serta bell dengan badge jumlah penuh dan popover maksimal lima tindakan trade terbaru; fitur ini tidak membuat inbox atau status read/unread.
- Administrator mengunggah CSV katalog/value ke staging, meninjau preview dan warning, lalu memublikasikan snapshot valid secara manual.
- Administrator mengelola master Rod non-tradable, relasi Rod Skin, thumbnail item, Fair threshold, dan status katalog.
- Bahasa UI pertama dipilih dari locale browser (`id` untuk Indonesia, selain itu `en`), dapat diganti, dan dipersist.
- Dark theme adalah pengalaman default; light theme tetap tersedia dan dipersist.

## Capabilities and Constraints

- Roadmap memakai Fase 0–6. Fase 0–2 mencakup fondasi, katalog/admin, inventory, listing, search, dan W/F/L. Fase 3–4 melengkapi MVP melalui negotiation, deal/reveal, dan trust operations minimum.
- Wallet, payment, refund, compensation, dan dispute finansial berada di Fase 5 pasca-MVP. Production readiness berada di Fase 6.
- Baseline Fase 5 yang belum diaktifkan memakai gateway mock, IDR, deposit Rp10.000–Rp1.000.000, dan model fee tetap; nominal fee, provider production, settlement, serta refund belum diputuskan.
- Email wajib terverifikasi sebelum inventory, listing, atau simulator privat.
- `fair_threshold_percent` awal adalah `10`; perubahan admin berlaku prospektif dan diaudit.
- Listing aktif kedaluwarsa setelah 14 hari.
- Proposal aktif kedaluwarsa setelah 24 jam dan negotiation setelah 7 hari. Scheduler Fase 3 menutup state tersebut tanpa menghapus riwayat.
- Pra-deal kedaluwarsa setelah 30 menit. Akses identity snapshot setelah reveal berlaku 30 hari; expiry tidak menghapus snapshot immutable.
- Environment local, development, staging, dan testing menyediakan halaman admin khusus untuk memicu expiry pra-deal atau akses reveal secara manual. Surface ini tidak tersedia di production dan setiap tindakan diaudit.
- Deal MVP memakai `fee = 0`, mewajibkan disclaimer non-escrow, dan baru membuka identity snapshot setelah final acceptance kedua pihak berhasil atomik.
- Production memakai Nginx, PHP 8.4-FPM, PostgreSQL 16, systemd scheduler, release symlink atomik, JSON log ke `stderr`, serta liveness `/up` dan fail-closed readiness `/ready`.
- Target recovery adalah RPO 1 jam dan RTO 4 jam. Backup lokal menyimpan 48 hourly dan 14 daily; restore drill bulanan dan encrypted off-host copy wajib sebelum launch.
- Retensi: identity snapshot/reveal entitlement 90 hari; report selesai dan negotiation terminal tanpa deal 180 hari; deal graph, suspension selesai, serta audit satu tahun. Value snapshot dan katalog historis dipertahankan. Penghapusan akun belum tersedia pada MVP.
- Moderasi manual menargetkan review awal 24 jam, memprioritaskan scam/identity misuse, tidak auto-suspend, tidak memberi kompensasi no-show, dan menerima appeal manual selama tujuh hari.
- Katalog/value berasal dari CSV UTF-8 yang diunggah admin. Upload hanya membuat staging; publish manual melakukan partial upsert dan membuat snapshot immutable secara atomik.
- Perubahan value di atas 25% menghasilkan warning yang wajib dikonfirmasi. Baris dengan value kosong menghapus value item pada snapshot baru.
- Kategori awal adalah `rod_skin`, `boat`, `relic_set`, dan `other`; model kategori tidak boleh menjadi enum tertutup.
- Rod adalah metadata non-tradable. Rod Skin tanpa relasi menampilkan `Undefined Rod`.
- Thumbnail menerima JPG/PNG/WebP maksimal 2 MB. Item tanpa thumbnail memakai fallback kategori.
- Kartu katalog memprioritaskan gambar, canonical item name, point value, dan offer range. Detail item juga menampilkan metadata Rod dan versi snapshot aktif.
- Tidak ada migration, seed, scheduler, queue worker, atau import yang dijalankan terhadap database persisten oleh agent tanpa perintah eksplisit.

## Brand Commitments

- Nama produk: Fisch Trade Center.
- Struktur UX mengambil referensi dari kepadatan, app shell, dan hierarchy Metronic Tailwind Demo 1 tanpa menyalin source, aset, logo, atau composition secara literal.
- Hierarchy katalog mengambil referensi dari pola Fisch Value List di Game.Guide—gambar dominan, nama, value, search/filter/sort, dan pagination—tanpa menyalin data, source, atau aset pihak ketiga.
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
