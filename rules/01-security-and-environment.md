# 01 — Keamanan dan Environment

## Secret dan data sensitif

- Secret hanya boleh dibaca oleh proses server. Jangan meletakkan token, password, credential database, private key, webhook secret, atau nilai sensitif lain di source control, log, URL, error response, fixture publik, screenshot, atau dokumentasi.
- Jangan memakai environment client seperti `VITE_*` untuk secret. Semua variabel dengan prefix client dianggap publik karena masuk ke bundle browser.
- Browser hanya boleh menerima konfigurasi publik yang dipilih dan dikirim eksplisit oleh server.
- `.env` lokal tidak boleh dibaca, dicetak, disalin, di-commit, atau dikirim ke pihak lain. `.env.example` hanya berisi nama variabel dan contoh yang tidak sensitif.
- Redaksi nilai sensitif sebelum logging. Log boleh menyimpan ID internal dan status, tetapi bukan credential, token, USN/IGN yang belum boleh diungkap, atau payload payment mentah.

## Privasi identitas trade

- USN dan IGN kedua pihak bersifat rahasia sampai deal berhasil melewati final acceptance dan debit biaya atomik.
- API, event, cache, analytics, notifikasi, dan HTML awal tidak boleh membocorkan identitas tersembunyi.
- Gunakan identifier anonim khusus transaksi sebelum reveal; jangan memakai hash sederhana dari user ID yang dapat dikorelasikan lintas transaksi.

## Tindakan berisiko

- Jangan melemahkan authentication, authorization, validation, CSRF, rate limit, signature verification, atau audit untuk mempermudah development.
- Jangan memasukkan data produksi ke unit test atau fixture.
- Gunakan prinsip least privilege untuk database, queue, storage, payment gateway, dan scheduler.
