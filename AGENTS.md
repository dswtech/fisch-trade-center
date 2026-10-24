# Petunjuk Agen — Fisch Trade Center

Dokumen ini adalah pintu masuk wajib bagi setiap coding agent yang bekerja di repository ini.

## Urutan baca wajib

Sebelum menganalisis, merencanakan, menjalankan perintah, atau mengubah berkas:

1. Baca **seluruh** berkas Markdown di [`rules/`](rules/) menurut urutan nama berkas.
2. Baca manifest [`docs/agents/README.md`](docs/agents/README.md).
3. Baca PRD dan seluruh ADR aktif yang relevan dengan tugas.
4. Baca [`docs/agents/knowledge.md`](docs/agents/knowledge.md).
5. Baca [`docs/agents/memory.md`](docs/agents/memory.md) terakhir agar memperoleh keadaan terkini.

Jika instruksi saling bertentangan, hentikan tindakan yang berisiko dan minta keputusan pengguna. Instruksi pengguna yang terbaru tetap tidak membatalkan larangan keamanan, database persisten, atau pengelolaan secret tanpa pernyataan yang eksplisit.

## Kewajiban kerja

- Pertahankan requirement ID, nomor ADR, dan jejak keputusan; jangan gunakan ulang ID lama.
- Perbarui dokumentasi sumber ketika perilaku produk berubah. Jangan mengubah artefak bangkitan secara langsung.
- Setiap perubahan perilaku wajib disertai unit test. Tambahkan integration/feature test untuk alur lintas modul, transaksi, otorisasi, dan kondisi balapan.
- Jangan memasang atau memperbarui dependency sebelum pengguna menyetujuinya secara eksplisit.
- Jangan menjalankan operasi tulis terhadap database persisten kecuali pengguna memerintahkannya secara eksplisit.
- Jangan mengekspos secret atau environment server ke bundle maupun environment client.
- Perbarui `docs/agents/memory.md` pada akhir pekerjaan material dengan fakta yang telah diverifikasi, keputusan baru, hasil verifikasi, dan pekerjaan berikutnya.

## Bahasa dan format

- Dokumentasi produk menggunakan Bahasa Indonesia yang ringkas dan dapat diuji.
- Nama kode, field, state, dan kontrak teknis menggunakan Bahasa Inggris agar konsisten dengan source code.
- Keputusan arsitektur yang mahal dibalik harus menjadi ADR baru; jangan menimpa isi ADR lama.
