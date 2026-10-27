# Design System — Fisch Trade Center

## Arah visual

Surface utama memakai comp `hybrid-02-search-first`: struktur dashboard operasional yang rapat, pencarian sebagai command surface, workbench Give/Receive bernuansa bioluminescent, dan grid listing ringkas. Referensi Metronic dipakai pada hierarchy dan pola operasional, bukan aset, logo, atau source code.

Identitas memakai mark original bertema ubur-ubur/arus dan exchange. Seluruh konten demo serta item art saat ini synthetic dan harus diganti ketika hak sumber produksi disetujui.

## Prinsip

- Dark-first; light mode mempertahankan hierarchy dan kontras.
- Kuning hanya untuk CTA primer, focus ring, dan highlight penting.
- Status W/F/L selalu memakai teks dan warna; warna bukan satu-satunya pembeda.
- Desktop memakai sidebar dan utility header; tablet rail; mobile top bar, drawer, dan bottom navigation lima tujuan.
- Tabel/area kompleks berubah menjadi cards atau stacked rows pada mobile; filter Discovery memakai dialog tengah pada desktop dan bottom sheet pada mobile.
- Semua kontrol utama minimal 44 × 44 px, focus terlihat, dan motion mengikuti `prefers-reduced-motion`.

## Tipografi dan spacing

Heading memakai `Rethink Sans` dengan fallback `Segoe UI`; body memakai keluarga yang sama untuk konsistensi operasional. Skala spacing berbasis 4 px dengan jarak surface utama 12–24 px dan radius 10–18 px.

## Signature interaction

Balance orb di antara Give dan Receive adalah signature interaction tunggal. Orb menampilkan delta, label W/F/L, dan perspektif secara eksplisit; ia berpindah menjadi langkah vertikal pada mobile.

## Katalog publik dan account utility

- Katalog memakai grid visual 5 kolom pada desktop lebar, 3 kolom pada tablet, dan 2 kolom pada mobile. Thumbnail/fallback menjadi anchor visual; canonical name dan point value menjadi hierarchy teks utama.
- Toolbar search, filter kategori, dan sort berada sebelum grid. Submit search serta pergantian filter/sort kembali ke halaman pertama; pagination mempertahankan query string dan menyediakan ringkasan halaman yang ringkas pada mobile.
- Menu native `select`, termasuk opsi yang dirender browser di luar surface halaman, wajib memakai foreground dan background sesuai tema agar label tetap terbaca pada dark maupun light mode.
- Detail item memakai artwork besar di samping data value, offer range, Rod/`Undefined Rod`, dan snapshot. CTA kembali ke discovery listing dengan query item terisi.
- Utility header menampilkan CTA `Login` untuk guest. CTA membuka popover form pada desktop dan bottom sheet pada mobile sehingga konteks halaman tetap terlihat; login sukses kembali ke URL lokal sebelumnya. Player melihat bell action state serta menu akun Profil/Logout; menu dapat ditutup dengan Escape atau click-outside dan seluruh focus state wajib terlihat.
- Referensi Fisch Value List dipakai hanya untuk hierarchy informasi katalog. Data, source code, dan aset pihak ketiga tidak disalin.

## Discovery dan penawaran

- Discovery menempatkan ringkasan pencarian `Kamu Berikan → W/F/L → Kamu Terima` sebagai blok pertama. Desktop memakai grid `1fr 96px 1fr`; orb W/F/L berada pada kolom tengah non-absolute dan kedua panel memiliki tinggi sama. Pada mobile susunannya vertikal. Kedua sisi mengarahkan player ke simulator untuk menyusun item; feed `Temukan Trade` berada tepat di bawahnya.
- `Temukan Trade` memakai dua kolom mulai 1.100 px: feed penawaran fleksibel dan rail insight 340 px yang sticky. Heading hasil berada sebelum grid agar kartu pertama sejajar dengan panel rail. `Insight Pasar` dan `Cocok untuk Inventory-mu` dapat diciutkan secara independen tanpa menyimpan state. Di bawah breakpoint tersebut rail berpindah setelah feed tanpa mengubah urutan tugas utama.
- Bell player membuka popover tindakan trade di tempat, bukan berpindah halaman. Popover menampilkan paling banyak lima tindakan terbaru, empty state, dan tautan ke seluruh negosiasi; badge tetap merepresentasikan seluruh tindakan tertunda.
- Kartu penawaran memperlihatkan offered/wanted sebagai dua kelompok item, maksimal tiga thumbnail per sisi, indikator `+N`, total value, umur, alias anonim, dan status W/F/L. Pada mobile kedua sisi ditumpuk vertikal.
- Rail menampilkan snapshot aktif, jumlah penawaran, item paling dicari/ditawarkan, dan maksimal tiga rekomendasi inventory. Guest serta inventory kosong mendapat CTA yang jujur, bukan data contoh.
- Halaman recommendation memakai kartu rapat dua kolom pada desktop dan satu kolom pada mobile. Setiap kandidat menjadi draft interaktif: player dapat menambah maksimal delapan jenis item dari inventory, mengubah quantity sampai batas tersedia, dan menghapus item. Kartu menghitung ulang total, delta, serta W/F/L secara langsung. CTA kartu membuka modal ringkasan; tombol kirim proposal hanya tersedia di modal setelah komposisi ditinjau.
- Ruang negotiation membuka counteroffer sebagai panel inline di bawah proposal aktif. Sisi player editable dari inventory, sisi lawan read-only, keduanya tetap menampilkan thumbnail, quantity, dan value. Ringkasan W/F/L tampil sebelum submit. Pada posisi `LOSE`, aksi primer adalah permintaan terstruktur agar lawan menambah item; penerima melihat banner dan hanya dapat menambah item lalu membalas atau menolak, bukan menerima permintaan sebagai offer.
- Builder `Buat Penawaran` mengikuti komposisi simulator: `Kamu Berikan` dan `Kamu Cari` berdampingan dengan pembanding `VS`, total value, serta kartu item compact yang memiliki kontrol quantity dan remove. Tombol tambah pada tiap sisi membuka picker grid; sisi Berikan hanya menampilkan inventory aktif milik player, sedangkan sisi Cari menampilkan katalog aktif. Pada mobile kedua sisi ditumpuk dan picker menjadi bottom sheet tiga kolom.
- Istilah UI pemain memakai `Penawaran Saya`, `Buat Penawaran`, item yang diberikan, dan item yang dicari. Nama teknis listing tetap dipakai dalam kode dan dokumentasi engineering.
- Referensi Fisch Trading Hub hanya dipakai untuk hierarchy dua kolom dan kepadatan informasi; identitas terbuka, chat, rating, response time, view count, serta aset pihak ketiga tidak diadaptasi.

## Aset dan provenance

- `public/images/brand-mark.png` — logo original, prompt tersimpan di `.impeccable/build/brand-mark-prompt.txt`.
- `public/images/sidebar-ocean-art.png` — plate atmosfer sidebar, prompt tersimpan di `.impeccable/build/sidebar-ocean-art-prompt.txt`.
- `public/images/trade-ocean-field.png` — texture workbench dari comp yang disetujui, provenance tercatat di sidecar build Impeccable.

Token machine-readable berada di `resources/design/tokens.json`. Screenshot QA berada di `.impeccable/review/`.
