# Roadmap Fase Development — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Roadmap fase development dan audit gap implementasi |
| Versi | v1.5 |
| Tanggal audit | 27 Oktober 2026 |
| Pemilik | Product dan Engineering |
| Status | Aktif |

Dokumen ini menerjemahkan requirement PRD menjadi urutan delivery. Definisi requirement tetap hanya berada di PRD. Status fase ditentukan dari jalur HTTP yang dapat digunakan, UI operasional bila diperlukan, authorization, dan automated test; schema atau service yang belum terhubung hanya dihitung sebagai fondasi parsial.

Target MVP mencakup Fase 0–4 dengan wallet dan fee nonaktif serta `fee = 0`. Fase 5 adalah monetisasi pasca-MVP. Fase 6 merupakan gerbang production dan tidak menggantikan gerbang kualitas pada fase sebelumnya.

## Status ringkas

| Fase | Requirement utama | Dependency | Status audit |
|---|---|---|---|
| 0 — Fondasi & Security Baseline | ACC-01–ACC-06 | Tidak ada | Hampir selesai |
| 1 — Catalog Data & Admin | CAT-01–CAT-07, AUD-04, ADM-01, ADM-03 | Fase 0 | Hampir selesai |
| 2 — Player Trade Core | INV-01–INV-04, LST-01–LST-06, SRC-01–SRC-06 | Fase 0–1 | Hampir selesai; Discovery memakai quick-trade tiga kolom, rail collapsible, dan dialog filter responsif. |
| 3 — Recommendation & Negotiation | REC-01–REC-08, NEG-01–NEG-07, NEG-09–NEG-11 | Fase 0–2 | Selesai; tindakan tertunda tersedia sebagai badge dan popover lima item terbaru. |
| 4 — Pre-deal, Reveal & Trust Operations | NEG-08, DEL-01–DEL-08, AUD-01–AUD-03, ADM-02, ADM-04–ADM-05 | Fase 0–3 | Selesai |
| 5 — Wallet, Payment & Dispute Finansial | WAL-01–WAL-06, PAY-01–PAY-05 | Fase 0–4 dan keputusan produk pembayaran | Belum dimulai; bukan gate MVP |
| 6 — Production Readiness & Launch | Seluruh NFR dan gerbang operasional | Fase 0–4 untuk MVP; Fase 5 untuk monetisasi | Ready for rollout — blocked by off-host backup |

Pemetaan di atas menempatkan seluruh 76 requirement aktif tepat pada satu fase utama. Dependency lintas fase tidak mengubah kepemilikan requirement.

## Bukti inspeksi

| Fase | Route/UI | Implementation path | Test yang tersedia |
|---|---|---|---|
| 0 | Route authentication, verification, profile, dan preferences di `routes/web.php`; mutation privat memakai rate limit | `AuthController`, middleware idempotensi, `bootstrap/app.php`, `.github/workflows/ci.yml` | Authentication, idempotensi, dan rate limit mutation di `tests/Feature/ExampleTest.php` |
| 1 | Route katalog publik/list item dan route admin import, lifecycle Rod, metadata katalog, serta thumbnail di `routes/web.php`; UI `workspace.tsx` dan panel `admin.tsx` | `PhaseOnePageController`, `app/Domain/Catalog`, controller admin katalog, migration Rod/thumbnail | `CatalogBrowsingTest.php`, `CatalogValueCsvImportTest.php` termasuk failure injection publish, serta test React katalog/admin |
| 2 | Route inventory, listing termasuk halaman edit, search, dan simulator di `routes/web.php`; Discovery dua kolom menampilkan feed serta insight pasar current-version | Controller inventory/listing/search, `DiscoveryPresenter`, `MarketInsightService`, `WflCalculator`, model listing version | `ExampleTest.php`, `DiscoveryHubTest.php`, E2E discovery/player flow/accessibility, dan benchmark search |
| 3 | Route recommendation, daftar/detail negotiation, start, counter, accept, reject, dan cancel di `routes/web.php`; Discovery menampilkan rekomendasi personal batch; header menampilkan badge action state | `PhaseThreePageController`, `NegotiationController`, `RecommendationService`, `NegotiationService`, `NegotiationInvalidator`, `PhaseTwoExpiryService`, shared prop `tradeActions` | `PhaseThreeNegotiationTest.php`, `DiscoveryHubTest.php`, pending-count di `CatalogBrowsingTest.php`, test React shell/trade, dan E2E negotiation di `player-flow.spec.ts` |
| 4 | Route/UI server choice, final acceptance, deal/reveal, report, suspension, audit operator, dan time controls admin non-production tersedia | `DealService`, `PhaseFourExpiryService`, controller deal/report/suspension/audit/time-control, middleware operator/suspension, schema deal/reveal | `PhaseThreeNegotiationTest.php`, `ModerationAuditTest.php`, component/E2E trade dan trust operations, serta concurrency PostgreSQL final acceptance |
| 5 | Tidak ada route atau UI wallet/payment | ADR-005 saja; schema dan domain wallet/payment belum tersedia | Belum ada |
| 6 | `/up`, `/ready`, global correlation/security middleware, scheduler heartbeat, dan command retention | `RetentionService`, konfigurasi operations, artefak Linux VM, CI PostgreSQL, ADR-009, serta runbook operasi | `ProductionReadinessTest.php`, `RetentionServiceTest.php`, suite aplikasi, E2E accessibility, dan benchmark/concurrency PostgreSQL |

## Fase 0 — Fondasi & Security Baseline

**Outcome:** akun dan baseline engineering aman untuk menjadi dasar semua capability berikutnya.

**Bukti:** authentication email/password, email verification, CSRF session web, middleware idempotensi, konfigurasi server-only, dan workflow CI tersedia. Test yang tercatat di memory mencakup authentication gate dan idempotensi mutation.

**Gap dan blocker:** rate limit endpoint mutation privat telah diterapkan dan diuji. Perubahan penutupan gap masih berupa working tree yang belum di-commit. Keputusan rate limit read-heavy endpoint tetap menjadi hardening Fase 6, bukan penghalang capability Fase 0.

**Gerbang keluar:** seluruh endpoint privat mempunyai authentication, authorization, CSRF, dan rate limit yang sesuai; pipeline terkunci lulus; tidak ada secret client; perubahan material tersimpan dalam checkpoint yang dapat dilacak.

## Fase 1 — Catalog Data & Admin

**Outcome:** operator dapat memelihara katalog/value secara manual dan pengguna selalu membaca satu snapshot published yang valid.

**Bukti:** endpoint katalog publik menyediakan search canonical/alias/kategori, empat sort order, pagination 40 item, dan detail stable `public_id`. Endpoint template, staging, publish, create/assign Rod, serta upload/delete thumbnail juga terdaftar; service CSV menyediakan partial upsert dan snapshot immutable; feature/component test katalog tersedia.

**Gap dan blocker:** update status/alias katalog serta rename/inactive Rod kini mempunyai route, UI, authorization, audit, dan test. Rollback publish dibuktikan dengan SQLite failure injection. Seluruh migration pending Fase 1–4 telah dijalankan pada database persisten pada 26 Oktober 2026 setelah otorisasi eksplisit.

**Gerbang keluar:** seluruh CAT serta ADM/AUD terkait dapat dijalankan dari admin, authorization dan audit lulus, invalid staging tidak mengubah snapshot aktif, replay publish idempotent, dan kegagalan publish terbukti rollback penuh.

## Fase 2 — Player Trade Core

**Outcome:** player dapat mengelola inventory, memublikasikan listing, menemukan listing lain, dan menjalankan simulasi W/F/L.

**Bukti:** route inventory/listing/search/simulator, versioned listing, deterministic cursor, redacted discovery DTO, W/F/L calculator, E2E player flow, accessibility test, serta benchmark PostgreSQL tersedia.

**Gap dan blocker:** route halaman edit listing, invalidasi listing setelah inventory berkurang, rate limit mutation, dan kontrol homepage telah terhubung serta diuji. Homepage tidak lagi menampilkan rekomendasi/value sintetis sebagai data nyata. INV-04 bersifat kondisional; empat kategori aktif saat ini belum mempunyai atribut tambahan wajib, sehingga tidak memerlukan field generik pada MVP. Penambahan atribut baru harus mengikuti aturan kategori konkret dan test baru.

**Gerbang keluar:** seluruh alur inventory/listing/search/simulator dapat diselesaikan dari UI, edit listing dapat dijangkau, stale inventory ditolak atau menginvalidasi state terkait, redaction tetap bersih, dan target p95 search terpenuhi.

## Fase 3 — Recommendation & Negotiation

**Outcome:** player dapat memilih rekomendasi inventory, memulai negotiation anonim, melakukan counteroffer, dan menerima versi proposal aktif.

**Bukti:** listing discovery membuka recommendation nyata dari inventory pengguna; setiap kandidat dapat diedit sampai delapan jenis item dengan quantity yang dibatasi inventory dan modal ringkasan W/F/L sebelum submit. Route HTTP mendukung start, counter, accept, reject, cancel, index, dan detail negotiation; alias anonim serta authorization peserta diterapkan. Header player menghitung setiap negotiation yang membutuhkan tindakan tepat sekali dan mengarah langsung ke daftar negotiation. Counteroffer memakai composer dua sisi: sisi actor berasal dari inventory dan editable, sisi lawan read-only, W/F/L dihitung langsung, lalu submit dilakukan dari modal ringkasan. Draft `LOSE` dapat menjadi permintaan tambahan item non-binding yang tidak dapat di-accept. Counter memakai active proposal ID dan lock agar versi basi ditolak. Feature, component, dan E2E test mencakup alur utama, intent, thumbnail, serta redaction identitas.

**Gap dan blocker:** gerbang capability tertutup pada 26 Oktober 2026. Proposal aktif kedaluwarsa setelah 24 jam dan negotiation setelah 7 hari; job `trade:expire-phase-three` berjalan setiap menit melalui scheduler yang sudah dipanggil Windows Scheduled Task. Test memastikan job hanya menutup state Fase 3 sehingga keputusan expiry Fase 4 tidak diterapkan diam-diam. Test PostgreSQL disposable memakai dua proses/koneksi yang menerima proposal sama secara bersamaan dan membuktikan tepat dua acceptance serta audit event, dengan status akhir proposal/negotiation `accepted`. Pengukuran biaya recommendation skala besar dipindahkan ke hardening performa Fase 6 karena PRD tidak menetapkan target terpisah untuk beam search.

**Gerbang keluar:** seluruh action memiliki route dan policy, UI memakai data nyata, satu proposal version aktif dijaga secara atomik, versi basi selalu ditolak, perubahan inventory/listing menginvalidasi proposal terkait, dan expiry berjalan serta diuji.

## Fase 4 — Pre-deal, Reveal & Trust Operations

**Outcome:** kedua pihak dapat menyepakati server, memberi final acceptance, dan menerima reveal terbatas secara atomik dengan `fee = 0`; operator memiliki minimum trust operations.

**Bukti:** kedua participant dapat menerima proposal yang sama, memilih dan menerima server, menyetujui disclaimer, melakukan final acceptance, lalu membuka deal dengan `fee = 0`. Identity snapshot lawan baru tersedia setelah commit final kedua. Report terstruktur dapat dikirim participant. Operator/admin dapat membuka queue audit, mengubah status report, suspend/lift akun, dan suspension memblokir trade mutation sambil tetap mengizinkan report/logout. Seluruh route memakai authorization, rate limit, audit, UI operasional, feature/component test, dan jalur E2E desktop/mobile.

**Gap dan blocker:** gerbang capability tertutup pada 26 Oktober 2026. Pra-deal kedaluwarsa setelah 30 menit dan reveal entitlement setelah 30 hari melalui job `trade:expire-phase-four` setiap menit. Environment local/development/staging/testing memiliki halaman admin khusus untuk memicu expiry secara manual dan diaudit; route tidak didaftarkan pada production. Test membuktikan authorization participant, larangan reveal prematur, disclaimer wajib, immutable identity snapshot, retry tepat sekali, rollback, expiry otomatis/manual, serta final acceptance bersamaan pada dua koneksi PostgreSQL. Kebijakan detail report/no-show/appeal dan retensi tetap pekerjaan operasional Fase 6, bukan gap capability Fase 4.

**Gerbang keluar:** route dan UI dapat digunakan kedua pihak, disclaimer non-escrow wajib diterima, finalisasi/retry bersifat idempotent, test concurrency membuktikan tidak ada reveal prematur, dan report minimum dapat ditangani operator. Wallet serta fee harus tetap nonaktif dan UI harus menyatakan biaya nol.

## Fase 5 — Wallet, Payment & Dispute Finansial

**Outcome:** monetisasi berjalan melalui ledger immutable dan payment provider tervalidasi tanpa mengubah kontrak deal historis.

**Bukti:** ADR-005 menetapkan arah arsitektur; implementasi schema, service, endpoint, UI, dan test belum tersedia.

**Gap dan blocker:** baseline development dipilih berupa gateway mock, IDR, deposit Rp10.000–Rp1.000.000, dan model fee tetap. Provider/metode/settlement production, nominal fee, expiry quote, refund, reconciliation, serta dispute finansial belum diputuskan. Fase ini tidak boleh diaktifkan sebagian atau menghambat MVP tanpa pembayaran.

**Gerbang keluar:** seluruh keputusan produk ditutup; ledger, callback, refund/reversal, compensation, reconciliation, signature validation, dan concurrency test lulus; review keamanan dan runbook insiden tersedia.

## Fase 6 — Production Readiness & Launch

**Outcome:** capability yang sudah melewati gate fase dapat dirilis dan dioperasikan dengan recovery serta observability yang disetujui.

**Bukti:** global correlation ID dan security headers, `/up`, fail-closed `/ready`, scheduler heartbeat, JSON stderr production config, retention dry-run/execute, failure-injection test, artefak Nginx/PHP-FPM/systemd/release symlink, backup/checksum/restore drill, CI Linux PostgreSQL, ADR-009, serta runbook operasi tersedia.

**Gap dan blocker:** implementasi repo siap rollout. Launch tetap diblokir karena encrypted off-host backup dan akses host belum tersedia; monitor eksternal juga baru dapat dikonfigurasi dan diuji pada environment nyata. Tidak ada deployment atau migration persisten yang dijalankan pada pekerjaan ini. Migration berikutnya tetap memerlukan rencana dan otorisasi terpisah.

**Gerbang keluar:** `composer test`, Vitest, TypeScript, build, Playwright, accessibility, dan benchmark/concurrency relevan lulus; rollback, monitoring, retention, dan restore drill tervalidasi; encrypted off-host backup aktif; migration persisten disetujui terpisah.

## Urutan backlog

1. Sediakan akses Linux VM dan encrypted off-host backup, lalu konfigurasi monitor eksternal.
2. Jalankan restore drill dari off-host copy dan uji alert dua kegagalan berurutan.
3. Minta otorisasi terpisah untuk migration persisten dan rollout MVP Fase 0–4.
4. Mulai Wallet & Payment hanya setelah sisa keputusan produk Fase 5 ditutup.

Status fase wajib diperbarui berdasarkan bukti baru. Sebuah fase tidak boleh dinaikkan menjadi selesai hanya karena schema, service, atau UI statis sudah tersedia.
