# ADR-004 — Transaksi anonim sampai finalisasi berhasil

**Status:** Berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** LST-03, NEG-01, DEL-01 sampai DEL-08

## Keadaan

Pemain memerlukan USN/IGN untuk bertemu di Roblox, tetapi membukanya saat listing atau awal negosiasi memungkinkan kontak di luar platform, penghindaran biaya, dan kehilangan privasi. Sebaliknya, menagih sebelum kedua pihak terikat pada proposal dan server choice yang sama dapat merugikan salah satu pihak.

## Keputusan

Gunakan state machine eksplisit dari listing, negotiation, pra-deal, final acceptance, finalizing, dan revealed. Sebelum `revealed`, lawan hanya menerima alias acak per transaksi serta data item yang sengaja dibagikan. Kedua final acceptance terikat pada proposal version, server choice, fee quote, dan expiry yang sama.

Finalizing mengunci state dan melakukan seluruh pemeriksaan. Reveal entitlement serta snapshot USN/IGN dibuat hanya setelah transaksi finalisasi—termasuk debit bila monetisasi aktif—berhasil commit. Endpoint/serializer melakukan authorization terhadap entitlement per deal; tidak ada flag profil global `revealed`.

## Ditolak — Identitas tampil pada listing

Discovery menjadi mudah, tetapi pemain dapat melewati platform, melakukan spam, dan mengorelasikan inventory dengan akun Roblox. Privasi yang bocor tidak dapat dipulihkan ketika listing dihapus.

## Ditolak — Identitas tampil saat negotiation dibuat

Ini membatasi paparan dibanding listing publik, tetapi pengguna tetap dapat keluar platform sebelum mencapai kesepakatan atau membayar biaya. Pembuatan negotiation belum membuktikan kedua pihak menyetujui komposisi yang sama.

## Ditolak — Reveal setelah satu pihak menekan final accept

Satu pihak dapat memperoleh identitas lawan tanpa komitmen yang setara. Kondisi balapan dan pembatalan pihak lain akan menghasilkan reveal sepihak.

## Konsekuensi

- Seluruh DTO, event, notification, cache, audit, dan log membutuhkan redaction boundary yang diuji.
- Alias harus acak per transaksi agar tidak dapat dikorelasikan lintas listing/negotiation.
- Perubahan USN/IGN setelah deal tidak menulis ulang identity snapshot historis.
- Private Server hanya merupakan pilihan tipe pada pra-deal; detail pertemuan tidak boleh membocorkan identitas sebelum revealed.
- Penghapusan akun dan retensi identity snapshot menunggu kebijakan pada PRD Bagian 14.
