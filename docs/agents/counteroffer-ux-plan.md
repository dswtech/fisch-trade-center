# Rencana UI/UX Counteroffer dan Permintaan Tambah Item

| Item | Keterangan |
|---|---|
| Dokumen | Rencana implementasi tertunda |
| Tanggal | 27 Oktober 2026 |
| Pemilik | Product dan Engineering |
| Status | Disetujui, belum diimplementasikan |

## Tujuan

Counteroffer memakai composer yang sama seperti pembuatan proposal. Pengguna hanya dapat mengubah sisi item miliknya. Jika draft bernilai `LOSE`, pengguna dapat mengirim permintaan non-binding agar lawan menambah item, sehingga komposisi tersebut tidak disalahartikan sebagai offer yang sengaja merugikan pengirim.

## Rancangan UI/UX

- Tombol **Buat counteroffer** membuka panel inline di bawah proposal aktif.
- Panel menampilkan **Kamu Berikan** yang editable, W/F/L langsung di tengah, dan **Kamu Terima** yang read-only.
- Picker memakai grid compact dengan thumbnail, nama, value snapshot, dan jumlah tersedia.
- Item dapat ditambah, dihapus, serta diubah quantity-nya dengan kontrol minus/input/plus.
- Maksimal delapan jenis item dan quantity tidak boleh melebihi inventory aktif pengguna.
- **Lihat ringkasan** membuka modal final. Tombol submit hanya tersedia di modal tersebut.
- Modal menampilkan thumbnail kedua sisi, quantity, total PTS, selisih, persentase, status W/F/L, dan versi snapshot.

### Draft `LOSE`

- CTA utama berubah menjadi **Minta lawan tambah item**.
- Teks bantuan menjelaskan bahwa tindakan ini bukan persetujuan trade dan lawan harus menambah item lalu mengirim counteroffer.
- Aksi sekunder **Tetap kirim sebagai proposal** tetap tersedia dengan konfirmasi eksplisit bagi pengguna yang memang menerima kondisi `LOSE`.
- Permintaan tambahan tidak tersedia bila value tidak lengkap karena sistem tidak dapat memastikan status `LOSE`.

### Pengalaman penerima

- Proposal aktif menampilkan badge **Minta tambahan** dan banner bahwa lawan menunjukkan item yang dimiliki serta meminta tambahan item agar trade lebih seimbang.
- Aksi yang tersedia hanya **Tambah item & balas** dan **Tolak permintaan**.
- Tombol Accept tidak ditampilkan dan endpoint juga wajib menolaknya.
- **Tambah item & balas** membuka composer dengan sisi milik penerima diprefill dan editable. Counteroffer normal yang dikirim menjadi offer binding yang dapat diterima.

## Kontrak dan Integritas State

- Tambahkan field `intent` pada proposal version dengan nilai `offer` atau `request_more_items`; data lama memakai default `offer`.
- Endpoint proposal dan counter menerima `intent`. Server hanya menerima `request_more_items` bila kalkulasi server menghasilkan `LOSE` dari perspektif pengirim.
- Proposal `request_more_items` tetap menjadi active proposal version untuk chronology, tetapi tidak dapat di-accept. Proposal dapat di-counter, ditolak, expired, atau superseded.
- Counteroffer normal mengembalikan intent ke `offer`.
- Counter request membawa `proposalId`. Service memeriksa proposal aktif di dalam database lock; versi stale menghasilkan HTTP `409` tanpa membuat versi baru.
- Server tetap memvalidasi participant authorization, ownership inventory, status entry, quantity, maksimal delapan jenis item, dan immutable value snapshot.
- Audit mencatat pembuatan, penolakan, dan balasan permintaan tambahan. Badge tindakan negotiation menghitung request aktif satu kali.
- DTO halaman negotiation menambahkan thumbnail proposal/inventory, unit value snapshot, jumlah tersedia, `fairThresholdPercent`, `intent`, dan kemampuan aksi hasil evaluasi server.

## Dokumentasi

- Tambahkan `NEG-10` untuk composer counteroffer setara composer proposal.
- Tambahkan `NEG-11` untuk permintaan terstruktur non-binding agar lawan menambah item.
- Petakan keduanya ke Fase 3 dan perbarui PRD, roadmap, `PRODUCT.md`, `DESIGN.md`, knowledge, serta memory saat implementasi dikerjakan.
- Tidak diperlukan ADR baru. Migration baru tidak boleh dijalankan pada database persisten tanpa instruksi eksplisit pengguna.

## Pengujian dan Acceptance Criteria

- Editor menginisialisasi sisi pengguna dari proposal aktif dan tidak mengizinkan perubahan sisi lawan.
- Add, remove, dan quantity memperbarui total, selisih, persentase, serta W/F/L secara langsung.
- Quantity dibatasi inventory dan jumlah jenis item dibatasi delapan.
- Ringkasan menampilkan gambar pada kedua sisi dan submit tidak tersedia di luar modal.
- Tombol permintaan hanya muncul ketika hasil server-compatible adalah `LOSE`.
- Request tidak dapat di-accept melalui UI ataupun manipulasi HTTP.
- Penerima melihat banner serta dapat menambah item dan membalas dengan counteroffer normal.
- Counter dari kedua peran mempertahankan sisi lawan, mengganti sisi actor, dan membuat tepat satu proposal version baru.
- Proposal stale ditolak tanpa supersede atau insert baru.
- History, audit, pending-action badge, expiry, rejection, authorization, privacy, loading, dan double-submit diuji.
- E2E desktop/mobile mencakup picker, perubahan quantity, W/F/L langsung, modal ringkasan, request tambahan, balasan counter, keyboard, Escape, dan horizontal overflow.
- Verifikasi akhir memakai `php artisan test`, Vitest, TypeScript, Vite build, dan Playwright terkait.

## Batas Implementasi Berikutnya

- Dokumen ini hanya menyimpan keputusan. Belum ada perubahan schema, backend, frontend, atau test.
- Implementasi dimulai pada sesi berikutnya setelah pengguna memerintahkannya.
