# Memory — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Memory kerja agen |
| Terakhir diperbarui | 25 Oktober 2026 |
| Pemilik | Engineering |
| Status | Aktif |

## Keadaan repository

- Branch aktif `main`; remote `origin` menunjuk ke `git@github.com:dswtech/fisch-trade-center.git`.
- Stack aplikasi: Laravel 13.38.0, Inertia 3.5.2, React 19.3.1, Tailwind CSS 4.3, dan PostgreSQL sebagai target produksi. Seluruh test backend dan E2E memakai SQLite disposable.
- Checkpoint sebelum redesign tersimpan sebagai commit `a77ac3d` (`chore: checkpoint phase two wip before catalog import redesign`). Kondisi checkpoint: Vitest 8/8 dan TypeScript lulus; PHPUnit 41 test memiliki 1 error dan 1 failure.
- Tidak ada dependency baru untuk redesign katalog. `php artisan test` tidak tersedia karena repo tidak memasang Collision; suite dijalankan langsung dengan PHPUnit.

## Keputusan aktif

- Kategori trade awal hanya `rod_skin`, `boat`, `relic_set`, dan `other` dengan label Rod Skin, Boats, Relic & Sets, dan Lain-lain.
- Rod adalah master data non-tradable. Relasi Rod Skin ke Rod nullable dan ditampilkan sebagai `Undefined Rod` jika belum diisi.
- Akuisisi katalog/value memakai upload CSV UTF-8 manual oleh admin. Upload hanya membuat staging dan preview; publish manual membuat snapshot immutable baru secara atomik.
- Import CSV bersifat partial upsert. Baris yang tidak dikirim tetap dibawa dari snapshot aktif; value kosong menghapus value item pada snapshot baru; perubahan di atas 25% menjadi warning yang perlu dikonfirmasi.
- `catalog_items.public_id` adalah `item_key` stabil. Template terkini dapat diunduh kembali dari admin dan menetralkan awalan formula spreadsheet.
- Thumbnail dikelola manual per item pada filesystem disk configurable, menerima JPG/PNG/WebP maksimal 2 MB. Item tanpa thumbnail memakai asset fallback kategorinya.
- Authentication memakai email/password; USN dan IGN dimasukkan manual dan tetap privat. Negosiasi tidak memakai chat bebas.
- W/F/L memakai ambang Fair awal ±10%; perubahan admin prospektif dan diaudit.
- PostgreSQL adalah database persisten; SQLite hanya untuk test disposable. Operasi tulis database persisten memerlukan instruksi eksplisit.
- Windows Scheduled Task `FischTradeCenterScheduler` tetap memanggil `schedule:run`, tetapi aplikasi tidak lagi mendaftarkan job import value.
- Roadmap aktif memakai tujuh fase capability. Target MVP berakhir pada Fase 4 dengan wallet/fee nonaktif dan `fee = 0`; Fase 5 adalah monetisasi pasca-MVP, sedangkan Fase 6 adalah production readiness.
- PRD utama berada di `docs/agents/prd/README.md`; manifest dokumentasi menjadi pintu masuk untuk urutan baca.

## Pekerjaan redesign katalog yang terverifikasi

- Endpoint admin tersedia untuk unduh template, staging CSV, publish, master Rod, assignment Rod Skin, serta upload/replace/delete thumbnail.
- Fetch Google Sheets, konfigurasi sumber, command `values:import`, registrasi schedule, manifest Skin lama, dan jalur metadata eksternal telah dihapus.
- DTO katalog, inventory, listing, dan discovery memakai resolver thumbnail yang sama. Katalog menampilkan metadata Rod dan fallback `Undefined Rod`.
- Seeder/fixture/filter/copy trade tidak lagi menawarkan Fishing Rod sebagai item tradable.
- Cleanup persisten yang diotorisasi dijalankan setelah verifikasi ulang tepat: 1 item Fishing Rod, 0 inventory entry, 1 value, 26 referensi listing, dan 26 listing. Dataset synthetic tersebut dihapus dalam satu transaksi; migration skema redesign belum dijalankan pada database persisten.
- Guard cleanup tersedia sebagai command one-time dan menolak eksekusi jika hitungan target berubah. Test disposable memverifikasi jalur exact-count transaksional.
- PRD dinaikkan ke revisi 1.3. ADR-008 menggantikan bagian akuisisi terjadwal ADR-002, sedangkan prinsip staging dan snapshot immutable dipertahankan.

## Verifikasi terakhir

- PHPUnit pada SQLite `:memory:`: 30 test, 169 assertion, lulus.
- Vitest lulus 9/9; TypeScript strict typecheck lulus; Vite build lulus; Playwright desktop/mobile lulus 16/16 termasuk WCAG A/AA.
- Build lokal memakai Node 22.11.0 dan memberi warning karena Vite meminta 22.12+; build tetap berhasil. Asset `/images/trade-ocean-field.png` diselesaikan saat runtime.
- Audit statis 25 Oktober 2026 memetakan seluruh 69 requirement aktif tepat ke satu fase utama. Bukti diperiksa melalui route, controller/service, UI, migration, dan test; hasilnya dicatat di `development-phases.md`.
- Verifikasi suite tidak diulang saat audit roadmap karena `vendor/autoload.php` tidak tersedia. Ini tidak mengganti hasil verifikasi terakhir di atas.

## Gap implementasi per fase

- Fase 0 hampir selesai; rate limit endpoint privat belum merata dan working tree redesign katalog belum di-commit.
- Fase 1 hampir selesai; route/UI status-alias katalog, lifecycle master Rod, dan failure-injection test publish masih kurang. Migration redesign belum dijalankan pada database persisten.
- Fase 2 parsial kuat; route GET edit listing, INV-04, koneksi kontrol homepage, dan coverage invalidasi/rate limit masih kurang.
- Fase 3 baru fondasi; schema/service/UI tersedia tetapi route/controller/presenter, integrasi invalidator/expiry, dan seluruh test negotiation belum tersedia.
- Fase 4 baru fondasi; deal dan trust-operation controller belum terhubung ke route/UI/middleware serta belum memiliki test atomicity, concurrency, dan privacy reveal.
- Fase 5 belum dimulai dan bukan gate MVP. Fase 6 belum siap karena keputusan recovery/retention, monitoring, runbook, rollout, dan re-verifikasi masih terbuka.

## Keputusan terbuka produk

- Provider pembayaran konkret, metode pembayaran aktif, mata uang, minimum deposit, dan kebijakan fee/refund.
- Expiry proposal/negotiation Fase 3, expiry pra-deal/reveal Fase 4, serta retensi audit.
- Kebijakan report, no-show, dispute, suspend, dan refund biaya layanan.

## Next step

1. Stabilkan dan commit redesign katalog tanpa menjalankan migration persisten.
2. Tutup gap Fase 1–2, lalu hubungkan Fase 3 dan Fase 4 end-to-end sesuai urutan backlog di `development-phases.md`.
3. Jalankan migration redesign pada database persisten hanya setelah pengguna memberi instruksi eksplisit.
4. Operator mengunduh template terkini sebelum setiap batch upload agar selalu membawa `item_key` stabil.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, fitur yang berubah, test beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.
