# 02 — Keselamatan Database

## Larangan default

Tanpa perintah eksplisit pengguna, agen dilarang menjalankan operasi apa pun yang mengubah database persisten, termasuk:

- `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `DROP`, atau DDL lain;
- `php artisan migrate`, rollback, refresh, reset, seed, atau perintah sejenis;
- import, backfill, sinkronisasi, queue worker, atau aplikasi yang dapat menulis data;
- query manual melalui client database, dashboard, script, REPL, atau production console.

Larangan tetap berlaku pada database development yang persisten. Persetujuan untuk menulis kode migration bukan persetujuan untuk menjalankannya.

## Yang diperbolehkan

- Membaca schema, migration, model, dump struktur tanpa data sensitif, dan query plan read-only.
- Menulis kode migration, model, repository, service, serta test bila tugas implementasi memerlukannya.
- Menjalankan test terhadap database disposable yang dibuat khusus test dan otomatis dibuang, misalnya SQLite in-memory atau container test terisolasi, setelah target dipastikan bukan database persisten.

## Penjagaan

- Sebelum test database, verifikasi environment test, nama koneksi, dan sifat disposable-nya.
- Transaction boundary, constraint, idempotency, dan concurrency wajib diuji; jangan hanya mengandalkan validasi UI.
- Wallet dan ledger tidak boleh diperbaiki dengan mengubah atau menghapus baris lama. Koreksi memakai entry kompensasi yang dapat diaudit.
