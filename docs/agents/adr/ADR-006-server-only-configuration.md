# ADR-006 — Secret dan konfigurasi sensitif hanya tersedia di server

**Status:** Berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** ACC-01, CAT-03, PAY-02 sampai PAY-04, seluruh kebutuhan privasi

## Keadaan

Vite mengekspos variabel client tertentu ke bundle browser. Aplikasi akan memiliki credential database, source sync, email, session, payment gateway, webhook signature, dan kemungkinan Roblox di masa depan. Menamai variabel sensitif sebagai environment client atau menyuntikkannya ke Inertia props akan membuatnya dapat dibaca siapa pun.

## Keputusan

Semua secret berada di environment server dan hanya diakses melalui konfigurasi Laravel. Tidak ada secret memakai prefix client seperti `VITE_*`, masuk ke Inertia props, HTML, JavaScript bundle, URL, log, analytics, atau response error. Client menerima allowlist konfigurasi publik yang disusun eksplisit oleh server—misalnya nama aplikasi, feature flag publik, dan nilai tampilan yang memang aman.

`.env` tidak dibaca atau dicetak agent. `.env.example` berisi key tanpa nilai sensitif. Logging, audit, exception rendering, queue payload, dan test fixture wajib meredaksi token, signature, credential, serta identitas sebelum reveal.

## Ditolak — Menaruh credential integrasi di `VITE_*`

Prefix tersebut dirancang untuk nilai yang boleh dibundle. Obfuscation atau minification bukan perlindungan; credential tetap dapat diekstrak dari asset dan digunakan di luar aplikasi.

## Ditolak — Mengirim semua konfigurasi server melalui Inertia shared props

Cara ini nyaman bagi frontend tetapi memperluas data yang terekspos pada setiap response, meningkatkan risiko cache/log leak, dan membuat review sulit membedakan nilai publik dari secret.

## Ditolak — Satu file konfigurasi berisi secret yang di-commit secara terenkripsi sendiri

Repository tetap memperoleh ciphertext dan mekanisme kunci baru yang harus dikelola. Secret manager/environment deployment lebih sesuai; repository hanya menyimpan schema/nama konfigurasi.

## Konsekuensi

- Perlu pemisahan eksplisit `public configuration` dan `server configuration` serta test yang memastikan allowlist.
- Browser tidak dapat memanggil Google Sheets, wiki bercredential, email, atau payment gateway secara privileged; semua melalui server.
- Secret rotation tidak memerlukan rebuild frontend.
- Deployment harus menyediakan secret melalui mekanisme platform di luar repository.
