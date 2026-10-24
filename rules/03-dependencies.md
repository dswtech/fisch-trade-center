# 03 — Dependency dan Toolchain

- Jangan menjalankan `composer install`, `composer update`, `npm install`, `npm update`, `npx`, `pnpm`, `yarn`, `bun`, atau pemasang package lain tanpa persetujuan eksplisit pengguna.
- Persetujuan harus mencakup pemasangan atau pembaruan yang akan dilakukan; keberadaan rencana atau `package.json` bukan persetujuan.
- Sebelum meminta persetujuan, jelaskan package, tujuan, versi/rentang yang diusulkan, serta dampak lockfile.
- Setelah disetujui, gunakan sumber resmi, pilih versi stable yang kompatibel, dan commit lockfile yang dihasilkan.
- Jangan menjalankan software yang baru diunduh atau script lifecycle yang tidak dipahami.
- Hindari dependency baru bila kemampuan framework atau utility kecil lokal sudah cukup; jelaskan trade-off bila memilih dependency.
