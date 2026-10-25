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
- Migration Fase 1 tersedia. Atas izin eksplisit pengguna, katalog dan snapshot produksi lokal diterapkan pada PostgreSQL `127.0.0.1:5432/dsw_fisch_tc`; seluruh PHPUnit dan Playwright tetap memakai SQLite disposable.

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
- Sumber produksi kategori `skin` adalah ekspor CSV publik “Value List Proto — Skins”, disetujui 25 Oktober 2026. Fetch harian berjalan pukul 02:00 Asia/Jakarta ke staging, anomaly gate menolak perubahan di atas 25%, dan snapshot hanya diterbitkan manual oleh admin.
- Provider value bersifat fail-closed sampai `VALUE_SOURCE_ENABLED=true`; konfigurasi sumber hanya tersedia di server. Boats dan Fishing Rods tetap tanpa value sampai sumbernya disetujui.

## Keputusan terbuka produk

- Provider pembayaran konkret, metode pembayaran aktif, mata uang, minimum deposit, dan kebijakan fee/refund.
- Expiry proposal/pra-deal Fase 2 serta retensi audit; expiry listing Fase 1 sudah ditutup pada 14 hari.
- Kebijakan report, no-show, dispute, suspend, dan refund biaya layanan.
- Sumber value produksi yang disetujui untuk Boats dan Fishing Rods serta kebijakan wiki sebagai sumber metadata non-value.

Keputusan terbuka di atas menahan implementasi bagian terkait, bukan penulisan fondasi aplikasi yang tidak bergantung padanya.

## Progres terakhir

- PRD, knowledge, memory, manifest, enam ADR, root agent instruction, Claude pointer, dan enam rules telah dibuat sebagai paket awal.
- Referensi PRD, panduan dokumentasi, serta struktur Value List Skins telah ditinjau pada 24 Oktober 2026.
- Pemeriksaan dokumentasi awal memiliki 68 requirement; PRD v1.1 kini memiliki 69 requirement unik setelah penambahan ACC-06, dengan distribusi `M=53`, `S=12`, `C=3`, `W=1`.
- Enam ADR masing-masing memuat keadaan, keputusan, konsekuensi, dan sedikitnya dua alternatif yang ditolak.
- Seluruh tautan relatif valid dan tidak ada dokumen agen yatim terhadap manifest.
- Core Fase 1 tersedia: authentication/verifikasi email, onboarding profil manual, shared Inertia props yang diredaksi, preferensi locale/theme, katalog, inventory CRUD, listing wizard/management berversi, cursor search, simulator W/F/L fail-closed, fixture ValueSource, konfigurasi/audit admin, dan dashboard responsif.
- Comp final disetujui di `.impeccable/mocks/hybrid-02-search-first.png`; plate original dan prompt provenance tersedia. `DESIGN.md` dan `resources/design/tokens.json` menjadi sumber design system.
- Verifikasi terakhir: PHPUnit 13.4.3 lulus 36 test/159 assertion; Vitest lulus 8/8; Playwright lulus 14/14 pada desktop dan mobile; TypeScript strict typecheck dan Vite 8.3.5 build lulus. Playwright memakai SQLite file disposable dan server lokal terisolasi.
- Harness E2E memakai SQLite file disposable, fixture testing-only, dan menguji login verified user, profil privat, inventory, listing, penerapan filter offered/wanted sebagai guest, redaksi identitas, simulator, filter sheet, keyboard/focus, theme persistence, serta responsive overflow.
- Search SRC-01 memakai item per versi yang ternormalisasi, filter offered/wanted item, kategori, offered value range, dan freshness; cursor diurutkan deterministik berdasarkan `published_at` lalu `id`. Setiap versi menyimpan snapshot serta total value-nya sendiri, sedangkan payload JSON immutable tetap dipertahankan.
- Benchmark PostgreSQL 16.4 disposable pada 100.000 listing dan 200.000 item lulus untuk seluruh matrix SRC-01. p95: baseline 18,64 ms; offered 82,55 ms; wanted 83,16 ms; kategori 97,86 ms; value range 50,83 ms; freshness 19,43 ms; gabungan 349,10 ms. Target p95 <=1 detik terpenuhi; cluster dan log terhapus setelah run.
- Visual QA 1440/768/375 tidak menemukan horizontal overflow. Detector Impeccable menemukan satu font generik dan sudah dikoreksi. Axe menemukan nama tautan/region scroll dan source sudah dikoreksi; pemeriksaan ulang belum dijalankan agar batas dua putaran QA tetap dipatuhi.
- Fondasi dokumentasi disiapkan sebagai initial commit untuk branch `main`.
- Workflow CI Windows menjalankan PHPUnit, Vitest, build Vite, dan Playwright dari lockfile. Run pertama menemukan `ext-fileinfo` belum tersedia; run kedua melewati setup PHP tetapi menemukan direktori runtime Laravel hilang pada fresh checkout. Repository sekarang melacak placeholder `.gitignore` untuk `bootstrap/cache` dan direktori runtime `storage`, sedangkan workflow memprovisikan `fileinfo`, `mbstring`, dan `pdo_sqlite` secara eksplisit. Run ketiga pada commit `38ca2fc` lulus. Discovery menampilkan state loading/error/empty yang dapat diumumkan screen reader, memvalidasi value range sebelum request, dan menyediakan recovery untuk error server.
- Fixture E2E testing-only memiliki 26 listing synthetic agar kombinasi seluruh filter SRC-01 dan kelanjutan cursor dapat diuji dari UI tanpa jaringan atau database persisten.
- Detector Impeccable menemukan side accent lama pada catatan `unverified`; border tersebut telah diganti dengan treatment outline netral. TypeScript declaration packages `@types/react@19.3.0`, `@types/react-dom@19.3.0`, dan `@types/node@22.20.5` telah disetujui, dikunci, dan dipakai oleh gate `npm run typecheck` di CI. Kontrak shared Inertia, Vite imports, CSS imports, dan nullable listing value sudah lolos strict typecheck.
- Perbaikan placeholder direktori runtime diverifikasi dengan `php artisan package:discover` dan PHPUnit 18 test/86 assertion; keduanya lulus. Eksekusi lokal memerlukan izin di luar sandbox hanya agar PHP dapat menulis cache workspace.
- UX offline/retry Fase 1 tersedia melalui status jaringan global yang aksesibel, discovery yang mempertahankan hasil dan filter, `ResilientForm` untuk mutation, serta helper retry untuk mutation router. Retry selalu menunggu tindakan pengguna dan mempertahankan UUID idempotensi selama kegagalan jaringan.
- Middleware idempotensi menyimpan fingerprint dan hasil redirect aman per session tanpa payload sensitif. Replay key yang sama tidak mengulang mutation/audit, sedangkan reuse pada method/path berbeda ditolak.
- `GoogleSheetsValueSource`, parser CSV PHP, command `values:import`, scheduler harian, staging `ValueImport`, mapping canonical/alias, anomaly gate, status/error admin, fetch manual, dan publish manual immutable/idempotent telah tersedia. Fetch gagal atau import invalid tidak mengganti snapshot aktif.
- Unit/feature test mencakup parser dan kegagalan HTTP, mapping canonical/alias/unknown/ambigu, batas anomali tepat 25% dan di atasnya, command/scheduler staging-only, publish admin/checksum/last-known-good, serta idempotensi listing/setting/import. E2E mencakup kegagalan request pertama, pemulihan jaringan, retry manual, preservasi input, dan tepat satu perubahan server.
- Detector Impeccable untuk perubahan UI offline/admin tidak menemukan temuan. Tidak ada dependency baru, commit, atau push pada pekerjaan ini.
- Sumber live diverifikasi read-only pada 25 Oktober 2026. Endpoint `/export` mengembalikan 401, sedangkan endpoint anonim `/gviz/tq` dengan `tqx=out:csv` dan `gid=1079603703` berhasil. Header aktual adalah `Name 🎣SKINS`, `Value`, dan `Offer Range`; provider berhasil mem-parsing 231 baris tanpa menulis database.
- Tiga puluh tiga baris sumber memakai point value pecahan, termasuk `7.5` dan `0.5`. Kolom snapshot/listing, validator, anomaly gate, serta kalkulator W/F/L sekarang mempertahankan presisi sampai dua angka desimal tanpa membulatkan nilai sumber.
- `.env.example` memakai `APP_URL=http://ftc.test`. Import produksi pertama telah di-stage dan dipublikasikan manual pada PostgreSQL lokal yang diizinkan pengguna.
- Preview lokal memakai cluster PostgreSQL 16 khusus proyek di `.tools/preview-postgres` pada `127.0.0.1:55432`. Lima migration telah berjalan dan fixture testing-only diisikan sekali melalui process override: 2 user, 26 listing, dan 1 snapshot. Tidak ada import value live yang dijalankan.
- Asset production dipakai secara eksplisit kecuali `VITE_DEV_SERVER=true`, sehingga environment lokal tidak bergantung pada dev server port 5173. URL asset memiliki versi `filemtime` agar browser pada origin HTTP lokal tidak mempertahankan bundle lama setelah build.
- Virtual host Nginx `ftc.test` menunjuk ke `public/` dan PHP FastCGI `127.0.0.1:9084`. Mapping hosts `127.0.0.1 ftc.test` aktif. Verifikasi domain langsung lulus untuk halaman utama, `/up`, CSS, JavaScript, data PostgreSQL, dan render visual browser; tab preview `http://ftc.test` dapat dibuka.
- Manifest terversi `database/data/skin-catalog-v1.php` berisi 231 canonical name skin dan cocok satu-per-satu dengan CSV live pada 25 Oktober 2026 (`differences=0`). Command manual `catalog:import-skins` mengimpor manifest secara atomik/idempotent, memakai alias aktif, menolak ambiguity atau item/category inaktif, dan mencatat audit sekali per versi manifest. Test mencakup sukses, replay, alias, serta rollback ambiguity.
- Pada koneksi aplikasi efektif `127.0.0.1:5432/dsw_fisch_tc`, manifest katalog kini tercatat idempotent: 231 item cocok, 0 dibuat pada replay, dan satu audit `catalog.skin_manifest.imported` tersedia. Terdapat 232 skin aktif termasuk fixture lama; sumber live tetap menghasilkan 231 mapped, 0 unmapped, dan 0 ambiguous.
- Anomaly baseline dibatasi pada snapshot published dengan `source_key` yang sama. Karena itu import pertama `google-sheets-skins` tidak dibandingkan dengan fixture, sedangkan import produksi berikutnya tetap melewati anomaly gate 25%.
- Fetch produksi satu kali berhasil membuat staging valid `a408085e-0a85-4a68-a477-462afffa9b1e`. Publish manual melalui panel admin `http://ftc.test/admin` menghasilkan snapshot ID 2 dengan 231 `catalog_item_values` dan tepat satu audit `value_snapshot.published`; snapshot tersebut menjadi latest published tanpa mengubah snapshot fixture lama.
- Akun preview synthetic dipromosikan menjadi admin dengan audit `user.role.changed` agar alur publish manual dapat diverifikasi. Provider tetap tampil `DISABLED` pada konfigurasi normal; fetch satu kali memakai process override dan tidak mengubah `.env`.
- Generator idempotency key frontend sekarang memakai `crypto.getRandomValues` dengan format UUID v4, sehingga mutation tetap bekerja pada custom origin HTTP yang tidak menyediakan `crypto.randomUUID()`. Test memverifikasi format UUID dan rotasi key setelah respons definitif.
- `PreviewAccountSeeder` menyediakan akun verified `sysadmin@ftc.test` dengan role `admin` dan `user@ftc.test` dengan role `player`. Seeder idempotent, menolak production atau database remote, telah dijalankan pada PostgreSQL lokal, dan login live `ftc.test` terverifikasi; sysadmin mendapat HTTP 200 pada `/admin`, sedangkan user mendapat HTTP 403.

## Next step

1. Set `VALUE_SOURCE_ENABLED=true` pada environment deployment dan aktifkan scheduler produksi; repository tidak mengubah `.env` atau konfigurasi scheduler host.
2. Pantau import produksi kedua untuk membuktikan anomaly gate membandingkan terhadap snapshot `google-sheets-skins` yang baru dipublikasikan.
3. Setujui sumber value untuk Boats dan Fishing Rods sebelum mengisi value kedua kategori tersebut.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, berkas/fitur yang berubah, test yang dijalankan beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.
