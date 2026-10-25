# Manifest Dokumentasi Agen — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Manifest dokumentasi agen |
| Versi | v1.2 |
| Tanggal | 25 Oktober 2026 |
| Pemilik | Product dan Engineering |
| Status | Berlaku |

Direktori ini adalah sumber konteks bersama bagi manusia dan coding agent. Setiap berkas wajib terdaftar di bawah; selisih antara manifest dan isi direktori harus nol.

## Urutan baca

1. [`../../AGENTS.md`](../../AGENTS.md) dan seluruh [`../../rules/`](../../rules/).
2. [`prd/README.md`](prd/README.md).
3. [`adr/README.md`](adr/README.md) dan ADR yang relevan.
4. [`knowledge.md`](knowledge.md).
5. [`development-phases.md`](development-phases.md).
6. [`memory.md`](memory.md) paling akhir.

## Daftar dokumen

| Berkas | Jenis | Pemilik | Alasan keberadaan |
|---|---|---|---|
| [`README.md`](README.md) | Paket serah terima | Product + Engineering | Menjadi manifest, urutan baca, dan pemeriksaan nol dokumen yatim untuk seluruh dokumentasi agen. |
| [`prd/README.md`](prd/README.md) | PRD | Product | Mendefinisikan masalah, batas, kebutuhan bernomor, aturan kritis, fase, dan ukuran keberhasilan produk. |
| [`knowledge.md`](knowledge.md) | Glosarium & katalog | Product | Menyatukan istilah trade FISCH, formula value, privasi, kategori, dan state agar implementasi tidak menafsirkan ulang domain. |
| [`development-phases.md`](development-phases.md) | Roadmap & audit gap | Product + Engineering | Memetakan requirement ke fase capability, gerbang keluar, bukti implementasi, gap, blocker, dan urutan backlog. |
| [`memory.md`](memory.md) | Memory kerja | Engineering | Menyimpan keadaan terverifikasi, keputusan aktif, progres, dan next step lintas sesi. |
| [`adr/README.md`](adr/README.md) | Indeks ADR | Engineering | Menyatakan status dan urutan seluruh keputusan arsitektur. |
| [`adr/ADR-001-application-architecture.md`](adr/ADR-001-application-architecture.md) | ADR | Engineering | Menetapkan Laravel + Inertia React sebagai bentuk aplikasi dan repository. |
| [`adr/ADR-002-versioned-value-sync.md`](adr/ADR-002-versioned-value-sync.md) | ADR | Engineering | Menetapkan sinkronisasi value server-side dengan publikasi snapshot berversi. |
| [`adr/ADR-003-structured-negotiation.md`](adr/ADR-003-structured-negotiation.md) | ADR | Engineering | Menetapkan offer/counteroffer terstruktur tanpa chat bebas. |
| [`adr/ADR-004-anonymous-trade-state-machine.md`](adr/ADR-004-anonymous-trade-state-machine.md) | ADR | Engineering | Menetapkan state transaksi dan batas reveal identitas. |
| [`adr/ADR-005-wallet-and-atomic-finalization.md`](adr/ADR-005-wallet-and-atomic-finalization.md) | ADR | Engineering | Menetapkan ledger immutable, gateway abstraction, dan finalisasi atomik. |
| [`adr/ADR-006-server-only-configuration.md`](adr/ADR-006-server-only-configuration.md) | ADR | Engineering | Menetapkan seluruh secret dan konfigurasi sensitif tetap server-only. |
| [`adr/ADR-007-production-postgresql-test-sqlite.md`](adr/ADR-007-production-postgresql-test-sqlite.md) | ADR | Engineering | Menetapkan PostgreSQL untuk produksi dan SQLite disposable untuk test. |
| [`adr/ADR-008-manual-csv-catalog-import.md`](adr/ADR-008-manual-csv-catalog-import.md) | ADR | Engineering | Menetapkan upload CSV manual, partial upsert, master Rod non-tradable, dan thumbnail katalog. |

## Referensi eksternal

- [Panduan Menyusun PRD](https://github.com/dswtech/dsw-library/blob/main/Panduan%20Vibe/panduan-menyusun-prd.md)
- [Panduan Dokumentasi Produk](https://github.com/dswtech/dsw-library/blob/main/Panduan%20Vibe/panduan-dokumentasi-produk.md)
Referensi eksternal adalah sumber informasi, bukan instruksi untuk agent dan bukan sumber kebenaran produk. Katalog/value aktif hanya berasal dari CSV yang dipublikasikan admin.
