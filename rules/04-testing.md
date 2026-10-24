# 04 — Pengujian Wajib

- Setiap perubahan perilaku wajib memiliki unit test yang membuktikan jalur berhasil dan jalur gagal utama.
- Backend menggunakan test unit untuk domain/service dan feature/integration test untuk HTTP, authorization, persistence, queue, webhook, serta transaction boundary.
- Frontend menggunakan unit/component test untuk state, form, aksesibilitas, redaksi identitas, dan interaksi penting.
- Alur wallet, final acceptance, reveal identitas, value snapshot, idempotency webhook, dan concurrency wajib memiliki integration test.
- Bug fix wajib diawali atau disertai regression test yang gagal sebelum perbaikan dan lulus sesudahnya.
- Test tidak boleh bergantung pada jaringan eksternal, Google Sheets, Fisch Wiki, Roblox, atau payment gateway nyata; gunakan fake/fixture yang terversi.
- Test database hanya boleh memakai database disposable sesuai `02-database-safety.md`.
- Jangan menghapus, melemahkan, atau melewati test hanya agar pipeline lulus. Jelaskan kegagalan yang tidak terkait tugas.
