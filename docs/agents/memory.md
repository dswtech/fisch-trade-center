# Memory — Fisch Trade Center

| Item | Keterangan |
|---|---|
| Dokumen | Memory kerja agen |
| Terakhir diperbarui | 26 Oktober 2026 |
| Pemilik | Engineering |
| Status | Aktif |

## Keadaan repository

- Branch aktif `main`; remote `origin` menunjuk ke `git@github.com:dswtech/fisch-trade-center.git`.
- Stack aplikasi: Laravel 13.38.0, Inertia 3.5.2, React 19.3.1, Tailwind CSS 4.3, dan PostgreSQL sebagai target produksi. Seluruh test backend dan E2E memakai SQLite disposable.
- Checkpoint sebelum redesign tersimpan sebagai commit `a77ac3d` (`chore: checkpoint phase two wip before catalog import redesign`). Kondisi checkpoint: Vitest 8/8 dan TypeScript lulus; PHPUnit 41 test memiliki 1 error dan 1 failure.
- Collision v8.9.5 dipasang sebagai dev dependency bersama Whoops v2.18.7. `php artisan test` kini tersedia; tidak ada package existing yang diperbarui atau dihapus.

## Keputusan aktif

- Kategori trade awal hanya `rod_skin`, `boat`, `relic_set`, dan `other` dengan label Rod Skin, Boats, Relic & Sets, dan Lain-lain.
- Rod adalah master data non-tradable. Relasi Rod Skin ke Rod nullable dan ditampilkan sebagai `Undefined Rod` jika belum diisi.
- Akuisisi katalog/value memakai upload CSV UTF-8 manual oleh admin. Upload hanya membuat staging dan preview; publish manual membuat snapshot immutable baru secara atomik.
- Import CSV bersifat partial upsert. Baris yang tidak dikirim tetap dibawa dari snapshot aktif; value kosong menghapus value item pada snapshot baru; perubahan di atas 25% menjadi warning yang perlu dikonfirmasi.
- `catalog_items.public_id` adalah `item_key` stabil. Template terkini dapat diunduh kembali dari admin dan menetralkan awalan formula spreadsheet.
- Thumbnail dikelola manual per item pada filesystem disk configurable, menerima JPG/PNG/WebP maksimal 2 MB. Item tanpa thumbnail memakai asset fallback kategorinya.
- Authentication memakai email/password; USN dan IGN dimasukkan manual dan tetap privat. Negosiasi tidak memakai chat bebas.
- W/F/L memakai ambang Fair awal ±10%; perubahan admin prospektif dan diaudit.
- PostgreSQL adalah database persisten; SQLite hanya untuk test disposable. Operasi tulis database persisten memerlukan instruksi eksplisit.
- Windows Scheduled Task `FischTradeCenterScheduler` tetap memanggil `schedule:run`, tetapi aplikasi tidak lagi mendaftarkan job import value.
- Roadmap aktif memakai tujuh fase capability. Target MVP berakhir pada Fase 4 dengan wallet/fee nonaktif dan `fee = 0`; Fase 5 adalah monetisasi pasca-MVP, sedangkan Fase 6 adalah production readiness.
- PRD utama berada di `docs/agents/prd/README.md`; manifest dokumentasi menjadi pintu masuk untuk urutan baca.

## Pekerjaan redesign katalog yang terverifikasi

- Endpoint admin tersedia untuk unduh template, staging CSV, publish, master Rod, assignment Rod Skin, serta upload/replace/delete thumbnail.
- Fetch Google Sheets, konfigurasi sumber, command `values:import`, registrasi schedule, manifest Skin lama, dan jalur metadata eksternal telah dihapus.
- DTO katalog, inventory, listing, dan discovery memakai resolver thumbnail yang sama. Katalog menampilkan metadata Rod dan fallback `Undefined Rod`.
- Seeder/fixture/filter/copy trade tidak lagi menawarkan Fishing Rod sebagai item tradable.
- Cleanup persisten yang diotorisasi dijalankan setelah verifikasi ulang tepat: 1 item Fishing Rod, 0 inventory entry, 1 value, 26 referensi listing, dan 26 listing. Dataset synthetic tersebut dihapus dalam satu transaksi.
- Guard cleanup tersedia sebagai command one-time dan menolak eksekusi jika hitungan target berubah. Test disposable memverifikasi jalur exact-count transaksional.
- PRD dinaikkan ke revisi 1.3. ADR-008 menggantikan bagian akuisisi terjadwal ADR-002, sedangkan prinsip staging dan snapshot immutable dipertahankan.

## Penutupan gap Fase 0–2

- Mutation authentication, preference/profile, inventory/listing, dan admin diberi rate limit; feature test membuktikan respons 429 setelah batas terlewati.
- Admin dapat mengubah alias/status katalog serta rename/deactivate Rod melalui route dan UI. Mutation diaudit; Rod inactive tidak dapat dipilih untuk assignment baru.
- Publish CSV mempunyai failure-injection test yang membuktikan item, snapshot, value, dan audit event rollback ketika insert value gagal.
- Halaman edit listing kini mempunyai route. Pengurangan inventory diuji menginvalidasi listing yang tidak lagi dapat dipenuhi.
- Homepage aktif memakai data nyata, menyembunyikan badge synthetic secara default, dan mengganti rekomendasi palsu dengan jalur simulator yang operasional.
- INV-04 belum memerlukan implementasi tambahan karena tidak ada kategori aktif dengan atribut wajib. Atribut baru harus ditambahkan ketika aturan kategori konkret disetujui.
- Lima migration pending dari `2026_10_25_000100` sampai `2026_10_26_000100` dijalankan pada database persisten sebagai batch 2 setelah otorisasi eksplisit. `migrate:status` memastikan semuanya berstatus `Ran`.
- `PRODUCT.md` diselaraskan dengan roadmap Fase 0–6, CSV manual, kategori aktif, Rod non-tradable, thumbnail, dan batas MVP tanpa pembayaran.

## Verifikasi terakhir

- `php artisan test` pada SQLite `:memory:`: 33 test, 257 assertion, lulus setelah Collision dipasang.
- Vitest lulus 9/9; TypeScript strict typecheck lulus; Vite build lulus; Playwright desktop/mobile lulus 16/16 termasuk WCAG A/AA setelah target tombol metadata admin diperbaiki.
- Build lokal memakai Node 22.11.0 dan memberi warning karena Vite meminta 22.12+; build tetap berhasil. Asset `/images/trade-ocean-field.png` diselesaikan saat runtime.
- Audit statis memetakan seluruh 69 requirement aktif tepat ke satu fase utama. Bukti diperiksa melalui route, controller/service, UI, migration, dan test; hasilnya dicatat di `development-phases.md`.
- `composer validate --strict` menyatakan schema valid, dengan warning existing untuk exact constraint Laravel dan Inertia.

## Gap implementasi per fase

- Fase 0 hampir selesai; gap rate limit mutation tertutup, tetapi working tree penutupan gap belum di-commit.
- Fase 1 hampir selesai; gap route/UI status-alias, lifecycle Rod, pembuktian rollback publish, dan rollout migration persisten tertutup.
- Fase 2 hampir selesai; route edit, invalidasi listing, rate limit mutation, dan kontrol homepage tertutup. INV-04 tidak aktif sampai ada aturan atribut kategori konkret.
- Fase 3 baru fondasi; schema/service/UI tersedia tetapi route/controller/presenter, integrasi invalidator/expiry, dan seluruh test negotiation belum tersedia.
- Fase 4 baru fondasi; deal dan trust-operation controller belum terhubung ke route/UI/middleware serta belum memiliki test atomicity, concurrency, dan privacy reveal.
- Fase 5 belum dimulai dan bukan gate MVP. Fase 6 belum siap karena keputusan recovery/retention, monitoring, runbook, rollout, dan re-verifikasi masih terbuka.

## Keputusan terbuka produk

- Provider pembayaran konkret, metode pembayaran aktif, mata uang, minimum deposit, dan kebijakan fee/refund.
- Expiry proposal/negotiation Fase 3, expiry pra-deal/reveal Fase 4, serta retensi audit.
- Kebijakan report, no-show, dispute, suspend, dan refund biaya layanan.

## Next step

1. Review dan commit working tree penutupan gap Fase 0–2 tanpa menjalankan migration persisten.
2. Putuskan expiry proposal/negotiation, lalu hubungkan Fase 3 end-to-end sesuai urutan backlog di `development-phases.md`.
3. Hubungkan Fase 4 setelah gerbang Fase 3 lulus.
4. Migration persisten berikutnya tetap hanya boleh dijalankan setelah pengguna memberi instruksi eksplisit.
5. Operator mengunduh template terkini sebelum setiap batch upload agar selalu membawa `item_key` stabil.

## Cara memperbarui memory

Ubah hanya berdasarkan hasil yang benar-benar terjadi. Catat tanggal, fitur yang berubah, test beserta hasilnya, keputusan baru, blocker, dan next step. Hapus fakta yang sudah tidak berlaku atau pindahkan keputusan arsitektur yang mahal dibalik ke ADR baru.
