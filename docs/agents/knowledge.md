# Knowledge — Domain Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Knowledge domain dan implementasi |
| Versi | v1.1 |
| Tanggal | 25 Oktober 2026 |
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

## Batas fase aktif

Roadmap delivery memakai Fase 0–6 yang didefinisikan di [`development-phases.md`](development-phases.md). Target MVP berhenti pada Fase 4: final deal dan reveal berjalan dengan wallet serta fee nonaktif dan `fee = 0`. Wallet, payment, serta dispute finansial berada pada Fase 5 dan tidak boleh diaktifkan sebagian sebelum keputusan produk terkait ditutup. Fase 6 adalah gerbang operasional production.

Schema, service, atau UI statis tidak cukup untuk menyatakan fase selesai. Capability harus memiliki jalur HTTP yang dapat digunakan, authorization, UI operasional bila diperlukan, dan automated test sesuai gerbang fasenya.

## Katalog awal

Katalog awal hanya mengaktifkan kategori berikut, tetapi entity kategori tidak boleh berupa enum tertutup agar kategori baru dapat ditambah tanpa perubahan schema besar:

- `rod_skin` — skin untuk sebuah Rod dan merupakan item trade; relasi Rod bersifat metadata non-tradable.
- `boat` — boat yang dapat diperdagangkan.
- `relic_set` — relic atau set yang dapat diperdagangkan.
- `other` — item trade lain yang belum memiliki kategori khusus.

Setiap item katalog memiliki stable internal ID, category key, canonical name, aliases, optional thumbnail, status aktif, dan provenance. Nama eksternal tidak dipakai sebagai primary key. Rod disimpan pada master terpisah dan tidak dapat dimasukkan ke inventory/listing. Rod Skin tanpa relasi menampilkan `Undefined Rod`.

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

Katalog dan value produksi berasal dari CSV UTF-8 yang diunggah admin. Upload bersifat partial upsert dan hanya masuk staging. Baris yang tidak tercantum tidak berubah; value kosong menghapus value item dari snapshot berikutnya. Publish membawa seluruh value snapshot aktif, menerapkan patch, lalu membuat snapshot immutable baru secara atomik. Perubahan di atas 25% menjadi warning yang wajib dikonfirmasi.

Kolom CSV adalah `item_key`, `category`, `name`, `aliases`, `value`, `offer_range`, dan `status`. Server membuat `item_key` untuk item baru; template ekspor berikutnya menyertakan key tersebut. Rod dan thumbnail tidak berasal dari CSV. Thumbnail menerima JPG/PNG/WebP maksimal 2 MB dan menggunakan asset default kategori ketika belum tersedia.

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
- Recommendation hanya memakai inventory aktif milik viewer dan value snapshot listing. UI menampilkan total, delta, offer range, snapshot, item tanpa value, serta perspektif viewer.
- Route detail negotiation hanya tersedia bagi initiator atau owner. Discovery dan recommendation tidak mengirim email, USN, atau IGN pihak lawan.
- Counteroffer mengganti sisi milik actor dan membawa sisi lawan dari proposal aktif. Versi lama menjadi `superseded` dan tetap tersedia sebagai history.
- Perubahan inventory yang dipakai proposal atau perubahan/cancel listing mengubah negotiation dan proposal aktif menjadi `invalidated`.
- Proposal aktif kedaluwarsa setelah 24 jam dan negotiation setelah 7 hari. Scheduler `trade:expire-phase-three` berjalan setiap menit dan tidak menerapkan expiry pra-deal/deal sebelum kebijakan Fase 4 diputuskan.
- Participant dapat mengirim report reason-code terstruktur dari negotiation. Operator/admin mempunyai queue trust operations untuk review report dan suspend/lift akun; suspension menolak mutation trade baru tetapi tidak memblokir report atau logout.

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
- CSV invalid atau belum dipublikasikan tidak mengubah snapshot aktif. Snapshot terakhir yang valid tetap digunakan sampai admin menyetujui kandidat baru.
