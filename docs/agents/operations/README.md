# Runbook Production — Linux VM

**Target:** Nginx + PHP 8.4-FPM + PostgreSQL 16 pada satu Linux VM. **RPO:** 1 jam. **RTO:** 4 jam. **Status:** siap rollout; launch diblokir backup off-host dan akses host.

## Baseline dan layout

```text
/srv/fisch-trade-center/
├── current -> releases/<timestamp>
├── releases/
└── shared/
    ├── .env
    └── storage/
```

Gunakan `.env.production.example` sebagai daftar variabel, bukan sebagai secret. Owner aplikasi adalah `www-data`; `.env` hanya dapat dibaca proses aplikasi. Pasang konfigurasi Nginx dan unit systemd dari `ops/linux/`. Aktifkan scheduler dan backup timer. Jangan menjalankan queue worker karena MVP memakai `QUEUE_CONNECTION=sync`.

## Deployment

1. Pastikan artifact berasal dari commit/tag yang disetujui, checksum cocok, `.env` lengkap, backup timer sehat, dan encrypted off-host copy terakhir berumur kurang dari satu jam.
2. Jalankan backup lokal dan verifikasi manifest checksum.
3. Jalankan `php artisan migrate --pretend --force` dari release kandidat. Simpan output pada catatan perubahan.
4. Jika ada migration, minta otorisasi eksplisit. Baru set `FTC_ALLOW_MIGRATE=yes`; deploy mengaktifkan maintenance sebelum migration.
5. Jalankan `ops/linux/deploy.sh /absolute/path/artifact` dengan `FTC_OFF_HOST_BACKUP_READY=yes`. Script membuat release, menghubungkan shared state, meng-cache config/route/view, mengganti symlink secara atomik, reload PHP-FPM, lalu memeriksa `/up` dan `/ready`.
6. Pantau error rate, latency, disk, scheduler heartbeat, dan report queue minimal 30 menit.

Script tidak mengotorisasi migration. Flag hanya boleh diberikan setelah persetujuan migration persisten dicatat.

## Rollback

Jika health check sesudah switch gagal, script mengembalikan symlink sebelumnya. Untuk rollback manual: aktifkan maintenance, arahkan `current.next` ke release sehat sebelumnya, `mv -Tf` menjadi `current`, reload PHP-FPM, jalankan `artisan up`, lalu cek `/up` dan `/ready`.

Jangan rollback schema otomatis. Jika release lama tidak kompatibel dengan schema baru, pulihkan aplikasi dan database berdasarkan rencana migration serta backup yang disetujui. Catat correlation ID, release, operator, waktu, dan alasan.

## Backup dan restore

`ops/linux/backup.sh` membuat `database.dump`, archive `catalog-thumbnails`, dan `manifest.sha256`. Timer berjalan hourly; script mempertahankan 48 hourly dan 14 daily copy. Infrastruktur wajib menyalin hasil secara terenkripsi ke lokasi off-host dengan akses terbatas dan menguji umur copy setiap menit/jam sesuai provider.

Restore drill bulanan:

1. Pilih backup off-host yang sudah diunduh ke direktori sementara.
2. Jalankan `ops/linux/restore-drill.sh /absolute/backup/path` pada host non-production dengan database lokal bernama akhiran `_restore_drill`.
3. Pastikan checksum, restore PostgreSQL, tabel/migration, dan archive thumbnail lulus.
4. Bandingkan row count penting (`users`, `catalog_items`, `value_snapshots`, `listings`, `negotiations`, `deals`) dengan manifest operasional backup.
5. Catat waktu selesai. Gagal mencapai empat jam atau checksum mismatch adalah insiden severity tinggi.

## Monitoring dan incident response

Monitor eksternal memanggil `/up` dan `/ready` tiap menit; alert setelah dua kegagalan berurutan. Alert juga wajib untuk disk mendekati 2 GB, backup lebih tua dari satu jam, off-host copy gagal, scheduler heartbeat stale, dan lonjakan error. Log JSON `stderr` disimpan infrastruktur 30 hari dan dicari memakai `correlation_id`.

Saat insiden: tetapkan incident lead, catat waktu/release/correlation ID, lindungi bukti, aktifkan maintenance bila integritas atau privasi terancam, lalu pilih rollback atau recovery. Jangan mengubah ledger/snapshot/audit secara manual. Kebocoran identity, checksum backup gagal, dan data corruption adalah severity tinggi. Setelah pulih, lakukan postmortem dan test regresi.

## Retention dan moderasi

Jalankan `php artisan ops:retention` untuk preview. Tinjau count dan backup terbaru. Eksekusi hanya melalui `RETENTION_ENABLED=true php artisan ops:retention --execute`. Scheduler menjalankannya pukul 02:30 saat flag aktif. Ulangi dry-run dan pastikan count kandidat nol atau sesuai data baru.

Operator meninjau report maksimal 24 jam. Dahulukan `suspected_scam` dan `identity_misuse`. Suspend selalu keputusan manusia. No-show tidak memicu kompensasi. Appeal manual dapat mengubah report tertutup menjadi `reviewing` paling lambat tujuh hari setelah resolusi. Report open/reviewing tidak pernah dipangkas oleh retention.

## Gerbang launch

Launch dilarang sampai seluruh poin ini terbukti: akses host tersedia, encrypted off-host backup aktif, restore drill off-host lulus, monitor eksternal dan alert diuji, konfigurasi secret diset, seluruh suite lulus, dan migration persisten mendapat otorisasi terpisah.
