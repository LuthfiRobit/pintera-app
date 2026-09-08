# Kickoff: Susulan Gabungan (3 Spec) — Menu Kurikulum Assignment

**Base commit**: `107fca2c` (`docs(kurikulum-assignment): implementation plan gabungan 3 spec susulan`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Lanjutan dari audit besar Kurikulum Assignment (6-item spec sebelumnya SUDAH selesai & terverifikasi — crash 500, `canManageAssignment()`, dll). Setelah itu, user menemukan 2 masalah TAMBAHAN lewat screenshot nyata, plus 1 permintaan restyle:

1. **Relasi `tahunAjaran` ke-scope diam-diam oleh `TenantScope`** — kolom "Tahun Ajaran" tampil "-" untuk baris bukan lembaga yang sedang ditinjau, padahal datanya valid (dikonfirmasi lewat query database langsung). Terjadi di 3 titik: `index()`, `edit()`, dan dropdown Tahun Ajaran di halaman create untuk platform.
2. **Field "Bentuk Pendidikan" bebas dipilih tanpa terikat lembaga** — `lembaga.bentuk_pendidikan` SUDAH 1 nilai tetap per lembaga, tapi form membiarkan pilih apa saja. `CreateKelasAction` (konsumen nyata) SELALU pakai nilai tetap lembaga — assignment yang mismatch JADI MATI TOTAL, tidak pernah terpakai. **Sudah ada buktinya di database**: assignment SDIT PINTERA (bentuk_pendidikan="SD") dengan bentuk_pendidikan assignment="TK".
3. **Restyle tabel index** — masih pakai gaya visual lama, user minta disamakan dengan tabel Mata Pelajaran.

User secara eksplisit minta KETIGA spec ini digabung jadi 1 plan + 1 kickoff (bukan 3 terpisah).

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-kurikulum-assignment-tahun-ajaran-scope-leak.md` — detail Task 1-2.
2. `.agents/specs/2026-09-08-kurikulum-assignment-bentuk-pendidikan-lembaga.md` — detail Task 3-4, TERMASUK keputusan produk yang SUDAH dikonfirmasi user (kunci untuk non-platform, platform tetap bebas).
3. `.agents/specs/2026-09-08-kurikulum-assignment-restyle-tabel.md` — detail Task 5.
4. `.agents/plans/2026-09-08-kurikulum-assignment-susulan-gabungan.md` — 6 task TDD gabungan, kode lengkap tiap step, sudah self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **`TenantScope` itu sendiri TIDAK disentuh** — root-cause yang lebih besar (mempengaruhi 47 model app-wide, ditemukan saat investigasi) SENGAJA di luar scope, dicatat sebagai backlog terpisah yang butuh spec/plan/kickoff sendiri dengan regresi jauh lebih luas. Plan ini HANYA menambal titik-titik query spesifik di `KurikulumAssignmentController` dengan `withoutGlobalScope(TenantScope::class)`.
- **Untuk aktor PLATFORM-scope, field "Bentuk Pendidikan" TETAP dropdown bebas** — di CREATE maupun EDIT, di seluruh plan ini. Ini KEPUTUSAN PRODUK EKSPLISIT dari user (bukan celah yang terlewat) — JANGAN menguncinya juga untuk platform meski secara teori bisa menghasilkan assignment "mati" yang sama.
- **Nilai `bentuk_pendidikan` untuk non-platform WAJIB dihitung ulang SERVER-SIDE** (Task 4), TIDAK BOLEH mempercayai hidden input dari form — ini defense-in-depth, bukan cuma soal UX.
- **`StoreKurikulumAssignmentRequest`/`UpdateKurikulumAssignmentRequest` TIDAK diubah** — hidden input di Task 3 memenuhi validasi `required` yang sudah ada, JANGAN mengubah rule jadi `nullable` atau menambah logic kondisional di FormRequest.
- **Task 5 (restyle) MURNI markup** — mekanisme form Hapus (`POST` + `onsubmit="return confirm(...)"`) TIDAK diubah, JANGAN menambahkan JS/Alpine baru, JANGAN menambahkan pagination/search (di luar permintaan "restyling").
- **Urutan task TIDAK BOLEH dibalik**: Task 1-2 (bug data) → Task 3-4 (gap validasi) → Task 5 (visual) — restyle SENGAJA paling akhir supaya diff-nya murni CSS/markup tanpa tercampur perubahan logic.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (PERHATIKAN path — `Feature/Akademik/`, BUKAN `Feature/Admin/`). 3 helper existing: `actingAsKurikulumAssignmentManager(Lembaga $lembaga)`, `actingAsYayasanKurikulumManager()`, `actingAsPlatformScopeKurikulumManager()` — JANGAN bikin helper baru, JANGAN bikin file test baru.
- **`Lembaga` relation TIDAK PERNAH butuh bypass `TenantScope`** — model `Lembaga` TIDAK memakai `BelongsToTenant`. HANYA relasi/query ke `TahunAjaran` yang butuh bypass eksplisit.
- **`<x-table-actions>` dan `<x-dropdown-link>` (Task 5) SELF-CONTAINED** — TIDAK butuh `x-data` tambahan di parent, dikonfirmasi lewat pembacaan komponennya langsung saat spec ditulis.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 6.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau Task 6 Step 2 (regresi modul Kelas) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, JANGAN lanjut ke Step 3/4/5, laporkan detail kegagalannya ke user SEBELUM melakukan apa pun lagi (sinyal Task 1-2 ternyata berdampak ke `KurikulumAssignmentResolver`/`CreateKelasAction`, bertentangan dengan premis spec).
- **Kalau full suite Task 6 Step 1 menunjukkan kegagalan DI LUAR modul Kurikulum Assignment** — investigasi dulu apakah terkait; kalau tidak terkait (mis. seeder demo yang memang sudah dikenal flaky), catat sebagai pre-existing.

## 6. Catatan Serah Terima

- 3 spec ini SEMUA lahir dari user menemukan masalah lewat screenshot NYATA (bukan audit teoretis) — perlakukan dengan prioritas tinggi, terutama Task 1-2 (data yang tampil salah ke user).
- Keputusan "platform tetap bebas, non-platform dikunci" (Task 3-4) adalah HASIL KONFIRMASI LANGSUNG user menjawab pertanyaan eksplisit — bukan asumsi, jangan dipertanyakan ulang.
- User secara eksplisit minta 3 spec digabung jadi 1 plan+kickoff — kalau ke depan ada spec susulan LAGI untuk modul ini, TANYAKAN dulu apakah mau digabung ke plan ini atau terpisah, jangan asumsikan otomatis.
- Setelah Task 6 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 6 Step 5) — permintaan tambahan kalau user memang menghendaki.

## 7. Mulai dari mana

Mulai dari **Task 1** (bypass `TenantScope` di `index()`/`edit()`) di `.agents/plans/2026-09-08-kurikulum-assignment-susulan-gabungan.md`, kerjakan berurutan Task 1 → 6, JANGAN dilompat/dibalik urutannya (lihat Keputusan Kritis di atas kenapa urutan ini penting).
