# ADR-009 — Baseline operasi production Linux VM

**Status:** Berlaku · **Ditetapkan:** 26 Oktober 2026 · **Rujukan:** seluruh NFR, AUD-02, ADM-04, ADM-05

## Keadaan

MVP Fase 0–4 telah memiliki alur produk, tetapi belum memiliki kontrak deployment, health readiness, retensi, serta recovery yang dapat diuji. Production ditargetkan pada satu Linux VM. Storage backup off-host dan akses host belum tersedia, sehingga launch belum boleh dilakukan.

## Keputusan

Production memakai Nginx, PHP 8.4-FPM, PostgreSQL 16, systemd scheduler, release directory, shared `.env`/storage, dan atomic `current` symlink. `/up` adalah liveness tanpa dependency; `/ready` fail-closed untuk PostgreSQL, heartbeat scheduler maksimal tiga menit, writable catalog storage, dan ruang disk minimum 2 GB. Semua request memperoleh correlation ID. Log production berbentuk JSON ke `stderr`, level `info`, dengan retensi infrastruktur 30 hari.

Backup PostgreSQL custom-format dan thumbnail dibuat tiap jam dengan checksum. Lokal menyimpan 48 hourly dan 14 daily copy. Restore drill bulanan memakai PostgreSQL disposable. Targetnya RPO satu jam dan RTO empat jam. Launch tetap diblokir sampai backup terenkripsi berhasil disalin ke lokasi off-host dan akses host tersedia.

Retensi dijalankan oleh `ops:retention`: dry-run secara default dan mutasi hanya dengan `--execute` saat `RETENTION_ENABLED=true`. Identity snapshot/reveal entitlement disimpan 90 hari; report resolved/dismissed dan negotiation terminal tanpa deal 180 hari; deal graph, suspension selesai, dan audit satu tahun. Report open/reviewing, value snapshot, serta sejarah katalog tidak dihapus. Penghapusan akun tidak tersedia pada MVP.

Moderasi tetap manual: review awal maksimal 24 jam, `suspected_scam` dan `identity_misuse` diprioritaskan, tidak ada auto-suspend, no-show tidak memberi kompensasi, dan report tertutup hanya dapat dibuka kembali ke review dalam tujuh hari.

## Ditolak — Deployment in-place

Menimpa checkout aktif memperbesar waktu henti dan membuat rollback bergantung pada rekonstruksi file. Release immutable dengan switch symlink memberi rollback aplikasi yang cepat dan dapat diaudit.

## Ditolak — Health check hanya memeriksa HTTP 200

Liveness saja dapat tetap hijau saat database, scheduler, storage, atau disk gagal. Readiness terpisah mencegah traffic masuk ke instance yang tidak mampu melayani operasi penting.

## Ditolak — Backup lokal sebagai gerbang launch yang cukup

Kerusakan disk atau VM dapat menghilangkan aplikasi dan seluruh backup lokal sekaligus. Karena provider belum dipilih, implementasi tetap provider-neutral, tetapi encrypted off-host copy wajib sebelum launch.

## Konsekuensi

- Deploy menolak berjalan tanpa penanda off-host backup siap dan membutuhkan izin eksplisit terpisah untuk migration persisten.
- Retensi dapat mengurangi data sensitif tanpa mengubah snapshot katalog/value historis.
- Monitor eksternal wajib memeriksa `/up` dan `/ready` tiap menit serta alert setelah dua kegagalan berurutan.
- Rotasi log dan pengiriman alert adalah tanggung jawab infrastruktur, bukan proses PHP.
- Fase 6 berstatus `Ready for rollout — blocked by off-host backup`, bukan selesai.
