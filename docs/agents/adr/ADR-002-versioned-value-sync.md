# ADR-002 — Sinkronisasi value server-side menjadi snapshot berversi

**Status:** Digantikan sebagian ADR-008 untuk cara akuisisi sumber; snapshot berversi tetap berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** CAT-03 sampai CAT-06, REC-02 sampai REC-05

## Keadaan

Value List adalah sumber komunitas tidak resmi yang dapat berubah format, akses, isi, dan waktu tanpa kendali aplikasi. Rekomendasi, W/F/L, dan negosiasi harus tetap dapat diaudit walau value berubah setelah pengguna mengambil keputusan. Metadata wiki dan data value juga memiliki kewenangan berbeda: wiki memperkaya katalog, bukan menentukan harga.

## Keputusan

Scheduler/server mengambil sumber eksternal ke `ValueImport` staging. Import memvalidasi schema, tipe, duplikasi, pemetaan item, nilai negatif, perubahan anomali, serta checksum. Hanya import valid yang dapat dipublikasikan menjadi `ValueSnapshot` immutable. Tepat satu snapshot menjadi aktif untuk perhitungan baru; proposal/deal lama tetap menunjuk snapshot asalnya.

Kegagalan fetch atau validasi mempertahankan last-known-good snapshot dan memunculkan status/umur sumber. Semua akses sumber, credential, parsing, dan publikasi berada di server. Wiki memakai pipeline metadata terpisah dan tidak mengubah point value.

## Ditolak — Membaca Google Sheets langsung dari browser

Pendekatan ini terlihat cepat tetapi mengekspos endpoint/configuration ke client, bergantung pada availability eksternal untuk setiap pengguna, sulit divalidasi konsisten, dan tidak menghasilkan versi historis yang dapat diaudit.

## Ditolak — Menimpa satu tabel value saat sync

Update in-place membuat proposal lama berubah arti ketika value baru masuk. Riwayat W/F/L dan alasan pengguna menerima deal tidak lagi dapat direproduksi, sehingga sengketa maupun debugging kehilangan bukti.

## Ditolak — Input admin sepenuhnya manual

Manual entry mengurangi ketergantungan format sumber tetapi meningkatkan keterlambatan, typo, dan beban operasional untuk setiap pembaruan. Admin approval tetap dapat dipakai sebagai gerbang publish tanpa mengetik ulang seluruh data.

## Konsekuensi

- Storage bertambah karena snapshot dipertahankan; retensi harus mengikuti keputusan data retention.
- Mapping alias/canonical item memerlukan antrian review bila sumber ambigu.
- Rekomendasi selalu deterministik terhadap satu version ID.
- Sinkronisasi harus idempotent; checksum sama tidak menghasilkan published snapshot duplikat.
- Mengakses atau menyalin sumber produksi harus mematuhi izin dan keputusan terbuka PRD.
