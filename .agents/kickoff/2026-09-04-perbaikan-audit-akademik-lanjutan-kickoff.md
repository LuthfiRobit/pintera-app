# Kickoff — Perbaikan Audit Akademik Lanjutan (IDOR, RPP Verify, Bentrok Jadwal, Resolver Precedence)

Kamu (agent baru) tidak punya akses ke diskusi panjang yang menghasilkan dokumen ini. Baca file ini secara lengkap sebelum menyentuh kode apapun.

## Konteks

Repo: `d:\laragon\www\pintera-app` (Laravel 12 / PHP 8.3, aplikasi pendidikan multi-tenant "Pintera"). Branch kerja: `akademik-v2` (LANJUT DI BRANCH INI, jangan buat branch baru). Base commit sebelum paket ini: `dc10a17d`.

Ini paket perbaikan dari audit menyeluruh 2 putaran modul Akademik (SEPENUHNYA TERPISAH dari 2 paket sebelumnya di branch yang sama — Kelompok A "siklus hidup kelas_id siswa" dan Kelompok B "peringatan tingkat Kenaikan Kelas", keduanya sudah SELESAI). Paket ini menutup 1 IDOR Critical (celah keamanan aktif, dua pintu berbeda) + 3 bug Important (RPP verify gagal total untuk role yayasan, bentrok jadwal berbasis ID bukan waktu nyata, precedence resolver Fase/Kurikulum salah).

## Dokumen yang WAJIB dibaca, urutan ini

1. **Spec (keputusan final lewat brainstorming, dengan audit kode/data konkret di setiap keputusan):** `.agents/specs/2026-09-04-perbaikan-audit-akademik-lanjutan.md`
2. **Plan implementasi (7 task, TDD, kode lengkap):** `.agents/plans/2026-09-04-perbaikan-audit-akademik-lanjutan.md`

Baca PLAN-nya secara utuh dulu sebelum mulai task pertama.

## Keputusan Kritis yang TIDAK BOLEH diubah tanpa konfirmasi ulang ke user

1. **IDOR ini punya DUA PINTU BERBEDA — tutup KEDUANYA, jangan cuma salah satu.**
   - **Pintu #1 (nilai yang DITULIS)**: `resolveLembagaId()` di `ResolveLembagaScopeTrait` — mengontrol `lembaga_id` BARU yang ditulis saat CREATE. Actor non-platform TIDAK PERNAH memakai `lembaga_id` dari request; selalu dari `session('active_lembaga_id')` (yayasan) atau `$actor->lembaga_id` (lembaga-scope).
   - **Pintu #2 (akses ke BARIS EXISTING)**: `authorizeExistingAssignmentScope()`/`authorizeExistingMappingScope()` — dipanggil di `edit()`/`update()`/`destroy()`, memverifikasi baris yang di-route-model-binding (via `{id}` di URL) memang MILIK actor, SEBELUM logic lain apapun jalan. Ini pintu TERPISAH dari pintu #1 — `update()` bahkan tidak pernah mengubah `lembaga_id` sama sekali, jadi `resolveLembagaId()` TIDAK RELEVAN untuk `edit`/`update`/`destroy`; SATU-SATUNYA proteksi jalur itu adalah pintu #2. Kalau kamu cuma menerapkan pintu #1 dan lupa pintu #2 (atau sebaliknya), paket ini TIDAK menutup kerentanannya secara penuh.
2. **KENAPA BUKAN Model Observer/`saving()` hook**: `.ai/rules/models.md` yang sudah direkam project ini secara eksplisit melarang ("No model Observers or lifecycle-hook closures — side effects... go in Actions/Services explicitly"). Solusi paket ini SENGAJA memakai pola "derive, jangan validate" (tidak pernah menerima input berbahaya sejak titik masuk) + pemeriksaan eksplisit di controller untuk baris existing — BUKAN Observer. Precedent yang jadi acuan: `CreatePersonAction::resolveYayasanId()` (`app/Domains/Identity/Actions/CreatePersonAction.php:41-58`) dan `GuruController::resolveLembagaId()` (`app/Http/Controllers/Admin/GuruController.php:257-263`) — baca keduanya kalau ragu polanya.
3. **Global assignment/mapping (`lembaga_id = NULL`) HANYA boleh dibuat/diubah/dihapus `platform`.** Berlaku KONSISTEN untuk `KurikulumAssignment` DAN `FaseDefaultMapping`, di KEDUA pintu.
4. **`session('active_lembaga_id')` WAJIB diverifikasi ulang di titik pakai** — `Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists()`. JANGAN percaya mentah-mentah walau `ResolveTenant.php` (middleware yang men-SET session ini) sudah memverifikasi saat SET — verifikasi di titik pakai tetap wajib (skenario: `yayasan_id` actor berubah setelah session di-set, session jadi stale).
5. **Actor `lembaga`-scope biasa di `index()` TIDAK disentuh** — filter existing-nya (`whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id)`) sudah benar sejak awal, cuma cabang `platform`/`yayasan` yang rusak.
6. **4+3 test existing meng-encode perilaku LAMA sebagai "benar" — HARUS ditulis ulang, BUKAN dihapus diam-diam atau dibiarkan merah.** Plan Task 2 Step 1 & 8, Task 3 Step 1 & 7 mendaftar persis test mana dan bagaimana menulis ulangnya. `KurikulumAssignmentDestroyGuardTest.php` juga punya 1 test yang perlu penyesuaian SETUP (kasih actor `yayasan_id` yang cocok), BUKAN assertion — baca Task 2 Step 9 dengan teliti.
7. **2 temuan Minor (race condition bobot Komponen Penilaian, fallback guru acak RppController) TIDAK masuk paket ini sama sekali** — jangan "sekalian" ditambal walau kelihatan gampang.
8. **Tidak pindah branch** — semua kerja tetap di `akademik-v2`.

## Fakta Operasional yang Perlu Diketahui

- Nomor baris kode di plan diambil saat plan ditulis — beberapa file (`KurikulumAssignmentController.php`, `RppController.php`, dll) mungkin sudah sedikit bergeser. Beberapa step plan SUDAH eksplisit minta "baca dulu struktur aktual sebelum finalisasi" (Task 4 Step 2, Task 5 Step 1) — ikuti instruksi itu, jangan asumsikan nomor baris 100% akurat di seluruh plan.
- Task 2 dan Task 3 SALING CERMIN (KurikulumAssignment vs FaseDefaultMapping) — pola identik, tapi `FaseDefaultMapping` TIDAK punya kolom `tahun_ajaran_id` (jangan salin logic terkait tahun ajaran dari Task 2 ke Task 3).
- Migration TIDAK ada di paket ini — semua perbaikan murni kode aplikasi (Action/Controller/View/Service), tidak ada perubahan schema.
- Ikuti CLAUDE.md project ini: jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task yang menyentuh PHP/Blade.
- Baca `.ai/rules/index.md` dan rule file relevan (`.ai/rules/actions.md`, `.ai/rules/controllers.md`, `.ai/rules/models.md` — KHUSUSNYA yang soal larangan Observer, `.ai/rules/tests.md`) sebelum menulis kode di setiap task.

## Catatan Serah-Terima (di luar scope paket ini)

1. **2 temuan Minor belum diverifikasi independen**: race condition validasi total bobot di `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction` (tanpa `lockForUpdate()`); fallback guru acak diam-diam di `RppController.php` saat `guru_id` tidak dikirim eksplisit.
2. **[Important — impact tinggi, likelihood rendah, JANGAN dianggap remeh] Pola "percaya `session('active_lembaga_id')` tanpa verifikasi ulang" di controller LAIN**: `GuruController`, `JalurPpdbController`, `KalenderAkademikController`, `PengaturanAkademikController`, `GelombangPpdbController` (kemungkinan ada lagi, belum disisir lengkap). Semuanya membaca session itu langsung tanpa re-verifikasi kepemilikan yayasan di titik pakai — beda dari pendekatan paket ini untuk `KurikulumAssignment`/`FaseDefaultMapping`. Trigger jarang (butuh `yayasan_id` actor berubah di tengah sesi aktif), TAPI dampaknya IDOR kalau terjadi.
3. Area yang belum diaudit sama sekali dari 2 putaran audit sebelumnya: sebagian besar sudah tercakup, kecuali detail implementasi internal beberapa Service yang cuma dicek dari sisi pemanggilnya (lihat riwayat sesi untuk daftar lengkap kalau perlu).

## Kalau Menemukan Ambiguitas atau Gap Saat Implementasi

**STOP dan laporkan ke user — JANGAN mengambil keputusan desain baru sendiri di tengah eksekusi.** Spec dan plan ini sudah melalui audit kode konkret berkali-kali (bukan asumsi). Contoh situasi yang HARUS stop-and-report:

- Menemukan test lain (di luar yang sudah didaftar Task 2/3) yang JUGA meng-encode perilaku lama (yayasan=platform) — laporkan, jangan langsung tulis ulang sendiri tanpa konfirmasi cakupannya lengkap atau tidak.
- `RppController::verify()`, `CreateJadwalPelajaranAction`, atau resolver ternyata sudah berubah signifikan sejak spec ditulis (bukan cuma pergeseran baris) — laporkan.
- Menemukan titik IDOR ke-3 (di luar 2 pintu yang sudah didaftar) untuk `KurikulumAssignment`/`FaseDefaultMapping` — laporkan sebagai temuan baru, jangan langsung ditambal tanpa tahu apakah pola perbaikannya sama atau butuh pendekatan berbeda.

## Mulai dari Mana

Plan sudah dipecah jadi 7 task berurutan. Kerjakan mulai Task 1, TDD (test dulu, gagal, implementasi, lolos, commit), satu task per commit. Task 2 dan 3 punya sub-step ekstra (menulis ulang test existing) yang WAJIB dikerjakan, bukan opsional — baca catatan di Keputusan Kritis #6 sebelum mulai kedua task itu. Setelah Task 7 (full suite final), laporkan ke user paket ini selesai, TERMASUK mengingatkan "Catatan Serah-Terima" di atas kalau user belum sempat menindaklanjutinya.
