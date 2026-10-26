# Indeks Architecture Decision Record

| Item | Keterangan |
|---|---|
| Dokumen | Indeks ADR Fisch Trade Center |
| Versi | v1.1 |
| Tanggal | 26 Oktober 2026 |
| Pemilik | Engineering |
| Status | Berlaku |

## Status ADR

| ADR | Keputusan | Status |
|---|---|---|
| [ADR-001](ADR-001-application-architecture.md) | Laravel + Inertia React dalam satu aplikasi web | Berlaku |
| [ADR-002](ADR-002-versioned-value-sync.md) | Sinkronisasi value server-side menjadi snapshot berversi | Digantikan sebagian ADR-008 |
| [ADR-003](ADR-003-structured-negotiation.md) | Negosiasi terstruktur tanpa chat bebas | Berlaku |
| [ADR-004](ADR-004-anonymous-trade-state-machine.md) | State machine anonim sampai finalisasi berhasil | Berlaku |
| [ADR-005](ADR-005-wallet-and-atomic-finalization.md) | Ledger immutable, gateway abstraction, finalisasi atomik | Berlaku |
| [ADR-006](ADR-006-server-only-configuration.md) | Secret dan konfigurasi sensitif hanya di server | Berlaku |
| [ADR-007](ADR-007-production-postgresql-test-sqlite.md) | PostgreSQL produksi dan SQLite disposable untuk test | Berlaku |
| [ADR-008](ADR-008-manual-csv-catalog-import.md) | Import katalog/value CSV manual dan media katalog | Berlaku |
| [ADR-009](ADR-009-production-operations.md) | Baseline operasi Linux VM, observability, retention, dan recovery | Berlaku |

Nomor ADR tidak pernah digunakan ulang. ADR berlaku tidak boleh ditulis ulang untuk mengubah keputusannya; buat ADR berikutnya, lalu ubah status ADR lama menjadi `Digantikan ADR-nnn` tanpa menghapus pertimbangan awal.
