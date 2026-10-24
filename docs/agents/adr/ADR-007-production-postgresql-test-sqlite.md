# ADR-007 — PostgreSQL produksi dan SQLite disposable untuk test

**Status:** Berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** PRD Fase 0–1, SRC-01, SRC-02, LST-05

## Keadaan

Fase discovery memerlukan filter dan cursor deterministik pada target 100.000 listing aktif, sedangkan test lokal harus cepat, terisolasi, dan tidak menulis database persisten. Menjalankan test terhadap database pengembang berisiko merusak data dan membuat hasil tidak deterministik.

## Keputusan

PostgreSQL menjadi database target produksi. Migration dan indeks ditulis untuk kebutuhan domain serta pencarian, tetapi tidak dijalankan oleh agent terhadap database persisten. PHPUnit memakai SQLite `:memory:` atau file disposable dan setiap test membangun schema baru. Benchmark target search p95 ≤1 detik wajib dijalankan kemudian pada PostgreSQL disposable dengan 100.000 listing sebelum gerbang performa dinyatakan lulus.

## Ditolak — SQLite untuk produksi

SQLite baik untuk test terisolasi, tetapi tidak mewakili concurrency, planner, locking, dan karakteristik indeks PostgreSQL pada beban pencarian produksi.

## Ditolak — Database pengembang bersama untuk test

Database bersama memperkenalkan state lintas test, risiko operasi destruktif, dan ketergantungan lingkungan yang membuat suite sulit direproduksi.

## Konsekuensi

- Perbedaan dialect wajib dijaga dengan query yang didukung keduanya atau integration test PostgreSQL disposable.
- Migration boleh dibuat dan diuji pada SQLite disposable, tetapi tidak dijalankan terhadap database persisten tanpa instruksi eksplisit.
- Gerbang performa Fase 1 tetap belum terverifikasi sampai benchmark PostgreSQL disposable selesai.
