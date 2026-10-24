# ADR-003 — Negosiasi memakai proposal terstruktur tanpa chat bebas

**Status:** Berlaku · **Ditetapkan:** 24 Oktober 2026 · **Rujukan:** NEG-01 sampai NEG-08, DEL-01 sampai DEL-03

## Keadaan

Trade membutuhkan tawar-menawar kombinasi item, tetapi chat bebas memperkenalkan moderasi bahasa, spam, phishing, pertukaran identitas sebelum waktunya, dan data pribadi yang sulit diredaksi. Produk awal perlu membuktikan apakah komposisi item, value, dan counteroffer cukup untuk mencapai deal.

## Keputusan

Negotiation hanya menerima action dan data terstruktur: propose, counter, accept, reject, cancel, expiry, reason code, item/quantity, serta pilihan Public/Private Server. Setiap proposal menjadi version immutable; satu version aktif dan version sebelumnya berstatus superseded. Tidak ada message body bebas pada fase awal.

## Ditolak — Chat bebas ditambah proposal terstruktur

Chat memberi fleksibilitas maksimal, tetapi pengguna dapat membagikan USN, link berbahaya, kontak eksternal, atau pelecehan sebelum sistem mempunyai kebijakan, filter, report evidence, retention, dan operator capacity. Risiko ini tidak sebanding dengan kebutuhan validasi awal.

## Ditolak — Chat bebas tanpa proposal terstruktur

Komposisi akhir harus diekstrak manual dari percakapan, sehingga stale offer, audit, W/F/L, accept version, dan final deal tidak dapat ditegakkan dengan andal. Sistem kembali menjadi kanal komunikasi, bukan fasilitas trade.

## Ditolak — Satu offer tanpa counteroffer

Model ini terlalu kaku untuk masalah utama pengguna: menukar kombinasi item sampai value dianggap layak. Pengguna akan keluar platform untuk berunding, sehingga privasi dan monetisasi tidak dapat dijaga.

## Konsekuensi

- UI perlu item picker, quantity control, summary value, timeline version, dan reason code yang cukup jelas.
- Moderasi lebih ringan, tetapi report/no-show/dispute tetap diperlukan.
- Analitik dapat mengukur pola counteroffer tanpa menyimpan pesan pribadi.
- Chat baru boleh ditambahkan melalui kebutuhan produk dan ADR baru setelah kebijakan serta kapasitas moderasi tersedia.
