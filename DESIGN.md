# Design System — Fisch Trade Center

## Arah visual

Surface utama memakai comp `hybrid-02-search-first`: struktur dashboard operasional yang rapat, pencarian sebagai command surface, workbench Give/Receive bernuansa bioluminescent, dan grid listing ringkas. Referensi Metronic dipakai pada hierarchy dan pola operasional, bukan aset, logo, atau source code.

Identitas memakai mark original bertema ubur-ubur/arus dan exchange. Seluruh konten demo serta item art saat ini synthetic dan harus diganti ketika hak sumber produksi disetujui.

## Prinsip

- Dark-first; light mode mempertahankan hierarchy dan kontras.
- Kuning hanya untuk CTA primer, focus ring, dan highlight penting.
- Status W/F/L selalu memakai teks dan warna; warna bukan satu-satunya pembeda.
- Desktop memakai sidebar dan utility header; tablet rail; mobile top bar, drawer, dan bottom navigation lima tujuan.
- Tabel/area kompleks berubah menjadi cards atau stacked rows pada mobile; filter berubah menjadi sheet.
- Semua kontrol utama minimal 44 × 44 px, focus terlihat, dan motion mengikuti `prefers-reduced-motion`.

## Tipografi dan spacing

Heading memakai `Rethink Sans` dengan fallback `Segoe UI`; body memakai keluarga yang sama untuk konsistensi operasional. Skala spacing berbasis 4 px dengan jarak surface utama 12–24 px dan radius 10–18 px.

## Signature interaction

Balance orb di antara Give dan Receive adalah signature interaction tunggal. Orb menampilkan delta, label W/F/L, dan perspektif secara eksplisit; ia berpindah menjadi langkah vertikal pada mobile.

## Aset dan provenance

- `public/images/brand-mark.png` — logo original, prompt tersimpan di `.impeccable/build/brand-mark-prompt.txt`.
- `public/images/sidebar-ocean-art.png` — plate atmosfer sidebar, prompt tersimpan di `.impeccable/build/sidebar-ocean-art-prompt.txt`.
- `public/images/trade-ocean-field.png` — texture workbench dari comp yang disetujui, provenance tercatat di sidecar build Impeccable.

Token machine-readable berada di `resources/design/tokens.json`. Screenshot QA berada di `.impeccable/review/`.
