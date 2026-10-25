# Roadmap Fase Development — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Roadmap fase development dan audit gap implementasi |
| Versi | v1.0 |
| Tanggal audit | 25 Oktober 2026 |
| Pemilik | Product dan Engineering |
| Status | Aktif |

Dokumen ini menerjemahkan requirement PRD menjadi urutan delivery. Definisi requirement tetap hanya berada di PRD. Status fase ditentukan dari jalur HTTP yang dapat digunakan, UI operasional bila diperlukan, authorization, dan automated test; schema atau service yang belum terhubung hanya dihitung sebagai fondasi parsial.

Target MVP mencakup Fase 0–4 dengan wallet dan fee nonaktif serta `fee = 0`. Fase 5 adalah monetisasi pasca-MVP. Fase 6 merupakan gerbang production dan tidak menggantikan gerbang kualitas pada fase sebelumnya.

## Status ringkas

| Fase | Requirement utama | Dependency | Status audit |
|---|---|---|---|
| 0 — Fondasi & Security Baseline | ACC-01–ACC-06 | Tidak ada | Hampir selesai |
| 1 — Catalog Data & Admin | CAT-01–CAT-06, AUD-04, ADM-01, ADM-03 | Fase 0 | Hampir selesai |
| 2 — Player Trade Core | INV-01–INV-04, LST-01–LST-06, SRC-01–SRC-05 | Fase 0–1 | Parsial kuat |
| 3 — Recommendation & Negotiation | REC-01–REC-06, NEG-01–NEG-07 | Fase 0–2 | Fondasi saja |
| 4 — Pre-deal, Reveal & Trust Operations | NEG-08, DEL-01–DEL-08, AUD-01–AUD-03, ADM-02, ADM-04–ADM-05 | Fase 0–3 | Fondasi saja |
| 5 — Wallet, Payment & Dispute Finansial | WAL-01–WAL-06, PAY-01–PAY-05 | Fase 0–4 dan keputusan produk pembayaran | Belum dimulai; bukan gate MVP |
| 6 — Production Readiness & Launch | Seluruh NFR dan gerbang operasional | Fase 0–4 untuk MVP; Fase 5 untuk monetisasi | Belum siap |

Pemetaan di atas menempatkan seluruh 69 requirement aktif tepat pada satu fase utama. Dependency lintas fase tidak mengubah kepemilikan requirement.

## Bukti inspeksi

| Fase | Route/UI | Implementation path | Test yang tersedia |
|---|---|---|---|
| 0 | Route authentication, verification, profile, dan preferences di `routes/web.php` | `AuthController`, middleware idempotensi, `bootstrap/app.php`, `.github/workflows/ci.yml` | Authentication gate dan idempotensi di `tests/Feature/ExampleTest.php`; suite terakhir tercatat di memory |
| 1 | Route admin import, Rod, dan thumbnail di `routes/web.php`; panel `resources/js/pages/admin.tsx` | `app/Domain/Catalog`, controller admin katalog, migration Rod/thumbnail | `CatalogValueCsvImportTest.php` dan `resources/js/tests/admin.test.tsx` |
| 2 | Route inventory, listing, search, dan simulator di `routes/web.php`; UI workspace/discovery | Controller inventory/listing/search, `DiscoveryPresenter`, `WflCalculator`, model listing version | `ExampleTest.php`, E2E discovery/player flow/accessibility, dan benchmark search |
| 3 | Belum ada route; form `trade.tsx` menargetkan endpoint negotiation yang belum terdaftar | Migration Fase 3, `RecommendationService`, `NegotiationService`, `NegotiationInvalidator`, `PhaseTwoExpiryService` | Belum ada test recommendation/negotiation |
| 4 | Belum ada route deal/report/suspension/audit; `trade.tsx` belum dapat dijangkau | `DealService`, controller report/suspension/audit, schema deal/reveal | `ModerationAuditTest.php` menguji service terbatas; belum ada test deal/reveal HTTP atau concurrency |
| 5 | Tidak ada route atau UI wallet/payment | ADR-005 saja; schema dan domain wallet/payment belum tersedia | Belum ada |
| 6 | Health route `/up`; belum ada surface operasi production | Workflow CI, harness E2E, benchmark PostgreSQL, audit correlation | Suite terakhir tercatat di memory; belum diulang pada audit roadmap |

## Fase 0 — Fondasi & Security Baseline

**Outcome:** akun dan baseline engineering aman untuk menjadi dasar semua capability berikutnya.

**Bukti:** authentication email/password, email verification, CSRF session web, middleware idempotensi, konfigurasi server-only, dan workflow CI tersedia. Test yang tercatat di memory mencakup authentication gate dan idempotensi mutation.

**Gap dan blocker:**

- Rate limit belum diterapkan merata pada endpoint privat seperti yang diwajibkan NFR Security.
- Redesign katalog setelah checkpoint `a77ac3d` masih berupa working tree yang belum di-commit.
- Verifikasi lokal tidak dapat diulang pada audit ini karena `vendor/autoload.php` tidak tersedia; hasil terakhir yang tervalidasi tetap dicatat di memory.

**Gerbang keluar:** seluruh endpoint privat mempunyai authentication, authorization, CSRF, dan rate limit yang sesuai; pipeline terkunci lulus; tidak ada secret client; perubahan material tersimpan dalam checkpoint yang dapat dilacak.

## Fase 1 — Catalog Data & Admin

**Outcome:** operator dapat memelihara katalog/value secara manual dan pengguna selalu membaca satu snapshot published yang valid.

**Bukti:** endpoint template, staging, publish, create/assign Rod, serta upload/delete thumbnail terdaftar; service CSV menyediakan partial upsert dan snapshot immutable; feature/component test katalog tersedia.

**Gap dan blocker:**

- `CatalogAdminController` dan `CatalogAdminService` sudah ada, tetapi update status/alias belum memiliki route dan UI operasional.
- Master Rod baru mendukung create dan assignment; edit, inactive, atau pengelolaan lifecycle belum tersedia.
- Atomic publish sudah memakai transaction, tetapi rollback akibat kegagalan di tengah publish belum dibuktikan dengan failure-injection test.
- Migration redesign belum dijalankan pada database persisten dan tetap menjadi langkah rollout yang memerlukan instruksi eksplisit.

**Gerbang keluar:** seluruh CAT serta ADM/AUD terkait dapat dijalankan dari admin, authorization dan audit lulus, invalid staging tidak mengubah snapshot aktif, replay publish idempotent, dan kegagalan publish terbukti rollback penuh.

## Fase 2 — Player Trade Core

**Outcome:** player dapat mengelola inventory, memublikasikan listing, menemukan listing lain, dan menjalankan simulasi W/F/L.

**Bukti:** route inventory/listing/search/simulator, versioned listing, deterministic cursor, redacted discovery DTO, W/F/L calculator, E2E player flow, accessibility test, serta benchmark PostgreSQL tersedia.

**Gap dan blocker:**

- `PhaseOnePageController::listingEdit` tersedia, tetapi route GET halaman edit belum terdaftar.
- Atribut tambahan inventory pada INV-04 belum diimplementasikan.
- Sebagian kontrol dan rekomendasi pada homepage masih presentasional dan belum terhubung ke aksi nyata.
- Coverage invalidasi listing setelah perubahan inventory dan rate limit mutation belum memenuhi gerbang fase.

**Gerbang keluar:** seluruh alur inventory/listing/search/simulator dapat diselesaikan dari UI, edit listing dapat dijangkau, stale inventory ditolak atau menginvalidasi state terkait, redaction tetap bersih, dan target p95 search terpenuhi.

## Fase 3 — Recommendation & Negotiation

**Outcome:** player dapat memilih rekomendasi inventory, memulai negotiation anonim, melakukan counteroffer, dan menerima versi proposal aktif.

**Bukti:** migration, model, `RecommendationService`, `NegotiationService`, invalidator, expiry service, dan komponen `trade.tsx` sudah tersedia.

**Gap dan blocker:**

- Tidak ada controller, route, dan presenter HTTP untuk recommendation maupun negotiation; `trade.tsx` belum dapat dijangkau.
- `NegotiationInvalidator` belum dipanggil oleh mutation inventory/listing.
- `PhaseTwoExpiryService` belum dijadwalkan.
- Belum ada automated test untuk authorization, ownership, counteroffer history, stale version, expiry, invalidation, retry, atau concurrency.
- Expiry proposal dan negotiation masih keputusan produk terbuka.

**Gerbang keluar:** seluruh action memiliki route dan policy, UI memakai data nyata, satu proposal version aktif dijaga secara atomik, versi basi selalu ditolak, perubahan inventory/listing menginvalidasi proposal terkait, dan expiry berjalan serta diuji.

## Fase 4 — Pre-deal, Reveal & Trust Operations

**Outcome:** kedua pihak dapat menyepakati server, memberi final acceptance, dan menerima reveal terbatas secara atomik dengan `fee = 0`; operator memiliki minimum trust operations.

**Bukti:** schema/model deal, `DealService`, deal UI, model/controller report, suspension, dan audit operator telah tersedia sebagai fondasi.

**Gap dan blocker:**

- Pre-deal, final acceptance, dan view deal belum memiliki controller atau route.
- Report, suspension, dan operator audit controller belum diroute; middleware operator/suspension belum didaftarkan; halaman operator audit belum tersedia.
- Belum ada test end-to-end untuk server choice, binding final acceptance, retry, final acceptance bersamaan, rollback, entitlement reveal, atau larangan reveal prematur.
- Expiry pra-deal/reveal serta kebijakan report, no-show, suspension, dan appeal masih terbuka.

**Gerbang keluar:** route dan UI dapat digunakan kedua pihak, disclaimer non-escrow wajib diterima, finalisasi/retry bersifat idempotent, test concurrency membuktikan tidak ada reveal prematur, dan report minimum dapat ditangani operator. Wallet serta fee harus tetap nonaktif dan UI harus menyatakan biaya nol.

## Fase 5 — Wallet, Payment & Dispute Finansial

**Outcome:** monetisasi berjalan melalui ledger immutable dan payment provider tervalidasi tanpa mengubah kontrak deal historis.

**Bukti:** ADR-005 menetapkan arah arsitektur; implementasi schema, service, endpoint, UI, dan test belum tersedia.

**Gap dan blocker:** provider, metode, currency, batas deposit, fee, refund, reconciliation, serta kebijakan dispute belum diputuskan. Fase ini tidak boleh diaktifkan sebagian atau menghambat MVP tanpa pembayaran.

**Gerbang keluar:** seluruh keputusan produk ditutup; ledger, callback, refund/reversal, compensation, reconciliation, signature validation, dan concurrency test lulus; review keamanan dan runbook insiden tersedia.

## Fase 6 — Production Readiness & Launch

**Outcome:** capability yang sudah melewati gate fase dapat dirilis dan dioperasikan dengan recovery serta observability yang disetujui.

**Bukti:** workflow CI, correlation ID, audit event, benchmark search, accessibility test, dan harness E2E disposable tersedia.

**Gap dan blocker:** RPO/RTO, retention, backup/restore drill, monitoring, runbook, persistent migration rollout, dan re-verifikasi suite belum selesai.

**Gerbang keluar:** dependency terkunci dipulihkan; `composer test`, Vitest, TypeScript, build, Playwright, dan benchmark relevan lulus; migration plan disetujui dan dijalankan terpisah; rollback, monitoring, retention, serta backup/restore tervalidasi.

## Urutan backlog

1. Stabilkan dan commit redesign katalog tanpa menjalankan migration persisten.
2. Tutup gap admin katalog dan Player Trade Core.
3. Hubungkan Recommendation & Negotiation end-to-end beserta invalidation dan expiry.
4. Hubungkan Pre-deal, Reveal, dan minimum trust operations dengan privacy/concurrency test.
5. Selesaikan production hardening dan rollout MVP Fase 0–4.
6. Mulai Wallet & Payment hanya setelah seluruh keputusan produk Fase 5 ditutup.

Status fase wajib diperbarui berdasarkan bukti baru. Sebuah fase tidak boleh dinaikkan menjadi selesai hanya karena schema, service, atau UI statis sudah tersedia.
