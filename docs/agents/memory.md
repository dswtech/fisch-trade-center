# Memory — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Memory kerja agen |
| Terakhir diperbarui | 27 Oktober 2026 |
| Pemilik | Engineering |
| Status | Aktif |

## Keadaan repository

- Branch aktif `main`; remote `origin` menunjuk ke `git@github.com:dswtech/fisch-trade-center.git`.
- Stack aplikasi: Laravel 13.38.0, Inertia 3.5.2, React 19.3.1, Tailwind CSS 4.3, dan PostgreSQL sebagai target produksi. Seluruh test backend dan E2E memakai SQLite disposable.
- Checkpoint sebelum redesign tersimpan sebagai commit `a77ac3d` (`chore: checkpoint phase two wip before catalog import redesign`). Kondisi checkpoint: Vitest 8/8 dan TypeScript lulus; PHPUnit 41 test memiliki 1 error dan 1 failure.
- Collision v8.9.5 dipasang sebagai dev dependency bersama Whoops v2.18.7. `php artisan test` kini tersedia; tidak ada package existing yang diperbarui atau dihapus.
- Environment aplikasi manual pada workstation ditetapkan sebagai `local`: URL `ftc.test` dan PostgreSQL lokal `dsw_fisch_tc`. PHPUnit/Playwright tetap memakai `testing` dengan database disposable. Pengguna mengizinkan reset serta seed ulang database local/development bila diperlukan; izin ini tidak mencakup staging atau production.

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

- Katalog publik `/catalog` kini mencari canonical name, alias, dan kategori tanpa membedakan huruf besar/kecil; memfilter empat kategori aktif; menyediakan empat urutan dengan value kosong selalu terakhir; dan memaginasi hasil di server sebanyak 40 item per halaman sambil mempertahankan query string serta browser history.
- Grid katalog memakai lima kolom wide, tiga kolom tablet, dan dua kolom mobile. Kartu menampilkan thumbnail/fallback dominan, kategori, canonical name, value PTS, offer range, serta membuka detail stabil `/catalog/{public_id}`. Detail inactive dan ID asing menghasilkan 404.
- Header publik menampilkan Login untuk guest. User terautentikasi memperoleh menu akun keyboard-accessible untuk Profil/Logout serta bell menuju negotiation dengan badge jumlah negotiation unik yang masih membutuhkan tindakan user; state expired, terminal, revealed, atau acceptance user yang sudah ada dikecualikan.
- Kartu katalog dipadatkan tanpa mengubah grid 5/3/2 kolom: artwork memakai rasio 4:3, jarak antarkartu lebih rapat, dan area metadata lebih pendek dengan teks minimum 11 px. Thumbnail dikunci secara absolut dengan inset 10 px agar intrinsic size fallback tidak memperbesar frame dan gambar terlihat penuh. Warna `option`/`optgroup` native mengikuti token tema secara global agar dropdown katalog, player, admin, dan operator tetap terbaca. Login header membuka popover desktop atau bottom sheet mobile, memfokuskan field Email, dapat ditutup dengan Escape/click-outside, dan kembali ke URL lokal asal setelah autentikasi; return URL eksternal ditolak.
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

- Kartu recommendation diubah pada 27 Oktober 2026 menjadi draft proposal interaktif per kandidat. Player dapat menambah item dari inventory, mengubah quantity dalam batas tersedia, menghapus item, dan melihat W/F/L berubah langsung. `Kirim proposal` dipindahkan ke modal ringkasan W/F/L; kedua sisi modal menampilkan thumbnail, nama, dan quantity dari snapshot listing. Server tetap memvalidasi maksimal delapan jenis item, kepemilikan, quantity, dan value snapshot. Vitest 24/24, TypeScript, Vite build, `PhaseThreeNegotiationTest` 12/12, dan regresi Playwright desktop khusus edit-summary-submit lulus. Browser desktop/mobile memverifikasi picker, perubahan FAIR ke LOSE, modal ringkasan, dan tidak ada horizontal overflow.
- Builder `Buat Penawaran` diubah pada 27 Oktober 2026 menjadi komposer `Kamu Berikan VS Kamu Cari` seperti simulator. Item dan quantity ditambah/dikurangi dinamis; picker Berikan hanya bersumber dari inventory aktif user, sedangkan picker Cari memakai katalog aktif. Desktop memakai dua sisi sejajar dan mobile menumpuk kedua sisi dengan picker bottom sheet tiga kolom. Create/edit mempertahankan payload backend lama, batas quantity inventory, total kedua sisi, serta selected state edit.
- Verifikasi builder terbaru: Vitest 23/23, TypeScript, dan Vite build lulus. Inspeksi browser aktual pada 1265×710 dan 390×844 memastikan komposer VS, quantity controls, picker desktop/mobile, serta label ketersediaan inventory bekerja. Unit test khusus membuktikan item tanpa inventory atau quantity nol tidak masuk picker Berikan.
- Halaman recommendation dipadatkan menjadi dua kartu per baris pada desktop dan satu kartu pada mobile. Thumbnail/fallback item, quantity, offer range, total kedua sisi, delta, serta badge teks W/F/L tampil langsung; URL recommendation milik sendiri kini mengarah aman ke `Penawaran Saya`.
- Feature test PhaseThreeNegotiation lulus 12/12 (219 assertion), test komponen recommendation termasuk dalam Vitest 21/21, dan regression Playwright `player-flow.spec.ts` lulus 14/14 pada desktop/mobile. Inspeksi browser aktual memastikan kartu rapat, gambar terbaca, CTA tidak overlap, dan W/F/L menonjol. Lint design-taste untuk `trade.tsx` menghasilkan 0 failure dan satu warning em dash legacy pada copy bersama.
- Discovery kini diawali ringkasan `Kamu Berikan → W/F/L → Kamu Terima` yang mengarah ke simulator. Feed `Temukan Trade` tetap berada tepat di bawahnya. Regression browser khusus Discovery lulus 12/12 dan inspeksi browser desktop memastikan kedua blok tersusun tanpa overlap.
- `PreviewExperienceSeeder` menyediakan dataset sintetis local/testing yang idempotent dan production-safe: akun preview, 32 item bernilai untuk inventory trader, 18 penawaran pasar, satu penawaran player, serta satu negotiation aktif. Seeder dijalankan pada PostgreSQL lokal atas instruksi eksplisit; keadaan setelah seed adalah 9 user, 175 inventory entry, 20 listing total, dan 1 negotiation.
- Discovery/Temukan Trade didesain ulang pada 27 Oktober 2026 menjadi feed dua kolom dengan kartu multi-item, W/F/L, cursor pagination, insight pasar global, dan maksimal tiga rekomendasi inventory personal. Rail 340 px sticky pada desktop dan berpindah setelah feed pada tablet/mobile. Guest membuka modal login tanpa kehilangan tujuan; menu pemain memakai `Penawaran Saya` dan `Buat Penawaran`.
- Perbaikan Discovery 27 Oktober 2026 memberi quick-trade kolom tengah 96 px untuk orb W/F/L non-absolute, menyelaraskan kartu hasil pertama dengan rail, membuat kedua panel rail dapat diciutkan independen, serta mengganti drawer filter dengan dialog tengah desktop/bottom sheet mobile. Bell player kini membuka popover maksimal lima tindakan trade terbaru tanpa mengubah URL; badge tetap menghitung seluruh tindakan tertunda dan payload hanya memuat alias anonim.
- Market insight hanya menghitung listing aktif, belum kedaluwarsa, dan current version; satu listing dihitung sekali per item. Recommendation memuat inventory serta value snapshot secara batch, menghormati quantity tersedia, dan tidak mengirim data inventory kepada guest. Alias publik tetap anonim dan identitas Roblox tetap teredaksi.
- Inspeksi browser terakhir pada viewport desktop dan 412×915 memastikan hierarki dua kolom, kartu ringkas, CTA, rail, bottom navigation, dan susunan mobile tampil tanpa overlap atau horizontal overflow. Lint design-taste untuk komponen Discovery menghasilkan 0 temuan.
- Shell aplikasi pemain disatukan pada 26 Oktober 2026 melalui `ProductShell`: Discovery, katalog, inventory, listing, simulator, profil, negotiation/deal, dan Trust Operations kini mempertahankan topbar, sidebar, active state, drawer, serta bottom navigation yang sama. Console admin dan time controls tetap memakai shell operator khusus.
- Balance orb pada Discovery disejajarkan dengan batas aktual kolom give/receive, termasuk pada viewport 1.800 px. Regression Playwright juga membuka seluruh menu pemain untuk memastikan shell tidak kembali berganti menjadi `module-header`/`module-nav`.
- Simulator didesain ulang pada 26 Oktober 2026 mengikuti pola kalkulator slot item: maksimal sembilan slot per sisi, picker bergambar dengan search/filter kategori, quantity/remove, live total dan persentase pembagian, serta orb W/F/L responsif. Perhitungan final tetap memakai endpoint server dan satu immutable active snapshot; branding, aset, dan sumber value pihak ketiga tidak disalin.
- Visual simulator dan picker diperiksa dua putaran pada desktop serta mobile. Perbaikan terakhir menjaga card picker tetap rapat di atas dan orb mobile tepat di tengah divider.
- `php artisan test` pada SQLite disposable: 67 test, 806 assertion, lulus. Ini mencakup idempotensi dan guard dataset preview. Collision perlu dijalankan di luar sandbox Codex lokal karena ACL sandbox menandai path bootstrap tidak writable; file dan runner proyek sendiri valid.
- Verifikasi perbaikan Discovery dan popover tindakan trade pada 27 Oktober 2026: `php artisan test` lulus 70 test/894 assertion; Vitest lulus 25/25; TypeScript strict typecheck, production build, full Playwright desktop/mobile/WCAG 34/34, dan `git diff --check` lulus. Design lint global masih melaporkan debt stylesheet lama (22 failure dan 14 warning untuk hairline, shadow, serta eyebrow), bukan regresi baru. Build tetap memberi warning Node 22.11.0 sementara Vite meminta 22.12+, dan asset `/images/trade-ocean-field.png` diselesaikan saat runtime.
- Katalog dan detail item diperiksa dalam dua putaran screenshot pada desktop serta Pixel 7. Grid 5/2 kolom, toolbar, pagination, detail, header, dan bottom navigation tidak mengalami horizontal overflow.
- Test PostgreSQL disposable dengan dua proses/koneksi lulus untuk acceptance proposal dan final acceptance deal. Deal concurrency menghasilkan status `revealed`, tepat 2 acceptance, 2 identity snapshot, 2 reveal entitlement, dan 1 audit reveal. Benchmark 100.000 listing terbaru mencatat p95 matrix search maksimum `527,23 ms` dan p95 insight pasar `420,95 ms`, di bawah target `1.000 ms`.
- Syntax PHP dan Bash, production config cache, route cache, YAML CI, link Markdown, manifest dokumentasi, dan `git diff --check` lulus. GitHub Actions run `40285977892` lulus untuk job aplikasi Windows dan `production-operations` Linux/PostgreSQL 16, termasuk migration disposable, concurrency, benchmark, backup checksum/row-count, dan restore drill.
- Impeccable detector pada JSX/CSS katalog menghasilkan 0 temuan. Audit design-taste seluruh CSS global masih memuat temuan legacy untuk gradient, hairline, shadow, dan eyebrow di luar scope redesign katalog.
- Regression Playwright khusus katalog/login lulus 6/6 pada desktop dan mobile. QA browser memastikan katalog lebih padat dan popover desktop tetap menampilkan konteks katalog; inspector app masih melaporkan gradient shell, effect tombol, ukuran judul, dan bottom-nav edge spacing yang merupakan baseline global sebelum perubahan ini.
- Automated test tetap memakai SQLite disposable untuk E2E. PostgreSQL lokal hanya ditulis untuk migration/cleanup yang pernah diotorisasi dan dataset preview sintetis terbaru; staging serta production tidak ditulis.
- Build lokal memakai Node 22.11.0 dan memberi warning karena Vite meminta 22.12+; build tetap berhasil. Asset `/images/trade-ocean-field.png` diselesaikan saat runtime.
- Audit statis memetakan seluruh 76 requirement aktif tepat ke satu fase utama, termasuk CAT-07 pada Fase 1, SRC-06 pada Fase 2, serta REC-07, REC-08, dan NEG-09–NEG-11 pada Fase 3. Bukti diperiksa melalui route, controller/service, UI, migration, dan test; hasilnya dicatat di `development-phases.md`.
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
- Implementasi Fase 6 dipush ke `origin/main` sebagai commit `6251761` (`feat: prepare phase six production operations`); kedua job CI lulus.

## Keputusan terbuka produk

- Provider/metode/settlement pembayaran production, nominal fee tetap, expiry quote, refund, reconciliation, dan dispute finansial.
- Provider storage off-host terenkripsi dan kredensial akses Linux VM.

## Next step

1. Redesign simulator, katalog, Discovery dua kolom, counteroffer, dan popover tindakan trade telah dipush ke `origin/main` sebagai commit `d034471` (`feat: refine trade discovery and negotiation experience`).
2. Sediakan akses Linux VM, encrypted off-host backup, dan provider-neutral monitor; jalankan restore drill dari off-host copy.
3. Migration persisten dan deployment nyata tetap hanya boleh dijalankan setelah pengguna memberi instruksi eksplisit.
4. Fase 5 tetap tidak dimulai sebelum sisa keputusan provider, nominal fee, refund, dan reconciliation ditutup.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, fitur yang berubah, test beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.

## Implementasi counteroffer terstruktur — 27 Oktober 2026

- Counteroffer memakai composer dua sisi seperti pembuatan proposal: sisi pengguna editable dari inventory, sisi lawan read-only, W/F/L langsung, picker bergambar, dan submit melalui modal ringkasan.
- Draft `LOSE` dapat dikirim sebagai permintaan non-binding `request_more_items`. Penerima tidak dapat Accept; ia hanya dapat menambah item dan membalas melalui counteroffer normal atau menolak permintaan.
- Proposal version menyimpan `intent`; counter menyertakan active `proposalId` dan server membaca sisi lawan di bawah lock agar versi basi tidak menimpa komposisi terbaru. Migration `2026_10_27_000100_add_intent_to_proposal_versions` diterapkan ke PostgreSQL lokal `dsw_fisch_tc` pada 27 Oktober 2026 atas izin eksplisit pengguna; staging dan production tidak disentuh.
- Verifikasi akhir: `php artisan test` lulus 69 test/866 assertion; Vitest lulus 25 test; TypeScript dan production build lulus; Playwright desktop/mobile termasuk WCAG lulus 34 test. Lint design-taste tidak memiliki failure. Build tetap memberi warning environment lokal Node 22.11.0 (Vite meminta 22.12+) dan resolusi runtime `/images/trade-ocean-field.png`.

## Perbaikan Discovery dan tindakan trade — 27 Oktober 2026

- Quick trade memakai grid `1fr 96px 1fr`; orb W/F/L menempati kolom tengah tanpa posisi absolut. Pemeriksaan browser pada viewport 1.280 px membuktikan kedua sisi sama tinggi, celah orb 6 px dari setiap sisi, heading hasil dan rail sejajar, serta tidak ada overflow horizontal.
- `Insight Pasar` dan `Cocok untuk Inventory-mu` dapat dilipat secara independen selama halaman aktif. Filter menjadi dialog tengah desktop selebar maksimum 520 px dan bottom sheet penuh pada mobile, dengan focus trap, Escape, click-outside, scroll lock, serta focus return.
- Bell menjadi popover tanpa navigasi. Payload hanya memuat maksimal lima tindakan terbaru yang sudah dideduplikasi per negotiation, sementara badge tetap memakai jumlah penuh. Alias anonim, stage, waktu, dan tujuan negotiation/deal tersedia tanpa mengekspos identitas Roblox.
- Tidak ada dependency baru. Setelah error runtime menunjukkan kolom `p.intent` belum tersedia, migration `proposal_versions.intent` diterapkan ke PostgreSQL lokal atas izin eksplisit pengguna. Status migration menjadi batch 3 `Ran`; regression `CatalogBrowsingTest` lulus 5 test/180 assertion. Rollout staging dan production tetap terpisah.
