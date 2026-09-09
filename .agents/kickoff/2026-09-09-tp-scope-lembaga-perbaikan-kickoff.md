# Kickoff: Perbaikan Scope Lembaga Menu TP (Komponen Penilaian)

**Base commit**: `1412f342` (`docs(komponen-penilaian): implementation plan perbaikan scope lembaga TP (6 task)`)
**Branch**: `rbac-v2` (di sesi ini disebut juga `akademik-v2` — SAMA branch, TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit ulang MENYELURUH menu TP (Tujuan Pembelajaran, dalam modul Komponen Penilaian) sisi Admin — dipicu pertanyaan eksplisit user "tambah dan edit belum terkunci switch lembaga?" setelah user melihat live app pasca perbaikan Task 1-4 sebelumnya (spec `2026-09-09-tp-komponen-penilaian-perbaikan.md`, SUDAH SELESAI & di-merge, jangan disentuh lagi). User lalu minta "audit semua hal di halaman tp, audit ulang" — audit kali ini menemukan 7 item baru, SEMUA soal kebocoran/ambiguitas SCOPE LEMBAGA untuk aktor yayasan mode "Semua Lembaga" (belum switch ke 1 lembaga via pengalih topbar), BUKAN soal validasi/kunci Subjek-Semester seperti spec sebelumnya.

**Jawaban ke pertanyaan user**: benar ada gap, TAPI HANYA di **Tambah (Create)** — Edit TIDAK punya risiko yang sama (Subjek/Semester di Edit sudah terkunci permanen sejak spec sebelumnya, jadi tidak ada jalur untuk salah pilih lembaga lewat Edit). Root cause tunggal: beberapa query di controller (`MataPelajaran::orderBy('nama')->get()`, default `TahunAjaran::where('status_aktif', true)->value('id')`) tidak pernah mempertimbangkan kondisi "aktor yayasan belum switch ke 1 lembaga" — `TenantScope` di kondisi itu sengaja fallback ke "semua lembaga di bawah yayasan yang sama", bukan 1 lembaga tunggal, jadi data tercampur.

**Keputusan desain kunci (Item A, WAJIB dipahami)**: bukan "label semua dropdown yang tercampur" (lebih rumit, tetap berisiko), tapi **GUARD "Tambah TP" supaya wajib aktor yayasan sudah switch ke 1 lembaga dulu** — meniru pola `AttendanceQrScanController` (Scan QR Kehadiran SDM) yang SUDAH ada & diterima di codebase ini untuk kelas masalah yang PERSIS SAMA. Begitu guard lolos, SEMUA query di dalam `create()`/`store()` otomatis benar tanpa filter manual tambahan (`TenantScope` sudah otomatis 1-lembaga).

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-09-tp-scope-lembaga-perbaikan.md` — spec lengkap 7 item (A-G). BACA BAIK-BAIK Ringkasan dan Item A ("Keputusan Desain") — fondasi kenapa Create di-guard total, bukan cuma dilabeli.
2. `.agents/plans/2026-09-09-tp-scope-lembaga-perbaikan.md` — 6 task TDD, kode lengkap tiap step, sudah self-review.

**Spec LAMA (jangan dikerjakan ulang, hanya untuk konteks kalau perlu bandingkan)**: `.agents/specs/2026-09-09-tp-komponen-penilaian-perbaikan.md` + `.agents/plans/2026-09-09-tp-komponen-penilaian-perbaikan.md` — SUDAH SELESAI, soal penguncian Subjek/Semester permanen (Item A-D versi lama), TIDAK ADA hubungannya dengan scope lembaga di spec/plan BARU ini meski sama-sama menu TP. Kalau menemukan penomoran "Item A/B/C/D" yang isinya BEDA dari yang dijelaskan di sini, itu tandanya salah baca spec/plan LAMA — pastikan baca dokumen dengan nama file `...-scope-lembaga-perbaikan.md`, BUKAN `...-komponen-penilaian-perbaikan.md`.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Guard Item A (Task 1) HARUS pakai `resolveActiveLembagaId()`** (trait `ResolveLembagaScopeTrait`, SUDAH `use` di `KomponenPenilaianController` sejak kickoff sebelumnya) — BUKAN raw `session('active_lembaga_id')`. Ini varian TERVALIDASI (mengembalikan `null` kalau lembaga session bukan milik yayasan aktor), konsisten dengan `scopeHeaderData()` yang sudah ada di controller yang sama.
- **SETELAH guard Item A lolos, JANGAN tambah `where('lembaga_id', ...)` manual di query mana pun** di `create()`/`store()` — `TenantScope` (dari `BelongsToTenant`, dipakai `MataPelajaran`/`TahunAjaran`/`Semester`) OTOMATIS benar begitu `active_lembaga_id` tervalidasi terisi. Kalau menemukan diri menambah filter manual di sana, STOP — itu tanda kesalahpahaman terhadap desain, bukan perbaikan tambahan yang perlu.
- **Edit TIDAK di-guard sama seperti Create** — TP yang diedit sudah pasti terikat 1 lembaga (route-model-binding + `TenantScope`), dan Subjek/Semester (sumber `lembaga_id`) sudah tidak bisa diubah lagi lewat Edit sejak spec sebelumnya. Edit HANYA dapat 2 perbaikan kecil: bersihkan query mati (Item E) + tambah badge lembaga (Item F), digabung di Task 5 sesuai keputusan eksplisit di spec ("digabung supaya tidak 2x mengubah signature method yang sama").
- **Item G (filter Mata Pelajaran tetap flat) SENGAJA TIDAK dapat task** — didokumentasikan sebagai keputusan sadar di spec (bukan celah yang tidak disadari). JANGAN menambah task untuk item ini, JANGAN "sekalian" memperbaikinya saat mengerjakan task lain yang menyentuh file yang sama.
- **Task 1 dan Task 3 SAMA-SAMA soal "aktor yayasan mode agregat", TAPI fix-nya SENGAJA beda bentuk**: Task 1 (`create()`/`store()`) di-GUARD TOTAL (blokir akses), Task 3 (`index()`) TETAP boleh diakses mode agregat, cuma defaultnya diperbaiki supaya tidak diam-diam menyempit ke 1 lembaga. JANGAN "menyamakan" perlakuan keduanya — itu justru pelanggaran keputusan desain yang sudah dijelaskan lengkap alasannya di spec Item A.
- **Jalur Guru (`Guru\KomponenPenilaianController`) TIDAK disentuh SAMA SEKALI** — guru selalu lembaga-scope tunggal, tidak pernah yayasan-scope, jadi tidak pernah mengalami kondisi "mode agregat" yang jadi akar seluruh plan ini. Task 6 Step 1 menjalankan regresi Guru justru untuk MEMBUKTIKAN tidak ada efek samping, bukan untuk mengubahnya.
- **Tidak pakai worktree, tidak pindah branch, TIDAK ADA migrasi database.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Admin/KomponenPenilaianCrudTest.php`. Helper existing: `actingAsKomponenManager(Lembaga $lembaga)` (lembaga-scope) dan `actingAsYayasanKomponenManager(Yayasan $yayasan)` (yayasan-scope) — JANGAN bikin helper baru.
- **Simulasi "aktor yayasan sudah switch ke 1 lembaga" di test**: `session(['active_lembaga_id' => $lembaga->id]);` SETELAH `actingAsYayasanKomponenManager()`, SEBELUM request — pola persis dari test existing di file yang sama.
- **`KomponenPenilaianController::edit()` berubah signature** jadi `edit(Request $request, KomponenPenilaian $komponenPenilaian)` di Task 5 — dicek TIDAK ADA caller manual method ini selain route-model-binding, AMAN diubah.
- **File yang berulang kali disentuh lintas-task**: `KomponenPenilaianController.php` disentuh di SEMUA 5 task implementasi (Task 1-5) dan `_daftar.blade.php` disentuh di Task 1 & Task 4 — kerjakan BERURUTAN (1→5), JANGAN paralel, supaya tidak conflict.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 6 kalau ada kekhawatiran proses lain masih jalan.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode (cari via nama method/nama test, BUKAN nomor baris semata), catat perbedaannya di laporan task.
- **Kalau regresi (Task 6 Step 1, atau regresi di step manapun dalam task) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan. Topik ini menyentuh scope multi-tenant (riwayat proyek: bug cross-tenant/IDOR sudah terjadi berulang kali di modul Akademik) — jangan lanjut kalau ada tanda-tanda regresi tak terduga, sekecil apa pun.
- **Kalau saat mengerjakan Task 1 atau Task 3 menemukan query/method LAIN (di luar yang sudah diidentifikasi di spec 7 item: A-G) yang JUGA mengalami masalah "tercampur lintas-lembaga di mode agregat"** — STOP, laporkan ke user dulu sebelum menambah scope sendiri. Spec ini sudah 3x direview tapi kemungkinan ada yang terlewat tetap harus dilaporkan, bukan diputuskan sendiri, mengingat topik scope multi-tenant historically rawan di proyek ini.
- **Kalau Task 5 (`edit()` cleanup) ternyata menemukan `mataPelajaranList`/`elemenCpList`/`semesterList`/`bentukPendidikan` DIPAKAI di suatu tempat yang belum ketahuan (mis. lewat `@include` partial lain yang tidak digrep spec)** — STOP, JANGAN hapus data yang ternyata masih dipakai, laporkan ke user.

## 6. Catatan Serah Terima

- Spec ini ditulis dan DIREVIEW 3 KALI dalam sesi yang sama (2 kali menemukan & memperbaiki kesalahan nyata: salah silang-referensi antar-item, dan instruksi diff yang kurang presisi) — versi final sudah akurat terhadap kode aktual per saat spec ditulis, TAPI TETAP ikuti instruksi Stop-and-Report kalau menemukan sesuatu yang tidak cocok.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 6 Step 4 eksplisit bilang begitu) — kalau user memang menghendaki di sesi ini, itu permintaan tambahan terpisah.
- Task 6 Step 3 (verifikasi browser manual) SENGAJA ditulis sebagai OPSIONAL, bukan blocking — sesi-sesi sebelumnya di proyek ini terbukti tidak selalu punya akses browser interaktif. Kalau tidak bisa dijalankan, JANGAN mengklaim "sudah diverifikasi" — laporkan dengan jujur bahwa langkah itu diserahkan ke user.
- Setelah Task 6 selesai, JANGAN merge branch ke branch manapun — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (guard Tambah TP — paling kritis) di `.agents/plans/2026-09-09-tp-scope-lembaga-perbaikan.md`, kerjakan BERURUTAN Task 1 → 6 (semua task menyentuh file yang sama secara berulang, urutan berurutan WAJIB untuk menghindari conflict, bukan sekadar disarankan).
