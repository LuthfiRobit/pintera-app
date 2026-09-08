# Kickoff: Audit & Perbaikan Menu Jadwal Pelajaran

**Base commit**: `ec377a88` (`docs(jadwal-pelajaran): implementation plan audit & perbaikan (8 item)`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu Jadwal Pelajaran menemukan 8 celah UI/UX/kejelasan — **BUKAN bug keamanan** (beda dari audit Pola Jam sebelumnya). Backend `JadwalPelajaranController` sudah AMAN: model `JadwalPelajaran` pakai `BelongsToTenant` dengan benar, `store()`/`update()`/`duplicate()` sudah punya defense-in-depth eksplisit (cek cross-lembaga guru/mapel/semester/ruangan). Halaman `create.blade.php`/`edit.blade.php` JUGA bukan halaman mati (tombol modal tetap punya `href` asli valid, beda dari kasus Pola Jam). Semua temuan di sini murni frontend/wording:

- **Item A-B**: dropdown Tahun Ajaran & Semester tidak menampilkan label "(Aktif)" maupun suffix nama lembaga saat mode agregat — user tidak bisa membedakan Tahun Ajaran/Semester yang identik namanya di lembaga berbeda.
- **Item C-E**: tidak ada konfirmasi konteks (kelas/semester mana yang sedang dikerjakan) sebelum user klik tombol "Tambah Slot Jadwal"/"Salin dari Kelas Lain" — user (product owner) sendiri yang mengangkat kekhawatiran ini lewat evaluasi UI/UX (`/frontend-design:frontend-design`), disepakati risiko nyata bukan cuma persepsi.
- **Item F**: typo murni ("Menyeduh..." harusnya "Menyimpan...").
- **Item G**: modal (jalur utama) informasinya lebih sedikit dibanding halaman penuh fallback (`create.blade.php`) — disamakan.
- **Item H**: fitur baru — saat ini "Salin dari Kelas Lain" cuma bisa dalam Tahun Ajaran yang sama, padahal skenario paling umum ("awal tahun ajaran baru, salin jadwal tahun lalu") tidak bisa dilakukan. **Backend SUDAH MENDUKUNG tanpa perubahan apa pun** — murni gap frontend (dropdown Kelas/Semester Sumber di modal cuma diisi dari Tahun Ajaran yang sedang difilter).

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-jadwal-pelajaran-audit-perbaikan.md` — spec lengkap 8 item, SUDAH melalui 2 putaran koreksi (baca bagian "KOREKSI" di Item A dan Item E — penting untuk paham KENAPA strukturnya begini, bukan sekadar APA-nya).
2. `.agents/plans/2026-09-08-jadwal-pelajaran-audit-perbaikan.md` — 9 task TDD, kode lengkap tiap step, sudah self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Backend/query/keamanan TIDAK diubah sama sekali** — `store()`, `update()`, `destroy()`, semua Action/Request class TETAP APA ADANYA. Tidak ada satu task pun di plan ini yang menyentuhnya. Kalau menemukan sesuatu yang "kelihatannya perlu diperbaiki" di backend saat mengerjakan, JANGAN diubah — laporkan ke user dulu, itu di luar scope plan ini.
- **`JadwalPelajaranController.php` SAAT INI belum meng-`use App\Models\Lembaga;`** — Task 1 WAJIB menambahkan import ini. Tanpanya, `Lembaga::withoutGlobalScopes()->find()` di `scopeHeaderData()` akan fatal error.
- **`scopeHeaderData()` (Task 1) WAJIB dipanggil di KEDUA cabang `index()`** (baik `$request->ajax()` maupun halaman penuh) — BUKAN cuma satu. Ini koreksi hasil audit ulang spec: Task 8 menambahkan dropdown baru di dalam partial yang direfresh via AJAX dan butuh data yang sama persis. Kalau cuma dipanggil di satu cabang, dropdown Tahun Ajaran Sumber di Task 8 tidak akan dapat suffix lembaga sama sekali.
- **Konten di dalam `_daftar.blade.php` (dan semua partial yang di-`@include` di dalamnya — `_modal-form.blade.php`, `_modal-duplicate.blade.php`) SELALU ter-refresh ulang setiap filter berubah.** Konten di LUAR itu (area "Card Filter" atas di `index.blade.php`, termasuk tombol aksi) **HANYA dirender SEKALI saat page load pertama, TIDAK PERNAH direfresh lagi**. Ini koreksi hasil audit ulang spec (draf pertama Item E salah taruh badge di tempat yang jadi basi) — JANGAN taruh info yang perlu selalu akurat (nama kelas/semester terpilih) di area "Card Filter", SELALU taruh di dalam `_daftar.blade.php` atau partial di bawahnya.
- **Guard "kelas tujuan tidak boleh jadi kelas sumber"** (self-copy protection) di modal Duplikat WAJIB DIPERTAHANKAN saat Task 8 menulis ulang JS populate Kelas Sumber — lihat kode `if (String(kelas.id) === String(this.duplicateForm.target_kelas_id)) return;` di plan, JANGAN dihilangkan.
- **Task 1 WAJIB paling awal** — Task 8 bergantung langsung pada `scopeHeaderData()` dan `tahunAjaranList` yang ditambahkan Task 1 ke cabang ajax.
- **`_matrix-roster.blade.php`, `JadwalPelajaranSiswaController`, `JadwalAnakController` TIDAK disentuh** — di luar cakupan, sudah dicek benar saat audit.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Admin/JadwalPelajaranCrudTest.php`. Helper existing: `actingAsJadwalManager(Lembaga $lembaga): User` (lembaga-scope, role `operator_akademik`) — JANGAN bikin helper baru. TIDAK ADA helper yayasan-scope di file ini — ikuti pola bikin role+user inline yang sudah dicontohkan lengkap di plan tiap task yang butuhnya.
- **Task 8 Step 1 punya test kedua yang SENGAJA membuktikan ULANG bahwa backend tidak perlu diubah** (bukan test fitur baru — test ini HARUS SUDAH PASS sebelum implementasi apa pun dimulai). Kalau ternyata GAGAL saat dijalankan pertama kali, itu sinyal SERIUS bahwa premis spec salah — STOP, JANGAN coba "perbaiki" `DuplicateJadwalAction`/`duplicate()` supaya lulus, laporkan ke user dulu.
- **Task 3 test mungkin sebagian sudah PASS sebelum implementasi** (nama kelas/semester kemungkinan sudah terlihat di tempat lain di halaman seperti dropdown filter) — ini BUKAN berarti task selesai tanpa kerja, pastikan assertion benar-benar menguji markup modal yang BARU (perkuat assertion kalau perlu, sudah dicatat di plan).
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 9.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau Task 8 Step 1 test kedua (bukti backend sudah mendukung) GAGAL** — STOP TOTAL, JANGAN ubah backend apa pun, laporkan detail ke user sebelum melanjutkan.
- **Kalau Task 9 Step 1 (regresi penuh) menunjukkan kegagalan di test yang TIDAK disentuh task manapun** (mis. `JadwalPelajaranBentrokWaktuTest`, `JadwalPelajaranTenantGuardTest`) — investigasi dulu apakah terkait perubahan Task 1-8; kalau tidak terkait (pre-existing flaky), catat sebagai temuan terpisah, JANGAN diperbaiki diam-diam tanpa lapor.

## 6. Catatan Serah Terima

- Spec ini SUDAH melalui 2 putaran audit-ulang eksplisit atas permintaan user ("review ulang spec") — 3 kekeliruan nyata ditemukan & diperbaiki sebelum plan ditulis (badge lokasi-basi Item E, `scopeHeaderData()` cakupan cabang, guard self-copy hilang). Plan yang ada SEKARANG sudah mengandung versi yang benar — TIDAK perlu dicurigai ulang dari nol, tapi tetap ikuti instruksi Stop-and-Report kalau menemukan sesuatu yang tidak cocok dengan kode aktual.
- Item C-D-E lahir dari user secara eksplisit meminta evaluasi lewat skill `/frontend-design:frontend-design` terhadap kekhawatiran nyata (bukan asumsi) — user (product owner) sendiri yang mengangkat isu "user bisa saja tidak tahu kelas mana yang sedang dikerjakan". Perlakukan Item C-D-E dengan bobot yang SAMA seriusnya dengan Item A-B (bukan sekadar polish kosmetik).
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 9 Step 4) — beda dari kickoff Pola Jam sebelumnya yang eksplisit memintanya. Kalau user memang menghendaki di sesi ini, itu permintaan tambahan terpisah.
- Setelah Task 9 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (`scopeHeaderData()` di kedua cabang + badge Tahun Ajaran) di `.agents/plans/2026-09-08-jadwal-pelajaran-audit-perbaikan.md`, kerjakan berurutan Task 1 → 9. Task 2-7 boleh ditukar urutan kalau perlu (tidak saling bergantung), TAPI Task 1 harus paling awal (fondasi Task 8) dan Task 8 harus setelah Task 1 selesai.
