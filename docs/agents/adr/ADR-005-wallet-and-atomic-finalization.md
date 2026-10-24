# ADR-005 — Ledger immutable dan finalisasi deal atomik

**Status:** Berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** DEL-03 sampai DEL-07, WAL-01 sampai WAL-06, PAY-01 sampai PAY-05

## Keadaan

Model bisnis memotong saldo ketika deal terjadi. Dua pihak dapat memberi final acceptance hampir bersamaan, pengguna dapat memiliki beberapa deal aktif, serta payment provider dapat mengirim callback berulang atau tidak berurutan. Menyimpan satu angka saldo yang diedit langsung atau memisahkan debit dari reveal akan menghasilkan state yang sulit direkonsiliasi.

## Keputusan

Gunakan append-only ledger. `available_balance` diturunkan dari ledger entries yang tervalidasi; deposit, fee, refund, reversal, dan compensation masing-masing mempunyai type dan reference unik. Koreksi membuat entry kompensasi dan tidak mengubah entry lama.

Payment provider berada di balik server-side gateway contract. Finalisasi deal memakai idempotency key, transaction boundary, locking/atomic conditional writes, dan unique constraints yang sesuai. Di dalam boundary yang sama sistem memvalidasi state/saldo kedua pihak, menulis dua debit fee, membuat immutable deal snapshot, dan memberi reveal entitlement. Kegagalan apa pun me-roll back seluruh perubahan.

## Ditolak — Kolom saldo yang dapat di-update langsung

Implementasinya sederhana tetapi kehilangan asal-usul perubahan, rawan lost update, sulit direkonsiliasi dengan provider, dan mendorong admin memperbaiki angka tanpa bukti kompensasi.

## Ditolak — Debit setelah trade in-game dikonfirmasi pengguna

Platform sudah membuka identitas dan memberikan layanan sebelum dibayar. Pengguna dapat tidak mengonfirmasi, kedua pihak dapat berbeda klaim, dan sistem tidak mempunyai integrasi game untuk membuktikan trade selesai.

## Ditolak — Debit saat pra-deal dimulai

Pada titik itu pilihan server atau final commitment belum tentu sama. Pembatalan normal akan menuntut refund massal dan membuat pengguna membayar sebelum menerima identitas.

## Konsekuensi

- Schema dan service lebih kompleks tetapi seluruh perubahan uang dapat diaudit dan direkonsiliasi.
- Finalization concurrency, insufficient balance, retry, callback duplicate, reversal, dan partial failure wajib memiliki integration test dengan database disposable.
- Fee/currency/provider/refund belum dipilih; feature wallet fail-closed sampai konfigurasi lengkap.
- Dashboard admin tidak menyediakan edit saldo langsung.
- Database persisten tidak boleh dimigrasi atau ditulis agent tanpa perintah eksplisit pengguna.
