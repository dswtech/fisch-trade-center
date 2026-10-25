# Knowledge — Domain Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Knowledge domain dan implementasi |
| Versi | v1.0 |
| Tanggal | 24 Oktober 2026 |
| Pemilik | Product |
| Status | Berlaku |

## Istilah domain

| Istilah | Arti operasional |
|---|---|
| USN | Username akun Roblox yang dimasukkan pengguna secara manual. Belum terverifikasi pada fase awal. |
| IGN | Nama yang digunakan pemain di dalam game. Belum terverifikasi pada fase awal. |
| LF | `Looking For`: item yang dicari pemain. |
| Add | Item tambahan untuk mendekatkan total value kedua sisi. |
| W/F/L | Win, Fair, atau Lose dari perspektif pengguna yang melihat simulasi/proposal. |
| Inventory | Pernyataan pengguna tentang item yang dimiliki; bukan bukti kepemilikan on-chain maupun escrow. |
| Listing | Penawaran publik berisi satu atau lebih item yang diberikan serta satu atau lebih item yang dicari. |
| Proposal | Komposisi item dua sisi yang diajukan dalam satu negosiasi. |
| Counteroffer | Proposal baru yang menggantikan proposal aktif sebelumnya tanpa menghapus riwayat. |
| Value snapshot | Versi katalog harga yang immutable dan dipakai menghitung sebuah rekomendasi/proposal/deal. |
| Offer range | Rentang komunitas pada Value List; ditampilkan sebagai informasi dan tidak menggantikan point value untuk perhitungan deterministik. |
| Pra-deal | Tahap setelah komposisi item diterima, ketika kedua pihak memilih Public atau Private Server. |
| Final deal | Persetujuan terakhir kedua pihak yang memicu debit biaya dan reveal identitas secara atomik. |

## Katalog awal

Katalog awal hanya mengaktifkan kategori berikut, tetapi entity kategori tidak boleh berupa enum tertutup agar kategori baru dapat ditambah tanpa perubahan schema besar:

- `skin` — metadata nama dan gambar dapat diperkaya dari sumber katalog; value berasal dari Value List.
- `boat` — metadata berasal dari Fisch Wiki; value hanya ada bila tersedia dalam sumber value yang disetujui.
- `fishing_rod` — metadata berasal dari Fisch Wiki; value hanya ada bila tersedia dalam sumber value yang disetujui.

Setiap item katalog memiliki stable internal ID, category key, canonical name, aliases, optional image/source URL, status aktif, dan provenance. Nama eksternal tidak dipakai sebagai primary key.

## Value dan W/F/L

Untuk setiap sisi proposal:

```text
side_total = sum(item_snapshot.unit_value * quantity)
delta = receive_total - give_total
delta_percent = (delta / give_total) * 100
```

- Proposal dengan `give_total <= 0` tidak dapat dinilai.
- `Fair` berlaku jika `abs(delta_percent) <= fair_threshold_percent` yang sedang aktif.
- Di atas ambang positif adalah `Win`; di bawah ambang negatif adalah `Lose`.
- Label selalu menyebut perspektif, misalnya `Win untuk Player A`; label tidak boleh disimpan tanpa perspektif.
- Sistem fail-closed: bila ambang Fair, value item, atau published snapshot tidak tersedia, tampilkan `Tidak dapat dinilai` dan jangan mengarang nilai.
- Semua item dalam satu perhitungan harus berasal dari snapshot yang sama. Offer range ditampilkan terpisah dan tidak dicampur ke point value.

Sumber produksi awal untuk value `skin` adalah ekspor CSV publik `Value List Proto — Skins`. Server mengambilnya setiap hari pukul 02.00 `Asia/Jakarta` ke staging. Import tidak dipublikasikan otomatis; admin hanya dapat memublikasikan import valid. Perubahan jumlah baris atau value item di atas 25% terhadap snapshot `skin` aktif dari `source_key` yang sama ditolak sebagai anomali. Import pertama suatu sumber tidak dibandingkan dengan snapshot fixture atau sumber lain. Nilai tepat 25% masih diterima. Boats dan Fishing Rods tetap tanpa value sampai sumbernya disetujui.

Canonical name skin produksi dikurasi dalam manifest `database/data/skin-catalog-v1.php`. Command manual `php artisan catalog:import-skins` memasukkan manifest secara atomik dan idempotent, mencocokkan canonical name atau alias aktif, menolak mapping ambigu/inaktif, serta mencatat satu audit ketika item baru dibuat. Command ini tidak melakukan fetch eksternal dan tidak dijadwalkan; `ValueSource` tetap dilarang membuat catalog item otomatis.

Point value mempertahankan presisi sumber sampai dua angka desimal; nilai seperti `7.5` tidak boleh dibulatkan saat staging, snapshot, listing, atau perhitungan W/F/L.

## Batas privasi

Sebelum final deal selesai, pihak lawan hanya melihat alias anonim per transaksi, inventory/listing yang sengaja dimasukkan ke transaksi, dan status kesiapan. USN, IGN, email, user ID internal, histori lain, korelasi alias, serta detail pembayaran tidak boleh terlihat.

Setelah final deal berhasil, kedua pihak boleh melihat USN dan IGN satu sama lain pada deal tersebut. Email dan detail pembayaran tetap rahasia. Reveal tidak bersifat global.

## State utama

### Listing

`draft → active → matched | expired | cancelled`

- Listing `matched` atau `expired` tidak menerima negosiasi baru.
- Perubahan inventory membuat listing/proposal yang bergantung padanya wajib divalidasi ulang; sistem tidak diam-diam mengganti komposisi.

### Negotiation

`open → proposed ↔ countered → accepted | rejected | cancelled | expired | invalidated`

- Hanya satu proposal yang aktif pada satu waktu.
- Setiap counteroffer menutup proposal aktif sebelumnya sebagai `superseded` dan membuat versi baru.
- Accept terhadap versi basi harus ditolak.

### Deal

`pre_deal → awaiting_final_acceptance → finalizing → revealed | failed | cancelled | expired`

- Kedua pihak harus menyetujui proposal version dan server type yang sama.
- `finalizing` melakukan revalidasi, debit biaya, pembentukan immutable deal snapshot, dan reveal dalam satu transaction boundary.
- Kegagalan apa pun sebelum commit tidak boleh menghasilkan debit parsial atau reveal identitas.

### Deposit

`created → pending → paid | failed | expired | reversed`

Callback provider harus diverifikasi signature-nya dan diproses idempotent. Pengulangan event yang sama tidak boleh menambah saldo dua kali.

## Batas kepercayaan

- Sistem memfasilitasi pencarian, penilaian, kesepakatan, dan pertemuan; sistem tidak memegang item Roblox.
- Inventory adalah self-declared pada fase awal.
- Sistem tidak menjamin transaksi di dalam game benar-benar diselesaikan dan tidak mengklaim afiliasi resmi dengan Roblox atau Fisch.
- Google Sheets tidak resmi adalah input yang dapat berubah. Snapshot terakhir yang valid tetap aktif bila sinkronisasi baru gagal.
