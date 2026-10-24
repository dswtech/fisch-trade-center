# Discovery Dashboard

## Scope and mode

`Operate` surface untuk dashboard discovery Fase 1, dengan search, simulator W/F/L, snapshot health, rekomendasi, dan listing terbaru dalam satu alur responsif.

## Audience, job, and constraints

Player FISCH datang dari ponsel maupun desktop untuk mencari item, membandingkan Give/Receive, dan membuka listing tanpa melihat identitas Roblox pihak lain. Data pada comp bersifat synthetic dan wajib diberi label `Data demo`. UI bilingual, dark-first dengan light mode, WCAG 2.2 AA, dan tidak memuat negotiation, wallet, payment, atau chat.

## Direction contract

**THESIS:** Search menjadi pintu masuk, perbandingan trade menjadi bukti, dan listing menjadi tindakan berikutnya. Surface menolak hero marketing dan dashboard metrik generik.

**OWN-WORLD:** App shell midnight navy–indigo dengan garis biru terukur, cahaya cyan/violet yang hanya hidup di workbench Give/Receive, permukaan data yang tenang, serta kuning terbatas untuk primary action dan focus. Listing memakai kartu padat, bukan panel bercahaya.

**STORY:** Player mencari item, menyusun atau menilai dua sisi trade pada snapshot yang sama, melihat perspektif W/F/L secara eksplisit, lalu membandingkan listing terbaru tanpa kehilangan konteks.

**FIRST VIEWPORT:** Sidebar dan utility header membingkai command bar penuh di atas. Di bawahnya, workbench Give/Receive mengambil dua pertiga lebar; snapshot health dan rekomendasi menempati sepertiga kanan. Grid `Listing Trade Terbaru` mengisi bagian bawah dan mulai terlihat pada viewport pertama. Approved comp: `.impeccable/mocks/hybrid-02-search-first.png`.

**FORM:** Hybrid A+B, search-first, dipilih pengguna dari tiga comp setelah direction seed `a1489822`. Signature interaction adalah pemilihan item yang memperbarui balance line, totals, delta, dan perspective label tanpa memindahkan konteks.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved decisions

Tidak ada keputusan visual terbuka untuk surface ini. Data produksi dan artwork resmi tetap di luar scope sampai izin sumber tersedia.
