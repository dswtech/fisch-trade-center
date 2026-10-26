# Memory — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Memory kerja agen |
| Terakhir diperbarui | 26 Oktober 2026 |
| Pemilik | Engineering |
| Status | Aktif |

## Keadaan repository

- Branch aktif `main`; remote `origin` menunjuk ke `git@github.com:dswtech/fisch-trade-center.git`.
- Stack aplikasi: Laravel 13.38.0, Inertia 3.5.2, React 19.3.1, Tailwind CSS 4.3, dan PostgreSQL sebagai target produksi. Seluruh test backend dan E2E memakai SQLite disposable.
- Checkpoint sebelum redesign tersimpan sebagai commit `a77ac3d` (`chore: checkpoint phase two wip before catalog import redesign`). Kondisi checkpoint: Vitest 8/8 dan TypeScript lulus; PHPUnit 41 test memiliki 1 error dan 1 failure.
- Collision v8.9.5 dipasang sebagai dev dependency bersama Whoops v2.18.7. `php artisan test` kini tersedia; tidak ada package existing yang diperbarui atau dihapus.

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
- Expiry ditetapkan: proposal aktif 24 jam, negotiation 7 hari, pra-deal 30 menit, dan reveal entitlement 30 hari. Scheduler mendaftarkan job Fase 3 dan Fase 4 setiap menit.
- Environment `local`, `development`, `staging`, dan `testing` menyediakan `/admin/time-controls` khusus admin untuk memicu expiry pra-deal atau reveal entitlement secara manual. Semua trigger diaudit dan route tidak didaftarkan pada production.
- Roadmap aktif memakai tujuh fase capability. Target MVP berakhir pada Fase 4 dengan wallet/fee nonaktif dan `fee = 0`; Fase 5 adalah monetisasi pasca-MVP, sedangkan Fase 6 adalah production readiness.
- PRD utama berada di `docs/agents/prd/README.md`; manifest dokumentasi menjadi pintu masuk untuk urutan baca.
- Fase 6 memakai baseline Linux VM: Nginx, PHP 8.4-FPM, PostgreSQL 16, systemd scheduler, release symlink atomik, shared `.env`/storage, JSON log `stderr`, RPO 1 jam, dan RTO 4 jam.
- Fase 5 tetap ditunda. Baseline development yang dicatat adalah gateway mock, IDR, deposit Rp10.000–Rp1.000.000, dan model fee tetap; nominal fee serta provider/metode/settlement/refund production belum diputuskan.

## Pekerjaan redesign katalog yang terverifikasi

- Endpoint admin tersedia untuk unduh template, staging CSV, publish, master Rod, assignment Rod Skin, serta upload/replace/delete thumbnail.
- Fetch Google Sheets, konfigurasi sumber, command `values:import`, registrasi schedule, manifest Skin lama, dan jalur metadata eksternal telah dihapus.
- DTO katalog, inventory, listing, dan discovery memakai resolver thumbnail yang sama. Katalog menampilkan metadata Rod dan fallback `Undefined Rod`.
- Seeder/fixture/filter/copy trade tidak lagi menawarkan Fishing Rod sebagai item tradable.
- Cleanup persisten yang diotorisasi dijalankan setelah verifikasi ulang tepat: 1 item Fishing Rod, 0 inventory entry, 1 value, 26 referensi listing, dan 26 listing. Dataset synthetic tersebut dihapus dalam satu transaksi.
- Guard cleanup tersedia sebagai command one-time dan menolak eksekusi jika hitungan target berubah. Test disposable memverifikasi jalur exact-count transaksional.
- PRD dinaikkan ke revisi 1.3. ADR-008 menggantikan bagian akuisisi terjadwal ADR-002, sedangkan prinsip staging dan snapshot immutable dipertahankan.

## Penutupan gap Fase 0–2

- Mutation authentication, preference/profile, inventory/listing, dan admin diberi rate limit; feature test membuktikan respons 429 setelah batas terlewati.
- Admin dapat mengubah alias/status katalog serta rename/deactivate Rod melalui route dan UI. Mutation diaudit; Rod inactive tidak dapat dipilih untuk assignment baru.
- Publish CSV mempunyai failure-injection test yang membuktikan item, snapshot, value, dan audit event rollback ketika insert value gagal.
- Halaman edit listing kini mempunyai route. Pengurangan inventory diuji menginvalidasi listing yang tidak lagi dapat dipenuhi.
- Homepage aktif memakai data nyata, menyembunyikan badge synthetic secara default, dan mengganti rekomendasi palsu dengan jalur simulator yang operasional.
- INV-04 belum memerlukan implementasi tambahan karena tidak ada kategori aktif dengan atribut wajib. Atribut baru harus ditambahkan ketika aturan kategori konkret disetujui.
- Lima migration pending dari `2026_10_25_000100` sampai `2026_10_26_000100` dijalankan pada database persisten sebagai batch 2 setelah otorisasi eksplisit. `migrate:status` memastikan semuanya berstatus `Ran`.
- `PRODUCT.md` diselaraskan dengan roadmap Fase 0–6, CSV manual, kategori aktif, Rod non-tradable, thumbnail, dan batas MVP tanpa pembayaran.

## Penutupan Fase 4

- Route/UI deal telah terhubung dari server choice, persetujuan server, final acceptance ber-disclaimer, sampai reveal identity snapshot kedua pihak dengan `fee = 0`.
- Finalisasi melakukan revalidasi proposal, pra-deal, listing, inventory, snapshot, serta feature flag wallet/fee dalam transaksi yang sama. Retry menghasilkan satu deal dan satu pasangan entitlement.
- Identity lawan tetap tersembunyi sebelum final acceptance kedua pihak. Perubahan profil setelah reveal tidak mengubah identity snapshot historis.
- `PhaseFourExpiryService` menangani expiry otomatis. Halaman admin non-production menangani expiry manual pra-deal dan reveal entitlement tanpa menghapus snapshot.
- Konfigurasi koneksi PostgreSQL menetapkan timezone session `UTC`; ini mencegah timestamp tanpa offset dibaca sebagai waktu server Asia/Jakarta pada concurrency test.
- Final acceptance bersamaan diuji dengan dua proses/koneksi PostgreSQL: tepat dua acceptance, satu finalisasi/reveal audit, dua identity snapshot, dan dua reveal entitlement.

## Production readiness Fase 6

- `EnsureCorrelationId` dan security headers aktif global. Production menambah CSP nonce serta HSTS untuk HTTPS; template production memakai cookie secure/HTTP-only/SameSite, database session/cache, JSON log `stderr` level `info`, wallet dan fee tetap nonaktif.
- `/up` tetap menjadi liveness. `/ready` fail-closed terhadap PostgreSQL, scheduler heartbeat lebih tua dari tiga menit, catalog storage tidak writable, dan disk bebas kurang dari 2 GB. Scheduler menulis heartbeat setiap menit.
- `ops:retention` dry-run secara default dan hanya memutasi dengan `--execute` ketika `RETENTION_ENABLED=true`. Identity/reveal dipangkas 90 hari; report selesai dan negotiation terminal tanpa deal 180 hari; deal graph, suspension selesai, dan audit satu tahun. Report aktif serta katalog/value historis dipertahankan. Test mencakup batas tanggal, replay, graph dependency-order, dan rollback paksa per chunk.
- Queue report memprioritaskan `suspected_scam`/`identity_misuse`, menandai SLA review 24 jam pada DTO, dan hanya mengizinkan pembukaan ulang manual ke `reviewing` dalam tujuh hari. Tidak ada auto-suspend atau kompensasi no-show.
- Artefak `ops/linux/` menyediakan Nginx, systemd scheduler/backup timer, backup PostgreSQL custom-format plus thumbnail/checksum/row-count, restore drill disposable, dan deploy/rollback symlink. Deploy menolak launch tanpa deklarasi off-host backup siap dan menolak migration tanpa flag otorisasi eksplisit.
- CI memiliki job Ubuntu + PostgreSQL 16 untuk migration, production config/route cache, larangan time controls production, syntax script, concurrency, benchmark 100.000 listing, serta backup/restore drill.
- ADR-009 dan runbook deployment, rollback, backup/restore, incident response, monitoring, retention, serta gerbang launch telah ditambahkan. Status Fase 6 adalah `Ready for rollout — blocked by off-host backup` karena encrypted off-host storage dan akses host belum tersedia.

## Verifikasi terakhir

- `composer test` pada SQLite `:memory:`: 57 test, 571 assertion, lulus.
- Vitest lulus 14/14; TypeScript strict typecheck lulus; Vite build lulus; Playwright desktop/mobile lulus 22/22 termasuk deal/reveal dua participant, halaman time controls admin, dan WCAG A/AA.
- Test PostgreSQL disposable dengan dua proses/koneksi lulus untuk acceptance proposal dan final acceptance deal. Deal concurrency menghasilkan status `revealed`, tepat 2 acceptance, 2 identity snapshot, 2 reveal entitlement, dan 1 audit reveal. Benchmark 100.000 listing terbaru mencatat p95 maksimum `357,33 ms`, di bawah target `1.000 ms`.
- Syntax PHP dan Bash, production config cache, route cache, YAML CI, link Markdown, manifest dokumentasi, dan `git diff --check` lulus. Backup/restore Linux belum dieksekusi lokal; job CI Linux yang baru akan menjadi bukti eksekusinya setelah commit dipush.
- Lint design-taste pada halaman trade/time controls menghasilkan 0 fail dan 1 warning untuk penggunaan em-dash sebagai simbol nilai kosong/label.
- Build lokal memakai Node 22.11.0 dan memberi warning karena Vite meminta 22.12+; build tetap berhasil. Asset `/images/trade-ocean-field.png` diselesaikan saat runtime.
- Audit statis memetakan seluruh 69 requirement aktif tepat ke satu fase utama. Bukti diperiksa melalui route, controller/service, UI, migration, dan test; hasilnya dicatat di `development-phases.md`.
- `composer validate --strict` menyatakan schema valid, dengan warning existing untuk exact constraint Laravel dan Inertia.

## Gap implementasi per fase

- Fase 0 hampir selesai; gap rate limit mutation tertutup dan checkpoint Fase 0–2 sudah di-push ke `main` sebagai commit `1c47c70`.
- Fase 1 hampir selesai; gap route/UI status-alias, lifecycle Rod, pembuktian rollback publish, dan rollout migration persisten tertutup.
- Fase 2 hampir selesai; route edit, invalidasi listing, rate limit mutation, dan kontrol homepage tertutup. INV-04 tidak aktif sampai ada aturan atribut kategori konkret.
- Fase 3 selesai; recommendation dan negotiation telah terhubung end-to-end melalui HTTP/UI, participant authorization, history proposal, stale-version rejection, retry idempotent, invalidasi inventory/listing, redaction, scheduler expiry, dan test concurrency PostgreSQL dua koneksi.
- Implementasi Fase 3 tersebut di-push ke `origin/main` sebagai commit `0d4a446` (`feat: connect phase three negotiation flow`).
- Fase 4 selesai: deal/reveal, expiry otomatis, time controls admin non-production, report participant, queue audit operator, update status report, suspend/lift, authorization, rate limit, dan blokir trade mutation untuk akun suspended sudah terhubung ke route/UI serta diuji.
- Fase 5 belum dimulai dan bukan gate MVP. Keputusan awal gateway mock/IDR/batas deposit/model fee sudah dicatat, tetapi keputusan production finansial masih terbuka.
- Fase 6 ready for rollout tetapi launch tetap diblokir encrypted off-host backup, akses host, konfigurasi monitor eksternal, dan restore drill off-host.

## Keputusan terbuka produk

- Provider/metode/settlement pembayaran production, nominal fee tetap, expiry quote, refund, reconciliation, dan dispute finansial.
- Provider storage off-host terenkripsi dan kredensial akses Linux VM.

## Next step

1. Commit/push perubahan Fase 6, lalu pastikan job CI Linux backup/restore lulus.
2. Sediakan akses Linux VM, encrypted off-host backup, dan provider-neutral monitor; jalankan restore drill dari off-host copy.
3. Migration persisten dan deployment nyata tetap hanya boleh dijalankan setelah pengguna memberi instruksi eksplisit.
4. Fase 5 tetap tidak dimulai sebelum sisa keputusan provider, nominal fee, refund, dan reconciliation ditutup.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, fitur yang berubah, test beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.
