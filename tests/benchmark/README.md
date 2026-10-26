# Benchmark Search PostgreSQL Disposable

Jalankan dari root repository pada Windows:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/benchmark/run-postgres.ps1
```

Harness membuat cluster PostgreSQL sementara di `tests/benchmark/.pgdata`, memakai port `55432`, menjalankan migration pada database `fisch_trade_benchmark`, menguji final acceptance proposal secara bersamaan melalui dua proses, memuat 100.000 listing synthetic dan 200.000 baris item terindeks, lalu menghentikan dan menghapus cluster beserta log-nya.

Penjagaan menolak database selain environment `benchmark` dengan nama tepat `fisch_trade_benchmark`. Tidak ada koneksi ke database development atau produksi.

## Baseline 24 Oktober 2026

- PostgreSQL 16.4 lokal, 100.000 listing synthetic.
- Query: listing aktif, belum expired, mengecualikan listing viewer, deterministic order, dan eager-load current version.
- 10 warmup dan 100 sampel.
- p50 `16,49 ms`, p95 `21,32 ms`, maksimum `23,68 ms`.
- Target p95 `≤1.000 ms` lulus untuk query baseline ini.

## Matrix SRC-01 — 24 Oktober 2026

- Setiap skenario memakai 5 warmup dan 50 sampel, cursor 24 hasil, deterministic order, serta eager-load current version dan item katalog.
- p95 baseline `18,64 ms`; offered item `82,55 ms`; wanted item `83,16 ms`; kategori `97,86 ms`; value range `50,83 ms`; freshness `19,43 ms`; kombinasi seluruh filter `349,10 ms`.
- p95 maksimum `349,10 ms`, sehingga target `<=1.000 ms` lulus untuk seluruh matrix wajib SRC-01.

Benchmark mengukur query lokal pada hardware pengembangan, bukan latency jaringan atau kapasitas deployment produksi.

## Concurrency NEG-04 — 26 Oktober 2026

- Dua proses PHP memakai koneksi PostgreSQL terpisah dan menerima proposal yang sama secara bersamaan.
- Trigger khusus database disposable memperpanjang overlap transaksi agar row lock benar-benar diuji.
- Gerbang lulus hanya bila tepat dua acceptance dan dua audit event tercatat, lalu proposal serta negotiation sama-sama berstatus `accepted`.
- Eksekusi terverifikasi: lulus. Benchmark search yang berjalan pada cluster sama mencatat p95 maksimum `501,11 ms` pada 100.000 listing, di bawah target `1.000 ms`.
