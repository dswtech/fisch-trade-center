# PRD — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | PRD — Fisch Trade Center |
| Versi | v1.6 (Expiry dan kontrol waktu Fase 4) |
| Tanggal | 26 Oktober 2026 |
| Disusun oleh | Product |
| Audiens | Product Owner, Engineering, Design, QA, dan Operations |
| Status | Target MVP Fase 0–4; monetisasi Fase 5 tetap tertahan keputusan produk |

| Item | Keterangan |
|---|---|
| Induk | Tidak ada — dokumen produk utama |
| Cakupan | Trade discovery sampai final deal, serta fase wallet dan pembayaran |
| Requirement | 69 aktif |
| Prasyarat | Sumber katalog/value yang dapat digunakan; keputusan pembayaran sebelum Fase 5 |
| Aturan ID | ID tidak boleh digunakan ulang dan hanya didefinisikan dalam dokumen ini |

## 1. Ringkasan Eksekutif

Fisch Trade Center adalah aplikasi web yang membantu pemain FISCH menemukan partner trade, memahami value item, menyusun kombinasi penawaran dari inventory mereka, bernegosiasi secara terstruktur, dan mencapai kesepakatan tanpa membuka identitas Roblox terlalu dini.

Nilai utamanya bukan sekadar membuat listing. Sistem mengurangi ketidakpastian saat pemain tidak mengetahui value atau tidak tahu kombinasi item apa yang layak ditawarkan. Semua rekomendasi dan proposal memakai value snapshot yang sama sehingga perubahan sumber value di kemudian hari tidak menulis ulang sejarah keputusan.

Produk dirilis melalui fase capability. Target MVP mencakup fondasi, katalog, trade core, negotiation, dan final deal/reveal tanpa pembayaran. Wallet, deposit, serta biaya layanan berada pada fase pasca-MVP setelah keputusan finansial ditutup.

## 2. Latar Belakang & Permasalahan

Pemain saat ini menyatakan keinginan trade dengan kalimat komunitas seperti `LF X for Y + add` dan meminta penilaian `W/F/L?`. Proses tersebut bergantung pada pengetahuan manual tentang value yang berubah, pencarian partner di kanal yang ramai, dan perundingan kombinasi item tanpa alat hitung yang konsisten.

Dua kegagalan lapangan yang hendak diselesaikan adalah pemain tidak mengetahui nilai inventory-nya dan pemain tidak tahu apa yang dapat ditawarkan untuk target tertentu tanpa menerima hasil Lose. Bahkan setelah partner ditemukan, perubahan kombinasi dapat membuat kedua pihak membandingkan angka atau versi value yang berbeda.

Identitas Roblox juga perlu dilindungi selama penjajakan. Membukanya sejak listing memungkinkan pihak lain menghubungi pemain di luar proses, sedangkan membukanya setelah biaya dipotong tetapi sebelum finalisasi atomik dapat menyebabkan salah satu pihak membayar tanpa menerima akses yang dijanjikan.

## 3. Tujuan & Batasan

### 3.1 Tujuan

| Sasaran | Ukuran keberhasilan |
|---|---|
| Membantu pemain memahami kelayakan trade | Sedikitnya 95% simulasi yang seluruh item-nya memiliki value menghasilkan total, selisih, dan label W/F/L deterministik. |
| Mempercepat penemuan partner | p95 pencarian listing aktif selesai ≤ 1 detik pada target kapasitas awal 100.000 listing aktif. |
| Menjaga konsistensi negosiasi | 100% proposal dan deal dapat ditelusuri ke satu published value snapshot. |
| Melindungi identitas sebelum deal | 0 response, event, log aplikasi, atau halaman lawan yang mengandung USN/IGN sebelum deal berstatus `revealed`. |
| Menjaga saldo dan biaya | 0 debit parsial atau debit ganda pada pengujian retry, callback duplikat, dan final acceptance bersamaan. |

### 3.2 Batasan

| Batasan | Keterangan |
|---|---|
| Tidak menjadi escrow item | Sistem tidak memegang atau memindahkan item Roblox dan tidak dapat menjamin trade in-game diselesaikan. |
| Tidak memverifikasi kepemilikan item pada fase awal | Inventory merupakan pernyataan pemain; klaim tidak boleh ditampilkan sebagai kepemilikan terverifikasi. |
| Tidak memverifikasi USN/IGN pada fase awal | Identitas dimasukkan manual. Integrasi OAuth atau verifikasi Roblox memerlukan keputusan dan fase terpisah. |
| Tidak menyediakan chat bebas | Negosiasi dan pemilihan server memakai field terstruktur untuk mengurangi moderasi dan kebocoran identitas. |
| Tidak menentukan harga pasar sendiri | Sistem memproses sumber value yang dipilih; ia tidak mengklaim value sebagai harga resmi Fisch/Roblox. |
| Tidak menjual item | Saldo hanya membayar layanan platform, bukan membeli item atau menjamin nilai tukar item. |
| Tidak mengaktifkan kategori di luar cakupan awal | Hanya Rod Skin, Boats, Relic & Sets, dan Lain-lain yang aktif pada rilis awal walaupun model kategori generik. Rod hanya metadata non-tradable. |

## 4. Pengguna & Peran

| Peran | Cakupan | Larangan kritis |
|---|---|---|
| Guest | Melihat landing page, katalog publik, penjelasan value, dan listing yang sudah diredaksi. | Tidak melihat identitas, inventory privat, detail negosiasi, saldo, atau audit. |
| Player | Mengelola profil, inventory, listing, pencarian, rekomendasi, negosiasi, deal, dan wallet miliknya. | Tidak melihat identitas lawan sebelum reveal; tidak mengubah item/value snapshot atau ledger; tidak bertindak atas transaksi pengguna lain. |
| Operator/Support | Menangani report dan dispute sesuai kewenangan terbatas serta melihat audit yang diperlukan. | Tidak mengubah value, saldo, atau identitas; tidak melihat detail pembayaran di luar kebutuhan kasus. |
| Administrator | Mengelola katalog/import, threshold, fee, status pengguna, dan kebijakan operasional. | Tidak menghapus audit/ledger atau mengganti snapshot historis; tindakan sensitif wajib tercatat. |

## 5. Konsep Utama

| Konsep | Penjelasan |
|---|---|
| Catalog Item | Definisi canonical untuk sebuah Rod Skin, Boat, Relic & Set, atau item Lain-lain; berbeda dari klaim inventory milik pengguna. |
| Rod | Master data non-tradable yang dapat dihubungkan ke Rod Skin. Skin tanpa relasi menampilkan `Undefined Rod`. |
| Inventory Entry | Klaim pengguna atas catalog item dan quantity; bukan bukti kepemilikan terverifikasi. |
| Value Import | Hasil pengambilan dan validasi satu sumber eksternal yang belum tentu dipublikasikan. |
| Value Snapshot | Sekumpulan value immutable yang telah dipublikasikan dan memiliki version ID. |
| Listing | Penawaran publik satu pengguna: item yang diberikan dan item yang dicari. |
| Recommendation | Kombinasi inventory pengguna untuk sebuah target berdasarkan satu snapshot; bukan proposal sampai disubmit. |
| Negotiation | Ruang privat anonim antara dua pihak dengan riwayat proposal terstruktur. |
| Proposal Version | Komposisi immutable kedua sisi pada satu titik negosiasi. |
| Deal | Rekaman immutable dari proposal yang disepakati, server type, fee, snapshot, dan hasil reveal. |
| Available Balance | Saldo ledger yang dapat didebit; nilai turunan dari entry ledger, bukan angka bebas yang dapat diedit. |

`Value`, `offer range`, dan `cost in Robux` tidak boleh disamakan. Point value dipakai untuk perhitungan deterministik; offer range hanya informasi komunitas; cost merupakan metadata perolehan dan bukan otomatis market value.
Point value dapat berupa pecahan sampai dua angka desimal dan harus dipertahankan tanpa pembulatan sepanjang import, snapshot, listing, dan perhitungan W/F/L.

## 6. Alur Proses

### 6.1 Pendaftaran dan persiapan

| # | Pelaku | Tindakan | Keadaan sesudahnya |
|---|---|---|---|
| 1 | Guest | Mendaftar dengan email dan password. | Akun dibuat dan menunggu/selesai verifikasi email sesuai kebijakan authentication. |
| 2 | Player | Mengisi USN dan IGN. | Identitas tersimpan sebagai data privat, belum terverifikasi. |
| 3 | Player | Menambahkan Rod Skin, Boats, Relic & Sets, atau item Lain-lain ke inventory. | Inventory siap dipakai listing dan rekomendasi. |

### 6.2 Mencari barang

| # | Pelaku | Tindakan | Keadaan sesudahnya |
|---|---|---|---|
| 1 | Player | Memilih item target dan filter. | Sistem mencari listing aktif dari pemain lain. |
| 2 | Sistem | Menghitung kombinasi inventory terhadap target dengan satu value snapshot. | Kandidat menampilkan total, delta, offer range, dan W/F/L atau alasan tidak dapat dinilai. |
| 3 | Player | Memilih kandidat dan mengirim proposal. | Negotiation anonim dibuat atau proposal baru masuk ke negotiation aktif. |

### 6.3 Menawarkan barang

| # | Pelaku | Tindakan | Keadaan sesudahnya |
|---|---|---|---|
| 1 | Player | Memilih satu atau lebih inventory entry yang ditawarkan. | Draft listing menyimpan komposisi offered side. |
| 2 | Player | Memilih satu atau lebih catalog item yang dicari. | Draft memiliki wanted side. |
| 3 | Player | Memublikasikan listing. | Listing aktif muncul pada pencarian tanpa USN/IGN. |

### 6.4 Negosiasi sampai deal

| # | Pelaku | Tindakan | Keadaan sesudahnya |
|---|---|---|---|
| 1 | Salah satu player | Mengirim offer atau counteroffer terstruktur. | Satu proposal version aktif; versi sebelumnya tetap tercatat sebagai superseded. |
| 2 | Kedua player | Menerima komposisi yang sama. | Negotiation masuk pra-deal. |
| 3 | Kedua player | Menyetujui Public atau Private Server. | Deal menunggu final acceptance. |
| 4 | Kedua player | Memberi final acceptance. | Sistem mengunci state, revalidasi, mendebit fee atomik, membuat deal snapshot, lalu reveal USN/IGN. |
| 5 | Player | Bertemu dan trade di Fisch. | Hasil in-game berada di luar jaminan sistem; report/dispute mengikuti kebijakan fase terkait. |

### 6.5 Jalur yang tidak berakhir normal

- Import value invalid disimpan sebagai gagal dan snapshot aktif sebelumnya tetap digunakan.
- Listing/proposal yang expired, dibatalkan, atau inventory-nya berubah tidak dapat difinalisasi.
- Accept terhadap proposal version lama ditolak dan state terbaru dikembalikan.
- Saldo tidak cukup membuat finalisasi gagal tanpa debit dan tanpa reveal.
- Dua final acceptance bersamaan menghasilkan tepat satu finalisasi idempotent.
- Callback payment duplikat menghasilkan tepat satu credit ledger.
- Cancel setelah finalisasi tidak membalik ledger secara diam-diam; koreksi memerlukan proses refund/compensation yang dapat diaudit.

## 7. Kebutuhan Fungsional

Prioritas: `M` Must, `S` Should, `C` Could, dan `W` Won't untuk cakupan rilis saat ini.

### 7.1 Akun dan profil

| ID | Kebutuhan | Prioritas |
|---|---|---|
| ACC-01 | **Guest dapat mendaftar dan masuk memakai email serta password.** Credential diproses server-side dan password tidak pernah disimpan dalam bentuk terbaca. | M |
| ACC-02 | **Player mengisi USN dan IGN sebelum memublikasikan listing atau memulai negosiasi.** Keduanya ditandai `unverified` agar pengguna lain tidak menganggapnya telah dibuktikan. | M |
| ACC-03 | **Player dapat mengubah USN/IGN sebelum final deal.** Perubahan wajib tercatat dan deal yang sudah revealed mempertahankan snapshot identitas saat finalisasi. | S |
| ACC-04 | **Sistem tidak mengungkap email kepada pengguna lain dalam keadaan apa pun.** Email hanya untuk authentication dan komunikasi sistem yang disetujui. | M |
| ACC-05 | **OAuth atau verifikasi identitas Roblox tidak dikerjakan pada fase awal.** Batas ini mencegah klaim verifikasi semu sebelum integrasi resmi dirancang. | W |
| ACC-06 | **Email wajib terverifikasi sebelum player mengelola inventory, memublikasikan listing, atau memakai simulator privat.** Katalog dan discovery publik tetap dapat dibaca tanpa verifikasi. | M |

### 7.2 Katalog dan import value

| ID | Kebutuhan | Prioritas |
|---|---|---|
| CAT-01 | **Sistem menyimpan catalog item dengan stable internal ID, category, canonical name, alias, provenance, dan status.** Nama eksternal tidak menjadi primary key karena dapat berubah. | M |
| CAT-02 | **Kategori aktif awal adalah Rod Skin, Boats, Relic & Sets, dan Lain-lain.** Rod merupakan metadata non-tradable; model kategori tetap extensible. | M |
| CAT-03 | **Administrator mengunggah CSV UTF-8 secara berkala ke staging.** Upload bersifat partial upsert, tidak mengaktifkan data sebelum review dan publish manual. | M |
| CAT-04 | **Import memvalidasi header, tipe, key, duplikasi, kategori, status, formula injection, dan presisi value sebelum publikasi.** Perubahan value di atas 25% menjadi warning yang memerlukan konfirmasi; import invalid tidak mengganti snapshot aktif. | M |
| CAT-05 | **Admin memublikasikan import valid secara atomik menjadi perubahan katalog dan snapshot value lengkap yang immutable.** Baris yang tidak diunggah dipertahankan dan value kosong menghapus value item pada snapshot baru. | M |
| CAT-06 | **Admin dapat mengelola relasi Rod dan thumbnail item secara manual.** Thumbnail hanya JPG/PNG/WebP maksimal 2 MB; item tanpa thumbnail memakai gambar default kategori. | S |

### 7.3 Inventory

| ID | Kebutuhan | Prioritas |
|---|---|---|
| INV-01 | **Player dapat menambah catalog item aktif beserta quantity ke inventory.** Entri ditandai self-declared karena belum diverifikasi oleh game. | M |
| INV-02 | **Player dapat mengubah quantity atau mengarsipkan inventory entry miliknya.** Sistem memeriksa dampaknya terhadap listing dan proposal aktif. | M |
| INV-03 | **Inventory privat tidak dapat dilihat utuh oleh player lain.** Hanya item yang sengaja dimasukkan ke listing/proposal terkait yang terlihat. | M |
| INV-04 | **Inventory menyimpan atribut item tambahan secara tervalidasi bila kategori membutuhkannya.** Atribut yang memengaruhi value tidak boleh diterima sebelum aturan valuasinya tersedia. | S |

### 7.4 Listing

| ID | Kebutuhan | Prioritas |
|---|---|---|
| LST-01 | **Player dapat membuat listing dengan sedikitnya satu item inventory yang ditawarkan dan satu catalog item yang dicari.** Listing kosong tidak memiliki makna trade dan ditolak. | M |
| LST-02 | **Offered quantity tidak boleh melebihi quantity inventory saat listing dipublikasikan.** Penjagaan diulang saat proposal dan finalisasi karena inventory dapat berubah. | M |
| LST-03 | **Listing aktif menampilkan alias anonim, komposisi item, value version, umur listing, dan status tanpa USN/IGN.** Ini memungkinkan discovery tanpa membuka identitas. | M |
| LST-04 | **Player dapat membatalkan listing miliknya.** Pembatalan mencegah negosiasi baru tetapi tidak menghapus audit atau negotiation yang sudah ada. | M |
| LST-05 | **Listing MVP kedaluwarsa 14 hari setelah dipublikasikan.** Listing expired tidak muncul sebagai aktif dan tidak dapat menerima proposal baru; kebijakan expiry negotiation dan deal tetap keputusan terpisah. | S |
| LST-06 | **Edit material pada listing aktif membuat versi listing baru atau menginvalidasi proposal terkait.** Komposisi yang telah dilihat lawan tidak boleh berubah diam-diam. | M |

### 7.5 Search dan discovery

| ID | Kebutuhan | Prioritas |
|---|---|---|
| SRC-01 | **Player dapat mencari listing berdasarkan wanted item, offered item, kategori, value range, dan freshness.** Default hanya menampilkan listing aktif milik pengguna lain. | M |
| SRC-02 | **Hasil search dipaginasi dan memiliki urutan deterministik.** Pagination tidak boleh menduplikasi atau melompati listing pada cursor yang sama. | M |
| SRC-03 | **Guest hanya melihat hasil publik yang telah diredaksi.** Tindakan trade mengarahkan guest untuk authentication. | S |
| SRC-04 | **Search tetap dapat digunakan saat upload/import value terbaru gagal.** Sistem memakai published snapshot terakhir dan menampilkan umur datanya. | M |
| SRC-05 | **Pencarian fuzzy menggunakan alias katalog yang dikurasi.** Sistem tidak membuat catalog item baru hanya karena typo pengguna. | C |

### 7.6 Rekomendasi dan W/F/L

| ID | Kebutuhan | Prioritas |
|---|---|---|
| REC-01 | **Sistem merekomendasikan kombinasi hanya dari inventory player dengan quantity yang tersedia.** Rekomendasi tidak boleh menawarkan item yang tidak diklaim pengguna. | M |
| REC-02 | **Seluruh item dalam satu perhitungan menggunakan published value snapshot yang sama.** Mencampur versi menghasilkan perbandingan historis yang tidak dapat diaudit. | M |
| REC-03 | **Hasil menampilkan give total, receive total, delta nominal, delta persen, offer range, snapshot version, dan perspektif W/F/L.** Perspektif eksplisit mencegah label terbalik. | M |
| REC-04 | **Ambang Fair awal adalah ±10% dan dapat diubah admin secara prospektif sebagai persentase non-negatif.** Perubahan dicatat di audit dan tidak mengubah hasil historis. | M |
| REC-05 | **Item tanpa value membuat kombinasi tidak mendapat label W/F/L.** Sistem menunjukkan item penyebab agar pengguna memahami kekurangannya. | M |
| REC-06 | **Sistem dapat mengurutkan kandidat berdasarkan kedekatan value dan jumlah item lebih sedikit.** Ranking bersifat bantuan dan tidak menyatakan jaminan trade terbaik. | S |

### 7.7 Negosiasi terstruktur

| ID | Kebutuhan | Prioritas |
|---|---|---|
| NEG-01 | **Player dapat memulai negotiation dari listing dengan proposal dua sisi.** Pihak, listing, snapshot, dan alias anonim dikunci untuk ruang tersebut. | M |
| NEG-02 | **Setiap pihak dapat mengirim counteroffer dengan memilih item dan quantity yang diizinkan.** Counteroffer membuat proposal version baru dan mempertahankan versi lama sebagai riwayat. | M |
| NEG-03 | **Hanya proposal version terbaru yang dapat diterima.** Accept terhadap versi lama atau superseded ditolak dengan state terbaru. | M |
| NEG-04 | **Masing-masing pihak dapat accept, reject, atau cancel sesuai state.** Semua transisi mencatat actor, waktu, dan version. | M |
| NEG-05 | **Perubahan inventory/listing memicu revalidasi proposal aktif.** Proposal invalidated tidak dapat diteruskan tanpa versi baru yang valid. | M |
| NEG-06 | **Negotiation kedaluwarsa berdasarkan konfigurasi dan aktivitas terakhir.** Expiry menutup tindakan baru tanpa menghapus riwayat. | S |
| NEG-07 | **Tidak ada field pesan bebas pada fase awal.** Seluruh komunikasi produk dibatasi pada pilihan item, action, reason code, dan pilihan server terstruktur. | M |
| NEG-08 | **Pengguna dapat melaporkan negotiation atau lawan dengan reason code.** Catatan tambahan bebas ditunda sampai kebijakan moderasi dan privasi disetujui. | C |

### 7.8 Pra-deal, final deal, dan reveal

| ID | Kebutuhan | Prioritas |
|---|---|---|
| DEL-01 | **Proposal masuk pra-deal hanya setelah kedua pihak menerima version yang sama.** Persetujuan pada version berbeda tidak boleh digabung. | M |
| DEL-02 | **Kedua pihak memilih dan menyetujui `public_server` atau `private_server`.** Pilihan server merupakan data terstruktur dan dapat di-counter sebelum final acceptance. | M |
| DEL-03 | **Final acceptance terikat pada proposal version, server choice, fee quote, dan expiry yang sama.** Perubahan salah satunya membatalkan acceptance sebelumnya. | M |
| DEL-04 | **Finalisasi mengunci deal, memeriksa state, inventory, listing, snapshot, fee, dan saldo kedua pihak dalam satu transaction boundary.** Kegagalan satu syarat membatalkan seluruh finalisasi. | M |
| DEL-05 | **Debit kedua pihak, deal snapshot, dan reveal entitlement terjadi tepat sekali secara atomik.** Tidak boleh ada satu pihak terdebit sendiri atau identitas terbuka sebelum commit. | M |
| DEL-06 | **Setelah deal `revealed`, kedua pihak melihat USN dan IGN snapshot satu sama lain hanya pada deal tersebut.** Email dan detail pembayaran tidak pernah dibuka. | M |
| DEL-07 | **Retry dan final acceptance bersamaan bersifat idempotent.** Semua caller menerima hasil deal yang sama tanpa debit atau reveal tambahan. | M |
| DEL-08 | **Sistem menyatakan jelas bahwa trade in-game tidak dijamin dan item tidak di-escrow.** Pernyataan tampil sebelum final acceptance. | M |

Pra-deal kedaluwarsa 30 menit setelah pilihan server dibuat. Entitlement untuk melihat identity snapshot lawan berlaku 30 hari setelah deal `revealed`; berakhirnya entitlement tidak menghapus snapshot immutable. Scheduler memproses kedua batas ini. Pada environment `local`, `development`, `staging`, dan `testing`, administrator dapat memicu expiry pra-deal atau entitlement reveal secara manual dari halaman khusus yang diaudit. Route tersebut tidak didaftarkan pada production.

### 7.9 Wallet dan ledger

| ID | Kebutuhan | Prioritas |
|---|---|---|
| WAL-01 | **Setiap player memiliki wallet dengan saldo yang diturunkan dari immutable ledger entries.** Saldo tidak diedit langsung agar setiap perubahan dapat diaudit. | M |
| WAL-02 | **Ledger mendukung credit deposit, debit fee, refund, dan compensation dengan reference unik.** Koreksi membuat entry baru, bukan mengubah atau menghapus entry lama. | M |
| WAL-03 | **Available balance tidak boleh negatif.** Penjagaan dilakukan dalam transaction boundary yang aman terhadap concurrency. | M |
| WAL-04 | **Player dapat melihat saldo dan riwayat ledger miliknya dengan label yang aman.** Reference internal sensitif dan data pihak lain tidak ditampilkan. | S |
| WAL-05 | **Admin tidak dapat mengedit saldo secara langsung.** Penyesuaian wajib melalui compensation dengan reason, actor, dan audit. | M |
| WAL-06 | **Fitur wallet tetap nonaktif sampai currency, minimum deposit, fee, refund, dan provider disetujui.** UI tidak boleh memberi kesan deposit tersedia sebelum konfigurasi lengkap. | M |

### 7.10 Pembayaran

| ID | Kebutuhan | Prioritas |
|---|---|---|
| PAY-01 | **Integrasi pembayaran memakai kontrak provider-agnostic di server.** Domain wallet tidak bergantung pada payload khusus provider. | M |
| PAY-02 | **Player dapat membuat deposit intent dengan amount dan expiry sesuai konfigurasi.** Secret provider tidak pernah dikirim ke browser. | M |
| PAY-03 | **Callback provider diverifikasi signature, amount, currency, merchant reference, dan statusnya sebelum credit.** Payload invalid ditolak dan diaudit. | M |
| PAY-04 | **Pemrosesan callback dan polling bersifat idempotent berdasarkan provider event/reference.** Event duplikat atau out-of-order tidak menggandakan credit. | M |
| PAY-05 | **Refund dan reversal memakai entry ledger kompensasi serta state machine tersendiri.** Penghapusan entry pembayaran dilarang. | S |

### 7.11 Audit dan administrasi

| ID | Kebutuhan | Prioritas |
|---|---|---|
| AUD-01 | **Sistem mencatat actor, action, target, before/after aman, timestamp, request correlation, dan reason untuk tindakan sensitif.** Secret dan data identitas yang belum boleh terungkap harus diredaksi. | M |
| AUD-02 | **Riwayat proposal, listing version, value snapshot, ledger, payment event, dan reveal tidak dapat dihapus melalui fitur aplikasi biasa.** Retensi mengikuti kebijakan yang akan disetujui. | M |
| AUD-03 | **Operator hanya melihat audit yang diperlukan untuk kasus yang ditangani.** Access audit itu sendiri turut dicatat. | S |
| AUD-04 | **Administrator dapat melihat status import, anomali, snapshot aktif, dan umur sumber.** Admin tidak dapat mengedit snapshot published. | M |
| ADM-01 | **Administrator dapat mengaktifkan/nonaktifkan catalog item dan mengelola alias tanpa mengubah identitas historis item.** Deal lama tetap menunjuk snapshot nama/value-nya. | M |
| ADM-02 | **Administrator dapat mengatur Fair threshold, expiry, fee, dan feature flag dengan validasi serta audit.** Perubahan berlaku prospektif dan tidak menghitung ulang deal lama. | M |
| ADM-03 | **Administrator dapat meninjau ulang dan memublikasikan staging upload secara idempotent tanpa memublikasikan data invalid.** Replay tidak membuat snapshot published duplikat untuk checksum yang sama. | S |
| ADM-04 | **Administrator dapat suspend akun sesuai reason dan durasi.** Suspension mencegah tindakan baru tetapi tidak menghapus audit, ledger, atau deal. | S |
| ADM-05 | **Operator dapat menangani report/dispute melalui status dan reason code terstruktur.** Kebijakan resolusi finansial tetap tertahan sampai Bagian 14 diputuskan. | C |

## 8. Gambaran Model Data

| Entitas | Peran | Catatan |
|---|---|---|
| User | Authentication dan status akun. | Email privat; tidak dipakai sebagai identity publik. |
| PlayerProfile | USN, IGN, verification status. | Snapshot disalin ke deal saat reveal, bukan dibaca ulang untuk sejarah. |
| CatalogCategory / CatalogItem | Taksonomi dan canonical item. | Kategori extensible; provenance tersimpan. |
| ValueImport / ValueSnapshot / ItemValue | Staging, versi published, dan nilai per item. | Snapshot published immutable. |
| InventoryEntry | Klaim item dan quantity milik user. | Self-declared; perubahan memicu revalidasi. |
| Listing / ListingVersion / ListingItem | Lifecycle listing dan komposisi offered/wanted. | Version lama tidak ditimpa. |
| Negotiation / Proposal / ProposalItem | Ruang anonim dan riwayat counteroffer. | Satu active proposal version. |
| Deal / DealAcceptance / IdentityReveal | Pra-deal, final acceptance, snapshot hasil, dan entitlement reveal. | Deal menyimpan snapshot, bukan referensi mutable saja. |
| Wallet / LedgerEntry | Kepemilikan wallet dan perubahan saldo. | Saldo diturunkan; ledger immutable. |
| Deposit / PaymentEvent | Intent provider dan callback terverifikasi. | Reference/event unik untuk idempotency. |
| AuditEvent / Report / Dispute | Jejak tindakan dan operasional kasus. | Payload sensitif diredaksi. |

Nilai `available_balance`, total proposal, delta, dan label W/F/L merupakan nilai turunan. Hasil total yang ditampilkan pada deal disalin sebagai snapshot untuk audit, tetapi sumber perhitungannya tetap item/value snapshot terkait.

## 9. Aturan Bisnis Kritis

| Aturan | Yang terjadi bila tidak ditegakkan | Rujukan | Penegakan |
|---|---|---|---|
| Identitas tidak terbuka sebelum finalisasi berhasil. | Pengguna kehilangan privasi walau deal gagal atau saldo tidak cukup; tidak dapat ditarik kembali. | DEL-05, DEL-06 | Policy/serializer server, transaction boundary, automated security test. |
| Satu proposal hanya memakai satu value snapshot. | Hasil W/F/L tampak sah tetapi membandingkan nilai dari waktu berbeda. | REC-02 | Domain service dan constraint/reference model; integration test. |
| Proposal version basi tidak dapat diterima. | Pihak dapat terikat pada komposisi yang tidak lagi ia lihat/setujui. | NEG-03, DEL-01 | Optimistic version check dan locked finalization; concurrency test. |
| Debit fee dan reveal terjadi tepat sekali secara atomik. | Pengguna dapat terdebit sendiri, ganda, atau membayar tanpa identitas lawan. | DEL-04, DEL-05, DEL-07 | Database transaction, unique idempotency key, row lock/atomic conditional write. |
| Saldo tidak pernah negatif. | Dua deal bersamaan dapat membelanjakan saldo yang sama dan menimbulkan kewajiban finansial. | WAL-03 | Database constraint/atomic ledger posting serta concurrency test. |
| Callback pembayaran hanya mengkredit sekali setelah tervalidasi. | Retry atau callback palsu menciptakan saldo tanpa dana yang sah. | PAY-03, PAY-04 | Signature verification, unique provider event/reference, idempotent handler. |
| Ledger dan snapshot published tidak diubah/dihapus. | Audit finansial dan alasan keputusan historis hilang; saldo/deal lama berubah retroaktif. | CAT-05, WAL-02, AUD-02 | Permission model, append-only service, database constraints bila tersedia, audit test. |

Aturan yang tidak dapat ditegakkan penuh oleh database—terutama redaksi identitas dan validasi signature—wajib dijaga lapisan aplikasi serta automated test. Implementasi tidak boleh menyatakan database telah melindunginya.

## 10. Integrasi

| Modul/Sistem | Arah ketergantungan | Yang dipertukarkan |
|---|---|---|
| CSV administrasi | Administrator mengunggah sumber katalog dan value yang telah dikurasi. | Stable item key, category, canonical name, aliases, value, offer range, dan status. |
| Payment Gateway | Fase wallet memanggil provider dan menerima callback provider. | Deposit intent, merchant reference, amount/currency, expiry, status, signature metadata. |
| Email Provider | Authentication/notifikasi server mengirim email. | Alamat tujuan, template, verification/reset token berumur pendek. |
| Roblox/Fisch | Tidak ada integrasi runtime pada fase awal. | Tidak berlaku; USN/IGN dan inventory dimasukkan manual. |

Seluruh integrasi eksternal berada di server. Kegagalan integrasi tidak boleh membocorkan secret atau mengubah state domain tanpa validasi dan idempotency.

## 11. Kebutuhan Non-Fungsional

| Area | Target |
|---|---|
| Search performance | p95 ≤ 1 detik pada 100.000 listing aktif untuk filter umum. |
| Mutation performance | p95 ≤ 750 ms untuk aksi non-payment di luar waktu integrasi eksternal. |
| Availability | 99,5% per bulan setelah production launch, di luar maintenance terjadwal. |
| Data integrity | 0 debit/credit ganda dan 0 reveal prematur dalam test suite concurrency/idempotency wajib. |
| Security | Seluruh endpoint privat memerlukan authentication, object-level authorization, CSRF protection untuk session web, dan rate limit. |
| Privacy | USN/IGN tidak muncul pada response lawan, event analytics, atau log sebelum reveal; diuji otomatis. |
| Accessibility | Alur utama memenuhi WCAG 2.2 AA untuk keyboard, focus, label, contrast, dan error announcement. |
| Responsive UI | Alur utama berfungsi pada viewport 360 px sampai desktop 1440 px tanpa horizontal overflow yang menghalangi aksi. |
| Observability | 100% finalization, payment callback, import publish/fail, dan admin configuration change mempunyai correlation ID dan audit event. |
| Recovery | Backup/restore target ditetapkan sebelum production; sementara itu production launch tertahan. |

## 12. Tahapan Rilis

| Fase | Cakupan minimum | Requirement utama | Gerbang keluar |
|---|---|---|---|
| Fase 0 — Fondasi & Security Baseline | Dokumen, authentication, CI/test baseline, idempotency, dan server-only config. | ACC-01–ACC-06 | Endpoint privat terlindungi; pipeline bersih; tidak ada secret client. |
| Fase 1 — Catalog Data & Admin | CSV staging/publish, kategori, snapshot, Rod, thumbnail, dan administrasi katalog. | CAT-01–CAT-06, AUD-04, ADM-01, ADM-03 | Import invalid tidak mengubah snapshot aktif; publish atomik/idempotent; admin route, UI, authorization, dan audit lulus. |
| Fase 2 — Player Trade Core | Inventory, listing, discovery, search, dan simulator W/F/L. | INV-01–INV-04, LST-01–LST-06, SRC-01–SRC-05 | Alur UI lengkap; stale inventory ditolak; redaction bersih; search p95 tercapai. |
| Fase 3 — Recommendation & Negotiation | Rekomendasi inventory dan negotiation terstruktur sampai proposal diterima. | REC-01–REC-06, NEG-01–NEG-07 | Route/UI operasional; stale version, invalidation, expiry, authorization, dan concurrency test lulus. |
| Fase 4 — Pre-deal, Reveal & Trust Operations | Pilihan server, final deal `fee = 0`, reveal terbatas, report, suspension, dan audit operator. | NEG-08, DEL-01–DEL-08, AUD-01–AUD-03, ADM-02, ADM-04–ADM-05 | Tidak ada reveal prematur; finalisasi/retry atomik; disclaimer tampil; minimum trust operations tersedia. |
| Fase 5 — Wallet, Payment & Dispute Finansial | Deposit, ledger, fee, callback, refund/compensation, dan dispute finansial. | WAL-01–WAL-06, PAY-01–PAY-05 | Keputusan pembayaran ditutup; review keamanan, reconciliation, idempotency, dan runbook insiden lulus. |
| Fase 6 — Production Readiness & Launch | Migration rollout, observability, retention, backup/restore, monitoring, dan runbook. | Seluruh NFR dan gerbang operasional | Suite lengkap lulus; RPO/RTO dan retention disetujui; rollout serta rollback tervalidasi. |

Target MVP berakhir pada Fase 4 dengan wallet dan fee nonaktif. Fase 5 tidak menghambat MVP selama UI menyatakan biaya nol secara jelas. Detail status, bukti, gap, dan backlog setiap fase berada di [`development-phases.md`](../development-phases.md). Fase tidak boleh dilompati hanya karena UI atau service berikutnya tersedia.

## 13. Ukuran Keberhasilan

| Ukuran | Kriteria |
|---|---|
| Recommendation coverage | ≥ 95% pencarian dengan seluruh item bernilai menghasilkan sedikitnya satu kombinasi atau alasan eksplisit mengapa tidak ada. |
| Search-to-negotiation | Baseline dikumpulkan 30 hari pertama; target berikutnya ditetapkan dari data tanpa mengarang angka awal. |
| Negotiation-to-deal | Baseline dikumpulkan per kategori dan value band; kegagalan teknis dipisahkan dari reject pengguna. |
| Stale/invalid prevention | 100% fixture proposal basi, inventory berubah, dan listing expired ditolak. |
| Privacy | 0 insiden USN/IGN terungkap sebelum deal `revealed`. |
| Financial integrity | Selisih reconciliation ledger dan provider = 0 untuk transaksi settled setelah memperhitungkan event pending/reversal. |
| Data freshness | Umur snapshot dan status import terlihat; target SLA ditutup setelah proses operasional upload disepakati. |

Ukuran conversion belum diberi target numerik sebelum baseline karena angka tanpa sumber akan menyesatkan. Setelah 30 hari data valid, Product menetapkan target dan mencatat sumber serta tanggalnya.

## 14. Keputusan Terbuka

| No | Pertanyaan | Pemutus | Yang tertahan | Prioritas |
|---|---|---|---|---|
| 1 | Payment provider, metode, mata uang, minimum/maksimum deposit, dan settlement mana yang digunakan? | Product Owner + Finance + Engineering | PAY-02 sampai PAY-05 dan aktivasi Fase 5 | Tinggi |
| 2 | Berapa fee per pihak, kapan quote kedaluwarsa, serta kapan refund diberikan? | Product Owner + Finance | DEL-03 sampai DEL-05, WAL-02, kebijakan dispute | Tinggi |
| 3 | ~~Berapa nilai awal `fair_threshold_percent` dan siapa yang menyetujui perubahannya?~~ | Tertutup 24 Oktober 2026: nilai awal ±10%; admin boleh mengubah secara prospektif dan setiap perubahan wajib diaudit. | REC-04 | Tinggi |
| 4 | ~~Berapa expiry listing, proposal, negotiation, pra-deal, dan identity reveal access?~~ | Tertutup 26 Oktober 2026: listing = 14 hari, proposal = 24 jam, negotiation = 7 hari, pra-deal = 30 menit, dan identity reveal access = 30 hari. Environment non-production menyediakan trigger expiry manual khusus admin untuk pengujian; production tidak mendaftarkan route tersebut. | NEG-06, DEL-03 | Sedang |
| 5 | Bagaimana report, no-show, scam allegation, suspend, dispute, refund, dan appeal ditangani? | Product Owner + Operations + Legal | ADM-04, ADM-05, production monetization | Tinggi |
| 6 | ~~Bagaimana sumber katalog, value, metadata Rod, dan gambar diperbarui?~~ | Tertutup 25 Oktober 2026: admin mengunggah CSV partial-upsert ke staging dan memublikasikannya manual; Rod serta thumbnail dikelola terpisah di admin. Tidak ada fetch atau scheduler sumber eksternal. | CAT-03 sampai CAT-06 | Tinggi |
| 7 | Berapa retensi audit, proposal, identity snapshot, payment event, dan data akun setelah penghapusan akun? | Product Owner + Legal + Security | AUD-02, privacy policy, production launch | Tinggi |
| 8 | Berapa target RPO/RTO dan strategi backup/restore produksi? | Engineering + Product Owner | Gerbang Fase 6 | Sedang |

Setelah diputuskan, baris tidak dihapus. Pertanyaan dicoret dan kolom Pemutus diganti dengan `Tertutup [tanggal]: [keputusan dan alasan]`; keputusan arsitektur yang mahal dibalik naik menjadi ADR baru.

## 15. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| CSV admin salah format atau memuat perubahan value tidak disengaja | Katalog atau rekomendasi salah. | Staging validation, preview diff, warning perubahan di atas 25%, konfirmasi publish, checksum, dan published snapshot immutable. |
| Nama item berbeda antar sumber | Duplikasi atau value menempel ke item salah. | Stable internal ID, curated aliases, manual mapping queue, larang auto-create dari fuzzy match. |
| Inventory/identitas palsu | Waktu pengguna terbuang dan potensi scam. | Label unverified, report/suspend, disclaimer, verifikasi Roblox sebagai fase terpisah. |
| Identitas bocor lewat serializer/log/cache | Privasi tidak dapat dipulihkan. | DTO/redaction terpisah, server-only policies, privacy regression tests, log scrubbing, cache scope per user/deal. |
| Race condition final accept | Debit ganda/parsial atau reveal salah. | Locked/idempotent finalization, unique key, immutable snapshot, concurrency integration tests. |
| Callback payment duplikat/palsu/out-of-order | Saldo tidak didukung dana nyata. | Signature dan amount validation, unique event/reference, state machine, reconciliation. |
| Pengguna menghindari biaya setelah identitas terbuka | Model bisnis gagal. | Debit sebelum reveal dalam transaksi yang sama; tidak ada endpoint reveal terpisah tanpa entitlement committed. |
| Tidak ada chat mengurangi fleksibilitas | Sebagian trade sulit dinegosiasikan. | Reason code dan counteroffer yang cukup ekspresif; evaluasi data sebelum mempertimbangkan chat yang menambah risiko moderasi. |
| Sistem dianggap menjamin trade | Sengketa reputasi dan hukum. | Disclaimer konsisten sebelum final acceptance, status self-declared, tidak memakai istilah escrow/verified tanpa bukti. |
| Scope wallet menghambat validasi produk inti | Waktu rilis discovery terlalu lama. | Tiga fase dengan feature flag dan gerbang; wallet baru aktif setelah keputusan finansial dan operasional ditutup. |
