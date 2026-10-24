# Memory — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Memory kerja agen |
| Terakhir diperbarui | 24 Oktober 2026 |
| Pemilik | Engineering |
| Status | Aktif |

## Keadaan repository

- Repository diawali dalam keadaan kosong, lalu diinisialisasi sebagai Git repository dengan branch `main` dan remote `origin` menuju `git@github.com:dswtech/fisch-trade-center.git`.
- Fondasi dokumentasi produk dan aturan agen telah dibuat; aplikasi belum di-scaffold.
- Belum ada dependency, lockfile, environment, database, source code, CI, atau test runner.
- Tidak ada package yang dipasang dan tidak ada operasi database yang dijalankan.

## Keputusan aktif

- Arsitektur target: Laravel 13 + Inertia + React 19.3 dalam satu aplikasi; Node.js hanya toolchain build frontend.
- UI target: Tailwind CSS 4.3 dan shadcn/ui stable terbaru saat bootstrap.
- Kategori awal: Skins, Boats, dan Fishing Rods dengan model katalog generik.
- Value disinkronkan server-side dari sumber eksternal, divalidasi, lalu dipublikasikan sebagai snapshot immutable.
- Authentication memakai email/password; USN dan IGN dimasukkan manual tanpa verifikasi Roblox pada fase awal.
- Negosiasi hanya memakai proposal/counteroffer terstruktur, tanpa chat bebas.
- W/F/L memakai ambang persentase Fair yang wajib dikonfigurasi admin.
- Biaya dipotong saat kedua pihak melakukan final acceptance; identitas baru dibuka setelah finalisasi atomik berhasil.
- Wallet menggunakan ledger immutable dan gateway abstraction.

## Keputusan terbuka produk

- Provider pembayaran konkret, metode pembayaran aktif, mata uang, minimum deposit, dan kebijakan fee/refund.
- Nilai awal `fair_threshold_percent` dan kewenangan approval perubahan nilai.
- Durasi expiry listing, proposal, pra-deal, serta retensi audit.
- Kebijakan report, no-show, dispute, suspend, dan refund biaya layanan.
- Cara memperoleh dan syarat penggunaan sumber sinkronisasi Value List dalam produksi.

Keputusan terbuka di atas menahan implementasi bagian terkait, bukan penulisan fondasi aplikasi yang tidak bergantung padanya.

## Progres terakhir

- PRD, knowledge, memory, manifest, enam ADR, root agent instruction, Claude pointer, dan enam rules telah dibuat sebagai paket awal.
- Referensi PRD, panduan dokumentasi, serta struktur Value List Skins telah ditinjau pada 24 Oktober 2026.
- Pemeriksaan akhir dokumentasi lulus: PRD memiliki 15 bagian berurutan, 68 definisi requirement unik, dan distribusi MoSCoW `M=52`, `S=12`, `C=3`, `W=1` (Must 76,5%).
- Enam ADR masing-masing memuat keadaan, keputusan, konsekuensi, dan sedikitnya dua alternatif yang ditolak.
- Seluruh tautan relatif valid dan tidak ada dokumen agen yatim terhadap manifest.
- Tidak ada aplikasi, package, lockfile, environment, atau database yang dibuat maupun dijalankan.
- Fondasi dokumentasi disiapkan sebagai initial commit untuk branch `main`.

## Next step

1. Review dan setujui PRD serta keputusan terbuka yang memblokir fase pertama.
2. Minta persetujuan dependency sebelum bootstrap Laravel/Inertia/React/Tailwind/shadcn.
3. Setelah persetujuan dependency, scaffold aplikasi tanpa menjalankan migration terhadap database persisten.
4. Tetapkan kontrak testing dan CI bersamaan dengan skeleton aplikasi, sebelum fitur produk pertama.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, berkas/fitur yang berubah, test yang dijalankan beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.
