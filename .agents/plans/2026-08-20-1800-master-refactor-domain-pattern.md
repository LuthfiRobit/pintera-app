# 📋 Master Roadmap: Refactor Controller Legacy ke Pola `Domains\<Modul>`

- **Document ID / Slug:** `2026-08-20-1800-master-refactor-domain-pattern`
- **Tanggal & Waktu:** 20 Agustus 2026, 18:00 WIB
- **Branch:** `rbac-v2`
- **Status Master:** 🟡 BELUM DIMULAI — dokumen ini adalah rencana, sub-task pertama belum di-brainstorming

---

## 📌 PANDUAN NAVIGASI UNTUK AGENT BARU / SESI BERIKUTNYA

> **PENTING BAGI AGENT YANG MELANJUTKAN SESI INI:**
> Dokumen ini adalah **Master Blueprint** untuk refactor SELURUH controller legacy aplikasi ke pola `Domains\<Modul>` (Action/DTO/Model, standar `.agents/skills/laravel-feature-standard/SKILL.md`), kelanjutan dari Sub-Task 05 modul Akademik (`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`) yang sudah 🟢 SELESAI TOTAL.
>
> Untuk mengeksekusi atau melihat checklist sub-task AKTIF, buka berkas Sub-Task pada §6 di bawah. Kalau §6 masih kosong, berarti belum ada sub-task yang resmi jadi spec — mulai dari §4 (urutan prioritas) untuk menentukan grup mana yang dikerjakan berikutnya, lalu jalankan `superpowers:brainstorming` untuk grup itu.
>
> **Baca §3 (Prinsip Arsitektur Mengikat) SEBELUM membuat spec baru** — itu bukan opsional, itu keputusan yang sudah diambil dan tidak perlu didiskusikan ulang dari nol tiap sub-task.

---

## 1. Latar Belakang

Modul Akademik (Sub-Task 01-05, lihat `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`) sudah 100% bermigrasi ke `app/Domains/Akademik/`. Audit sidebar-by-sidebar (2026-08-20, setelah Sub-Task 05 selesai) menemukan bahwa migrasi ini BARU menutup sebagian dari total controller aplikasi:

| Domain | Controller sudah "grouped" (pakai `App\Domains\*`) |
|---|---|
| `Akademik` | 15 |
| `Sarpras` | 7 |
| `Pengadaan` | 5 |
| `Workflow` | 0 langsung (cuma dikonsumsi lewat Pengadaan) |
| **Total sudah migrasi** | **28** |
| **Belum ada domain sama sekali** | **~24** (5 grup, lihat §2) |

Dokumen ini melanjutkan program yang sama untuk 5 grup sidebar yang tersisa.

## 2. Snapshot Kondisi Saat Ini (audit 2026-08-20, hasil `grep` + `route:list` nyata, bukan tebakan)

| Grup Sidebar | Jumlah Controller | Total Baris Kode | Domain Ada? |
|---|---:|---:|---|
| Pendampingan (Kasus) | 10 | 1042 | ❌ Tidak ada |
| Akses & Peran | 2 | 381 | ❌ Tidak ada |
| SPMB | 4 | 737 | ❌ Tidak ada |
| Keuangan | 8 | 1386 | ❌ Tidak ada |
| Data Induk | 12 | 1775 | ❌ Tidak ada |

Detail controller per grup ada di riwayat percakapan sesi 2026-08-20 (bisa direkonstruksi ulang lewat `php artisan route:list` + `grep -c "App\\Domains\\" <file>` per controller kalau perlu diverifikasi ulang — jangan asumsikan angka di atas masih akurat kalau sudah lama berlalu sejak tanggal audit).

## 3. Prinsip Arsitektur Mengikat

**Bagian ini adalah keputusan yang SUDAH DIAMBIL, bukan bahan diskusi ulang setiap sub-task.** Kalau ada sub-task yang butuh menyimpang dari salah satu prinsip ini, itu HARUS dibahas eksplisit dengan user dulu (sama seperti keputusan `salinJadwal` di Sub-Task 05) — jangan diam-diam menyimpang, tapi juga jangan mengulang perdebatan dari nol.

### 3.1 Kapan model dipindah ke `Domains\<Modul>\Models\` vs tetap di `app/Models\`

- **Audit dulu, jangan asumsi.** Sebelum memutuskan, jalankan `grep -rln "^use App\\\\Models\\\\<Model>;" --include="*.php" app database tests` untuk dapat jumlah & daftar file pemakai NYATA.
- Model dengan blast-radius KECIL (dipakai eksklusif oleh 1 fitur, biasanya < 10 file di luar test/factory) → **layak dipindah**.
- Model dengan blast-radius BESAR (dipakai lintas banyak modul lain — buktinya: `Lembaga` 382 file, `Kelas`/`Siswa`/`TahunAjaran`/`Semester` juga lintas modul) → **TETAP di `app/Models\`**, diimpor dari domain manapun yang butuh.
- Model anak/child yang 1 agregat dengan model yang sudah diputuskan pindah (contoh: `JamPelajaran` ikut `PolaJam`) → **ikut pindah bersamaan**, jangan setengah-setengah.
- Relasi lintas-namespace pakai **FQCN inline** di method relationship (`belongsTo(\App\Domains\Akademik\Models\PolaJam::class)`), BUKAN `use` statement tambahan di model yang tetap di `app/Models\` — supaya konsisten dengan pola yang sudah ada (`Kelas::ruangan()`).
- **Jangan lupa referensi implisit same-namespace.** Kalau dua model SAMA-SAMA di `app/Models\` sebelum salah satunya dipindah, referensi lintas-model di antara mereka BISA TIDAK PUNYA `use` statement eksplisit (resolusi otomatis same-namespace PHP) — grep berbasis baris `use` TIDAK akan menangkap ini. Sub-Task 05 menemukan kasus nyata: `JadwalPelajaran.php` mereferensikan `JamPelajaran::class` tanpa `use` statement. Setelah audit grep awal, WAJIB verifikasi ulang dengan menjalankan test scoped luas dan tracing error "Class not found" kalau ada, jangan cuma percaya hasil grep `use`-line.
- **`newFactory()` WAJIB ditambahkan** ke model yang dipindah keluar dari `App\Models\` kalau modelnya pakai `HasFactory` — resolusi factory default Laravel patah begitu model pindah namespace (lihat implementasi nyata di `KalenderAkademik`/`PolaJam`/`JamPelajaran` Sub-Task 05 sebagai referensi kode).

### 3.2 Kapan sebuah grup LAYAK jadi domain baru

- Grup dengan business logic nyata (state machine, validasi lintas-entitas, workflow approval, kalkulasi) → **layak** dijadikan domain (`Actions/`, `DTO/`, kalau perlu `Models/`).
- Grup yang mayoritas CRUD polos tanpa logic kompleks, ATAU sudah didelegasikan ke package pihak ketiga (contoh: Akses & Peran → Spatie Permission) → **prioritas rendah**, migrasi ke domain nilainya kecil dibanding effort.
- Model FONDASI yang dipakai lintas seluruh aplikasi (Lembaga, Kelas, Siswa, User, dst) TIDAK PERNAH "dijadikan domain" — mereka netral, diimpor dari domain manapun yang butuh. Data Induk sebagai grup sidebar SECARA UMUM masuk kategori ini — prioritas migrasinya rendah justru karena isinya fondasi, bukan business logic domain-spesifik.

### 3.3 Konvensi Controller & View

- Controller BARU (untuk grup yang baru mulai migrasi, atau fitur baru sama sekali) mengikuti pola `app/Http/Controllers/[Scope]/[Domain]/[Feature]Controller.php` — SUDAH ada 6 contoh nyata di kode (`Guru/Akademik/`, `Lembaga/{Pengadaan,Rapor,Sarpras}/`, `Yayasan/{Pengadaan,Sarpras}/`) dan didokumentasikan resmi di `laravel-feature-standard/SKILL.md` §A.
- Controller LAMA yang sudah punya Action tapi masih rata di `[Scope]/[Feature]Controller.php` (tanpa folder Domain) — **TIDAK WAJIB** dipindah namespace secara retroaktif. Itu proyek kosmetik terpisah, nol risiko fungsional, nol urgensi. Contoh nyata: `Admin\KenaikanKelasController`, `Admin\PolaJamController` (Sub-Task 05) tetap rata di `Admin\`, tidak dipindah ke `Admin\Akademik\`.
- **View ikut pindah ke `resources/views/portals/[scope]/[domain]/[feature]/`** SETIAP KALI controllernya dimigrasi ke Action/DTO — beda dengan controller (yang boleh ditunda), view TIDAK ditunda, dipindah bersamaan dalam task yang sama. Konvensi ini sudah didokumentasikan di `laravel-feature-standard/SKILL.md` §14 dan sudah dipakai konsisten di 12 folder domain (Sarpras, Pengadaan, sebagian Akademik). Segmen `[scope]` mengikuti SIAPA yang akses secara fungsional (`lembaga`/`guru`/`yayasan`), **BUKAN** namespace PHP controller-nya — contoh nyata: `Admin\RppController` (namespace `Admin\`) tetap punya view di `portals/lembaga/akademik/rpp/` (scope fungsional: staf lembaga), bukan `portals/admin/`.
- **Bahaya nyata saat memindahkan view:** dot-notation dipakai untuk DUA hal berbeda di Blade — nama VIEW (`view('admin.foo.index')`, `@include('admin.foo._bar')`) dan nama ROUTE (`route('admin.foo.index')`). Keduanya SERING punya string awalan yang identik (mis. `admin.pola-jam.`) padahal cuma nama view yang berubah saat dipindah — **nama route TIDAK PERNAH berubah** kecuali memang direncanakan rename terpisah. Cari-ganti otomatis (`sed`, dsb) HARUS dibatasi hanya pada baris `view(`/`@include(`/`assertViewIs(`/`->name()`, JANGAN blanket-replace satu file penuh — kalau terlanjur, verifikasi dengan `grep -rn "route('portals\." resources/views/portals` (harus kosong) SEBELUM lanjut. Ditemukan nyata saat memindahkan 9 view Akademik (2026-08-20): sed yang tidak dibatasi merusak `route()` di 5 file blade, menyebabkan `RouteNotFoundException` di test.
- Test yang menguji nama view (`assertViewIs()`, `expect($view->name())->toBe(...)`) WAJIB diaudit dan diupdate juga — jangan asumsikan cuma controller & blade yang berubah.

### 3.4 Zero-Behavior-Change adalah Default

- Migrasi controller → Action/DTO defaultnya **TIDAK mengubah perilaku sama sekali** — pesan error, urutan validasi, guard, format respons harus identik kata-per-kata dengan sebelum migrasi.
- Kalau selama investigasi ditemukan CELAH atau INKONSISTENSI nyata di kode lama (seperti `salinJadwal` yang tidak validasi bentrok, Sub-Task 05) — itu TIDAK diperbaiki diam-diam. Harus dibahas eksplisit dengan user sebagai keputusan desain terpisah (pros/cons kedua opsi), baru masuk spec sebagai deviasi yang didokumentasikan jelas kenapa berbeda dari prinsip zero-behavior-change.

### 3.5 Kebijakan Testing & Eksekusi

- Tiap task dalam plan: jalankan test SCOPED (file yang relevan ke task itu saja), bukan full suite.
- Full suite HANYA dijalankan sekali di task TERAKHIR tiap sub-task, dan HARUS minta izin eksplisit ke user dulu — jangan asumsikan izin.
- Kalau plan dieksekusi agent lain (bukan sesi yang menulis plan): siapkan kickoff prompt standalone (`.agents/kickoff-<slug>.md`, lihat contoh Sub-Task 05) SETELAH plan tersimpan, tanpa perlu ditanya lagi ke user (sudah jadi preferensi baku).
- Sesi yang menulis plan (kalau beda dari yang eksekusi) bertanggung jawab melakukan **review kode detail** setelah eksekusi selesai — verifikasi independen (grep zero-leak, lint, diff terhadap plan, jalankan ulang test/full-suite sendiri), bukan cuma percaya laporan handoff log.
- Alur baku: `superpowers:brainstorming` (tanya klarifikasi, ajukan opsi kalau ada keputusan desain terbuka) → tulis spec ke `.agents/specs/` → self-review → **user review spec** → `superpowers:writing-plans` (kode lengkap per task, bukan deskripsi) → commit plan → kickoff prompt (kalau perlu) → eksekusi → **review kode detail** → handoff log → update tabel §6 dokumen ini.

## 4. Urutan Prioritas Modul Refactor (saran, bukan harga mati)

| Urutan | Grup | Alasan |
|---|---|---|
| 1 | **Pendampingan (Kasus)** | Koreksi 2026-08-20: cakupan sebenarnya 10 controller, 1042 baris (bukan 4/483 seperti perkiraan awal — 6 controller sub-workflow consent/sesi/tugas/submission/evaluasi tidak muncul di sidebar tapi bagian dari `routes/kasus.php`). Tetap prioritas 1 karena paling mandiri (blast radius rendah — tidak jadi fondasi modul lain) dan business logic-nya (state machine kasus, consent, triase konselor) genuinely layak jadi domain, bukan CRUD polos — bukan lagi karena "paling kecil". |
| 2 | **SPMB** | Ukuran sedang (4 controller, 737 baris). Workflow verifikasi & keputusan berjenjang, mirip pola approval yang sudah terbukti di domain Pengadaan — referensi desain sudah ada. |
| 3 | **Keuangan** | Paling bernilai kalau selesai (billing, VA, manual payment, cicilan — uang sungguhan), tapi juga paling berisiko (8 controller, 1386 baris). Lebih aman dikerjakan setelah ada 1-2 domain baru lain sebagai pemanasan pola. |
| 4 | **Data Induk** | Paling besar (12 controller, 1775 baris) tapi nilai arsitektural migrasi RENDAH — isinya mayoritas fondasi/master data yang dipakai modul lain (mirip kenapa `Lembaga`/`Kelas`/`Siswa` sengaja tidak dipindah). Prioritas rendah meski ukurannya besar. |
| 5 | **Akses & Peran** | Paling kecil kedua (2 controller, 381 baris), tapi nilai migrasi paling rendah — sudah didelegasikan ke Spatie Permission, sedikit logic custom yang layak diekstrak. |

Urutan ini BOLEH berubah kapan saja kalau prioritas bisnis berubah — lihat §5 untuk mekanisme perubahan prioritas lewat permintaan fitur baru.

## 5. Kebijakan Sisipan Fitur Baru

Proyek refactor ini TIDAK menghentikan kebutuhan fitur baru. Kalau di tengah proses refactor muncul kebutuhan fitur baru, ikuti aturan berikut:

1. **Fitur baru yang genuinely baru** (bukan menambah endpoint ke controller LEGACY yang SEDANG AKTIF di-refactor dalam sub-task berjalan) → langsung didesain pakai pola baru dari awal (`Domains\<Modul>\Actions\`, `DataTransferObjects\`, `Models\` kalau perlu domain baru). TIDAK perlu menunggu sub-task refactor grup terkait selesai dulu. Ikuti alur baku brainstorming → spec → plan seperti biasa.
2. **Fitur baru dapat baris tabel navigasi SENDIRI** di §6 dokumen ini (atau di master roadmap terpisah kalau fitur itu bukan bagian dari 5 grup ini) — TIDAK mengganggu urutan atau progress sub-task refactor yang sedang berjalan. Refactor dan fitur baru adalah dua alur kerja paralel, bukan satu antrian.
3. **Kalau fitur baru itu numpang di controller yang SEDANG di-refactor** (konflik file langsung, task-in-progress) → tunggu sub-task refactor itu commit sampai selesai dulu, baru mulai kerjakan fitur baru di atasnya. Ini supaya tidak ada dua pekerjaan menyentuh file yang sama secara bersamaan.
4. **Kalau permintaan fitur baru menunjukkan satu grup jadi lebih mendesak** dari urutan di §4 (misal butuh fitur baru di Keuangan padahal urutannya nomor 3) → itu SINYAL untuk mempercepat prioritas grup itu, BUKAN alasan menunda fitur barunya. Keputusan re-prioritisasi dibahas dengan user saat kejadian nyata terjadi — §4 di atas tidak perlu diupdate preventif untuk skenario hipotetis.

## 6. Tabel Sub-Task

| No | Sub-Task | File Spec | File Plan | File Handoff Log | Status |
|:---:|---|---|---|---|:---:|
| *(kosong — belum ada sub-task yang resmi jadi spec)* | | | | | |

> Isi tabel ini setiap kali satu sub-task selesai brainstorming (spec ditulis) — jangan tunggu sampai implementasi selesai untuk mendaftarkannya, supaya sesi lain yang membuka dokumen ini tahu ada pekerjaan yang sedang berjalan.

## 7. Catatan Lintas Sub-Task

- **Gap `TenantScope` level-yayasan** (ditemukan Sub-Task 04a modul Akademik, BELUM diperbaiki, di luar scope dokumen ini): lihat catatan lengkap di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` §akhir. Masih relevan untuk seluruh aplikasi, bukan cuma Akademik — kalau ada sub-task refactor yang menyentuh query tenant-scoped, waspadai pola ini.
- *(tambahkan catatan baru di sini setiap kali sub-task menemukan sesuatu yang perlu dibawa ke sub-task lain atau sesi berikutnya — sama seperti pola di master roadmap Akademik.)*
