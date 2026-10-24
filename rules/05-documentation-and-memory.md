# 05 — Dokumentasi dan Memory

- PRD adalah sumber kebutuhan produk; desain teknis tidak boleh diam-diam menambahkan aturan produk.
- Satu requirement ID hanya didefinisikan dalam satu dokumen. Dokumen lain merujuk ID tersebut tanpa menyalin definisinya.
- ADR lama tidak ditulis ulang. Keputusan pengganti dibuat sebagai ADR baru dan status ADR lama diubah menjadi `Digantikan ADR-nnn`.
- Setiap ADR wajib memuat keadaan, keputusan, sedikitnya dua alternatif masuk akal yang ditolak, konsekuensi, tanggal, status, dan rujukan.
- Semua dokumen agen harus tercantum di `docs/agents/README.md`; dokumen yang tidak terdaftar dianggap yatim.
- Perbarui `docs/agents/memory.md` setelah perubahan material. Memory hanya memuat fakta terverifikasi, keputusan aktif, progres, hasil test, blocker, dan next step—bukan spekulasi.
- Jangan menulis nama orang, tanggal, angka, atau hasil pengujian yang tidak bersumber. Gunakan peran sebagai pemilik dokumen.
- Tautan relatif, requirement ID, metadata, dan manifest harus diperiksa sebelum penyerahan.
