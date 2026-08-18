# 📝 Handoff Log: Sub-Task 03a — Migrasi Jurnal KBM & Presensi ke Pola Domain Baru

- **Tanggal & Waktu:** 18 Agustus 2026, ~21:15 WIB
- **Terkait Spec:** [`.agents/specs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md)
- **Terkait Plan:** [`.agents/plans/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md)
- **Metode Eksekusi:** Subagent-Driven Development (fresh implementer subagent per task + task-scoped review + final whole-branch review), langsung di branch `akademik-v2`, tanpa worktree (user memilih ini secara eksplisit).
- **Status Sub-Task:** 🟢 SELESAI (COMPLETED)

---

## 1. Apa yang Dikerjakan

Modul Jurnal KBM & Presensi mode Sesi Mapel (dipakai jenjang SMP/SMA/SMK) dipindahkan dari struktur lama (`app/Models/`, `app/Enums/`, `app/Services/`) ke `app/Domains/Akademik/`, mengikuti pola domain-oriented yang sudah dipakai modul Sarpras/Pengadaan/RPP. **Tidak ada perubahan perilaku bisnis** pada alur existing — perilaku dibuktikan identik lewat 1750+ test yang sudah ada sebelumnya, tetap hijau di sepanjang proses.

**5 task implementasi + 1 fix wave final:**

1. **Relokasi Enum, Model, Service** — `StatusSesiPembelajaran`, `StatusPresensi`, `SesiPembelajaran`, `Presensi`, `SesiPembelajaranGenerator` pindah ke `app/Domains/Akademik/{Enums,Models,Services}/`. 16 file konsumen (factory, seeder, test) di-update referensinya.
2. **DTO, FormRequest, Action** — `JurnalPresensiData`, `UpdateJurnalPresensiRequest`, `GenerateSesiHarianAction`, `RecordJurnalDanPresensiAction` (dengan `DB::transaction()`) dibuat sebagai file baru, belum di-wire ke controller manapun.
3. **Controller + Route + View** — `Guru\Akademik\JurnalKbmController` (thin, pakai Action dari Task 2) menggantikan `Guru\SesiPembelajaranController` lama. Route `guru.sesi.*` → `guru.jurnal-kbm.*` (flat, konsisten dengan tetangga `guru.asesmen.*`/`guru.kasus.*`). 2 Blade view dipindah ke `resources/views/portals/guru/akademik/jurnal-kbm/`.
4. **`PresensiAggregationService`** — hitung total Hadir/Izin/Sakit/Alpa/Terlambat per siswa per semester (TDD, 4 test kasus).
5. **`RekapKehadiranController`** — halaman rekap kehadiran semesteran untuk Wali Kelas (TDD, 4 test kasus termasuk isolasi tenant).
6. **Final whole-branch review + fix wave** — review menyeluruh (model paling capable, opus) menemukan 3 Important + 6 Minor; 3 Important + 3 Minor diperbaiki dalam satu batch fix, 3 Minor sisanya sengaja tidak diubah (lihat §2).

**Hasil akhir:** `php artisan test` penuh = **1753 passed, 0 failed** (baseline sebelum sub-task ini: 1742). Base commit `64d84e5`, commit akhir `25e8849`, 10 commit total.

---

## 2. Keputusan Penting yang Diambil

1. **Route naming flat, bukan nested** (`guru.jurnal-kbm.*`, bukan `guru.akademik.jurnal-kbm.*`) — disepakati eksplisit dengan user saat brainstorming, supaya konsisten dengan tetangga langsungnya di file route yang sama. Keputusan soal penyeragaman pola nesting lintas-domain sengaja ditunda ke **FASE 5.1** master plan (Restrukturisasi Rute), bukan diputuskan sepihak di sub-task ini.

2. **Dua bug nyata ditemukan & diperbaiki selama implementasi** (bukan asumsi awal spec/plan, ditemukan implementer subagent lewat integrasi nyata, diverifikasi independen oleh reviewer):
   - **Task 3**: memindahkan validasi dari `$request->validate()` inline (controller) ke FormRequest bertipe-hint mengubah urutan eksekusi Laravel — `authorize()`/`rules()` FormRequest jalan SEBELUM body controller, jadi pengecekan kepemilikan (`authorizeMilikGuru()`) yang tadinya di controller tidak pernah sempat jalan kalau validasi gagal duluan (payload presensi kosong gagal validasi `required`, redirect 302 bocor sebelum sempat 403). Diperbaiki dengan memindahkan pengecekan kepemilikan ke `UpdateJurnalPresensiRequest::authorize()` — pola Laravel yang benar untuk kasus ini.
   - **Task 4**: kode di plan/brief awal saya (`Presensi::query()->select(...)`) akan meng-hydrate hasil sebagai model Eloquent parsial, menerapkan cast enum `StatusPresensi` ke kolom `status` — bikin `pluck('total','status')` punya key berupa objek enum, bukan string, jadi `$byStatus['hadir'] ?? 0` tidak pernah match. Diperbaiki dengan `DB::table('presensi')` (query builder murni, tanpa hydration Eloquent).

3. **Scope creep ditolak sekali** (Task 4): implementer sempat menambah accessor `getNamaAttribute()` ke model `Siswa` yang dipakai di seluruh aplikasi, padahal solusi minimal (pakai kolom asli `nama_lengkap` langsung di 2 file yang diizinkan) sudah cukup. Reviewer menangkap ini, di-revert.

4. **Insiden proses**: `.superpowers/` (direktori scratch/ledger) ternyata sudah ter-*tracked* di git dari sesi lain (project Keuangan tidak terkait) sebelum aturan `.gitignore`-nya efektif. Ditemukan saat review Task 4 (file laporan sesi lain ikut berubah tanpa disengaja di commit fix). Diperbaiki secara struktural (`git rm --cached`, commit `962ea9a`) — bukan cuma ditambal di titik kejadian — supaya tidak berulang di task-task berikutnya.

5. **3 dari 6 temuan Minor whole-branch review sengaja TIDAK diperbaiki**:
   - Urutan import di `routes/admin.php` — dicek, file itu sudah tidak alphabetical sejak sebelum sub-task ini mulai (baseline `64d84e5` sudah punya 3 import yang salah urutan). Bukan regresi dari kerjaan ini.
   - Konvensi lokasi view kini terpecah — `jurnal-kbm` sudah di `portals/guru/akademik/`, tapi tetangga sesamanya (`asesmen/`, `komponen-penilaian/`) masih di `resources/views/guru/` lama. Ini memang konsekuensi sengaja dari migrasi bertahap (per-modul, bukan sekaligus semua) — dicatat di sini supaya Sub-Task 03b/tahap berikutnya tidak bingung kenapa ada dua konvensi.
   - Permission `presensi.isi` (izin "isi presensi", sebuah write-permission) dipakai juga untuk mengunci halaman rekap yang sifatnya read-only. Berfungsi, tapi secara semantik kurang pas — kalau nanti ada Wali Kelas yang tidak mengajar (tidak punya `presensi.isi`) dia tidak akan bisa lihat rekap kelasnya sendiri. Dicatat sebagai catatan desain untuk FASE 4/5, bukan cacat yang menghambat.

---

## 3. Hal yang Perlu Direview Manusia / Tahap Selanjutnya

1. **Verifikasi visual manual di browser** (belum saya lakukan — tidak ada akses browser di eksekusi subagent):
   - Login sebagai guru mapel (permission `presensi.isi`) → buka menu "Jurnal & Presensi" → pastikan sesi hari ini auto-generate & tampil → buka satu sesi → isi materi + ubah presensi → submit → cek redirect & data tersimpan.
   - Login sebagai guru yang jadi Wali Kelas → buka menu baru "Rekap Kehadiran" (sekarang sudah muncul di sidebar, hasil fix wave) → pastikan angka H/I/S/A/T sesuai data yang sudah diisi.

2. **Status Git**: seluruh 10 commit sub-task ini sudah di branch `akademik-v2` lokal, **belum di-push**. Siap di-push atau langsung lanjut ke Sub-Task 03b sesuai keputusan user.

3. **Kesiapan Sub-Task 03b**: **Mode Tematik/Harian untuk KB/TK/SD** — genuinely belum ada kode sama sekali (dikonfirmasi saat audit awal sebelum brainstorming sub-task ini). Belum ada spec/plan-nya, perlu siklus brainstorming baru dari nol. File placeholder masih di baris tabel master plan (`.agents/plans/2026-08-17-1015-...md`) sebagai `⚪ PENDING (belum brainstorming)`.

4. **Temuan whole-branch review yang sengaja dibiarkan** (lihat §2 poin 5) — tidak menghambat, tapi kalau suatu saat ada yang tanya "kenapa jurnal-kbm dan asesmen beda folder", jawabannya ada di sini.
