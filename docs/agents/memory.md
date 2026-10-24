# Memory — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Memory kerja agen |
| Terakhir diperbarui | 25 Oktober 2026 |
| Pemilik | Engineering |
| Status | Aktif |

## Keadaan repository

- Repository diawali dalam keadaan kosong, lalu diinisialisasi sebagai Git repository dengan branch `main` dan remote `origin` menuju `git@github.com:dswtech/fisch-trade-center.git`.
- Aplikasi Laravel 13.38.0 + Inertia 3.5.2 + React 19.3.1 telah di-scaffold dan dependency exact dikunci di lockfile.
- Node 22.23.4/npm 10.9.9 tersedia terisolasi di `.tools` (diabaikan Git) karena NVM lokal macet; build final dijalankan langsung dengan binary tersebut.
- Migration Fase 1 telah dibuat tetapi tidak pernah dijalankan terhadap database persisten. Seluruh feature test memakai SQLite `:memory:` disposable.

## Keputusan aktif

- Arsitektur target: Laravel 13 + Inertia + React 19.3 dalam satu aplikasi; Node.js hanya toolchain build frontend.
- UI target: Tailwind CSS 4.3 dan shadcn/ui stable terbaru saat bootstrap.
- Kategori awal: Skins, Boats, dan Fishing Rods dengan model katalog generik.
- Value disinkronkan server-side dari sumber eksternal, divalidasi, lalu dipublikasikan sebagai snapshot immutable.
- Authentication memakai email/password; USN dan IGN dimasukkan manual tanpa verifikasi Roblox pada fase awal.
- Negosiasi hanya memakai proposal/counteroffer terstruktur, tanpa chat bebas.
- W/F/L memakai ambang Fair awal ±10%; perubahan admin bersifat prospektif dan diaudit.
- Email harus terverifikasi sebelum inventory, listing, atau simulator privat. Listing Fase 1 kedaluwarsa setelah 14 hari.
- PostgreSQL adalah target produksi; SQLite hanya untuk test disposable (ADR-007).
- Arah UI final adalah comp search-first: struktur listing A dipadukan atmosfer laut B, dark-first dengan light mode dan bilingual Indonesia–English.
- Biaya dipotong saat kedua pihak melakukan final acceptance; identitas baru dibuka setelah finalisasi atomik berhasil.
- Wallet menggunakan ledger immutable dan gateway abstraction.

## Keputusan terbuka produk

- Provider pembayaran konkret, metode pembayaran aktif, mata uang, minimum deposit, dan kebijakan fee/refund.
- Expiry proposal/pra-deal Fase 2 serta retensi audit; expiry listing Fase 1 sudah ditutup pada 14 hari.
- Kebijakan report, no-show, dispute, suspend, dan refund biaya layanan.
- Cara memperoleh dan syarat penggunaan sumber sinkronisasi Value List dalam produksi.

Keputusan terbuka di atas menahan implementasi bagian terkait, bukan penulisan fondasi aplikasi yang tidak bergantung padanya.

## Progres terakhir

- PRD, knowledge, memory, manifest, enam ADR, root agent instruction, Claude pointer, dan enam rules telah dibuat sebagai paket awal.
- Referensi PRD, panduan dokumentasi, serta struktur Value List Skins telah ditinjau pada 24 Oktober 2026.
- Pemeriksaan dokumentasi awal memiliki 68 requirement; PRD v1.1 kini memiliki 69 requirement unik setelah penambahan ACC-06, dengan distribusi `M=53`, `S=12`, `C=3`, `W=1`.
- Enam ADR masing-masing memuat keadaan, keputusan, konsekuensi, dan sedikitnya dua alternatif yang ditolak.
- Seluruh tautan relatif valid dan tidak ada dokumen agen yatim terhadap manifest.
- Core Fase 1 tersedia: authentication/verifikasi email, onboarding profil manual, shared Inertia props yang diredaksi, preferensi locale/theme, katalog, inventory CRUD, listing wizard/management berversi, cursor search, simulator W/F/L fail-closed, fixture ValueSource, konfigurasi/audit admin, dan dashboard responsif.
- Comp final disetujui di `.impeccable/mocks/hybrid-02-search-first.png`; plate original dan prompt provenance tersedia. `DESIGN.md` dan `resources/design/tokens.json` menjadi sumber design system.
- Verifikasi terakhir: PHPUnit 13.4.3 lulus 18 test/86 assertion; Vitest 5.0.5 lulus 6 test; Playwright lulus 10/10 pada desktop dan mobile; Vite 8.3.5 build lulus dengan Node 22.23.4/npm 10.9.9.
- Harness E2E memakai SQLite file disposable, fixture testing-only, dan menguji login verified user, profil privat, inventory, listing, penerapan filter offered/wanted sebagai guest, redaksi identitas, simulator, filter sheet, keyboard/focus, theme persistence, serta responsive overflow.
- Search SRC-01 memakai item per versi yang ternormalisasi, filter offered/wanted item, kategori, offered value range, dan freshness; cursor diurutkan deterministik berdasarkan `published_at` lalu `id`. Setiap versi menyimpan snapshot serta total value-nya sendiri, sedangkan payload JSON immutable tetap dipertahankan.
- Benchmark PostgreSQL 16.4 disposable pada 100.000 listing dan 200.000 item lulus untuk seluruh matrix SRC-01. p95: baseline 18,64 ms; offered 82,55 ms; wanted 83,16 ms; kategori 97,86 ms; value range 50,83 ms; freshness 19,43 ms; gabungan 349,10 ms. Target p95 <=1 detik terpenuhi; cluster dan log terhapus setelah run.
- Visual QA 1440/768/375 tidak menemukan horizontal overflow. Detector Impeccable menemukan satu font generik dan sudah dikoreksi. Axe menemukan nama tautan/region scroll dan source sudah dikoreksi; pemeriksaan ulang belum dijalankan agar batas dua putaran QA tetap dipatuhi.
- Fondasi dokumentasi disiapkan sebagai initial commit untuk branch `main`.
- Workflow CI Windows menjalankan PHPUnit, Vitest, build Vite, dan Playwright dari lockfile. Run pertama menemukan `ext-fileinfo` belum tersedia pada runner; workflow sekarang memprovisikan `fileinfo`, `mbstring`, dan `pdo_sqlite` secara eksplisit. Discovery menampilkan state loading/error/empty yang dapat diumumkan screen reader, memvalidasi value range sebelum request, dan menyediakan recovery untuk error server.
- Fixture E2E testing-only memiliki 26 listing synthetic agar kombinasi seluruh filter SRC-01 dan kelanjutan cursor dapat diuji dari UI tanpa jaringan atau database persisten.
- Detector Impeccable menemukan side accent lama pada catatan `unverified`; border tersebut telah diganti dengan treatment outline netral. `tsc --noEmit` belum menjadi gate karena React/Vite declaration packages tidak termasuk dependency yang disetujui dan source lama masih memiliki implicit type errors; tidak ada dependency baru yang dipasang.

## Next step

1. Setujui React/Vite declaration packages dan rapikan implicit type errors sebelum menambahkan TypeScript typecheck ke CI.
2. Tambahkan pemeriksaan error jaringan browser bila aplikasi menetapkan UX offline/retry yang lebih spesifik.
3. Hubungkan provider value produksi hanya setelah izin dan akses sumber disetujui.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, berkas/fitur yang berubah, test yang dijalankan beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.
