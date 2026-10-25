# ADR-008 — Import katalog/value CSV manual dan media katalog

**Status:** Berlaku · **Ditetapkan:** 25 Oktober 2026 · **Rujukan:** CAT-01 sampai CAT-06, REC-02 sampai REC-05

## Keadaan

Pipeline awal mengambil value Skin dari Google Sheets melalui scheduler, sedangkan katalog berasal dari manifest dan metadata eksternal. Operasional produk kemudian memilih sumber yang dikurasi dan diunggah sendiri secara berkala. Kategori juga berubah menjadi Rod Skin, Boats, Relic & Sets, dan Lain-lain; Rod bukan item trade dan gambar dikelola manual.

## Keputusan

Administrator mengunggah CSV UTF-8 dengan kolom `item_key`, `category`, `name`, `aliases`, `value`, `offer_range`, dan `status`. Upload hanya membentuk staging dan preview. Publish manual melakukan partial upsert katalog serta membuat snapshot value lengkap dan immutable dalam satu transaksi. Baris yang tidak ada tetap memakai state sebelumnya; value kosong menghapus value item pada snapshot baru. Perubahan value di atas 25% menjadi warning yang membutuhkan konfirmasi.

`item_key` memakai `public_id` katalog yang dibuat server dan tersedia melalui ekspor template. Rod disimpan sebagai master non-tradable dan dihubungkan ke Rod Skin di luar CSV. Thumbnail diunggah manual ke filesystem disk yang dikonfigurasi; item tanpa gambar memakai asset default kategori.

## Ditolak — Fetch Google Sheets terjadwal

Fetch otomatis mengikat operasi pada format dan availability sumber eksternal, sedangkan keputusan produk menghendaki kontrol penuh atas waktu dan isi pembaruan. Scheduler juga membuat kandidat baru tanpa tindakan operator.

## Ditolak — XLSX sebagai format awal

XLSX lebih nyaman untuk workbook kompleks, tetapi memerlukan dependency parser baru dan permukaan validasi lebih besar. CSV dapat diekspor dari Excel tanpa dependency tambahan dan cukup untuk kontrak tabular saat ini.

## Ditolak — Publish langsung setelah upload

Aktivasi otomatis menghilangkan kesempatan meninjau item baru, deactivation, value kosong, dan lonjakan value. Staging serta konfirmasi warning mempertahankan last-known-good snapshot sampai operator menyetujui perubahan.

## Konsekuensi

- Command fetch, konfigurasi Google Sheets, dan schedule import dihapus; Windows task host boleh tetap memanggil `schedule:run` tanpa job import.
- Admin bertanggung jawab mengunggah file lengkap dan meninjau preview secara berkala.
- Snapshot tetap dapat direproduksi walaupun upload bersifat partial karena publish menyalin value aktif lalu menerapkan patch.
- Key otomatis harus dipertahankan melalui template ekspor agar rename tidak membuat item baru.
- File asli tidak menjadi sumber runtime; payload tervalidasi, checksum, audit, dan snapshot menjadi bukti operasional.
