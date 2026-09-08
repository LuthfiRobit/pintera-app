# Kickoff: Penyempurnaan Halaman Index — Menu Kurikulum Assignment

**Base commit**: `e563560f` (`docs(kurikulum-assignment): implementation plan penyempurnaan halaman index`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Lanjutan dari plan susulan gabungan (3 spec) sebelumnya — SUDAH selesai & terverifikasi (TenantScope leak, kunci Bentuk Pendidikan, restyle tabel). Setelah restyle itu direview bersih, user memberi 2 masukan tambahan lewat percakapan langsung (bukan screenshot kali ini):

1. **Header index tidak responsif** — dua tombol besar (`Cek & Perbaiki Kurikulum/Fase`, `Tambah Assignment`) sejajar tanpa `flex-wrap`, mengganggu secara visual, dan tidak konsisten dengan pola tombol di menu lain (yang sudah pakai `<x-link-button>`).
2. **Tidak ada dialog konfirmasi Hapus yang layak** — tombol Hapus masih pakai `onsubmit="return confirm(...)"` (popup browser native), berbeda dari standar aplikasi (`confirmDialog()` Alpine + `<x-confirm-dialog>`) yang dipakai 40+ tempat lain.

Saat menggali "siapa yang bisa hapus" untuk menjawab poin 2, saya menemukan 3 hal TAMBAHAN yang lalu ditulis jadi 1 spec gabungan (`E.1`-`E.5`):

- **E.1**: `index()` SELAMA INI hanya "mode agregat" (tampilkan SEMUA lembaga di yayasan + assignment global) — TIDAK PERNAH menyempit walau aktor sudah switch ke 1 lembaga aktif lewat pengalih lembaga. Setelah investigasi ulang (bukan asumsi lama "ini sengaja"), ini TERBUKTI bug: manual scoping code di controller cuma meniru SEPARUH dari yang `TenantScope` sendiri lakukan untuk model lain (cabang agregat ada, cabang "menyempit saat switch" LUPA diimplementasikan). Dikonfirmasi ke user via pertanyaan eksplisit "apa akan berubah alur bisnisnya?" — jawaban: TIDAK, karena create/store/edit/update/destroy sudah scope independen dari index.
- **E.2**: index perlu badge scope (pola SAMA seperti Kelas/Tahun Ajaran/Mata Pelajaran) menunjukkan mode aktif ("Semua Lembaga" vs nama lembaga tertentu) — supaya honest terhadap perilaku baru E.1.
- **E.4**: ditemukan sambil membaca file — `create()` sudah lama mengirim `->withErrors([...])` untuk guard "belum pilih lembaga aktif", tapi `index.blade.php` TIDAK PERNAH merender `$errors->any()` — pesan validasi selama ini HILANG SENYAP dari user.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-kurikulum-assignment-penyempurnaan-index.md` — spec lengkap 5 item (E.1-E.5), termasuk tabel test guidance dan bagian "Di Luar Scope".
2. `.agents/plans/2026-09-08-kurikulum-assignment-penyempurnaan-index.md` — 5 task TDD, kode lengkap tiap step, sudah self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Cabang `elseif ($scope !== 'platform')` (lembaga-scope) dan cabang `platform` di `index()` TIDAK BOLEH ikut diubah** — HANYA cabang `yayasan` yang mendapat percabangan baru menyempit/agregat (Task 1).
- **Catatan statis lama** ("Daftar ini selalu menampilkan SEMUA lembaga...") **WAJIB DIHAPUS**, bukan cuma diedit — kalau dibiarkan, jadi MENYESATKAN karena index BENAR-BENAR menyempit sekarang.
- **Pengaman "masih dipakai Kelas" TIDAK diperluas ke assignment global** di plan ini — keputusan eksplisit user, sudah dicatat di spec bagian "Di Luar Scope". HANYA teks peringatan tambahan di dialog konfirmasi (Task 4) yang menutupi gap ini secara UX, bukan penambahan validasi backend baru.
- **`resync.blade.php` TIDAK disentuh**, TIDAK ada filter dropdown baru — di luar scope spec ini.
- **`confirmDialog()` adalah helper GLOBAL yang SUDAH ADA** (`window.confirmDialog`, `<x-confirm-dialog />` sudah ter-render lewat `app.blade.php`) — JANGAN menambahkan registrasi/import apa pun di Task 4, cukup panggil langsung.
- **`<x-link-button>` dipakai APA ADANYA** (komponen sudah ada, 2 varian: `primary` default, `ghost` untuk aksi sekunder) — JANGAN membuat varian baru di Task 2.
- **Urutan task TIDAK BOLEH dibalik**: Task 1 (query index) → Task 2 (badge+header, BUTUH `activeLembaga` dari Task 1) → Task 3 (`$errors`) → Task 4 (confirmDialog) → Task 5 (penutup). Task 3 dan 4 sebenarnya independen dari Task 1/2, tapi tetap diletakkan setelahnya sesuai urutan di plan.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (PERHATIKAN path — `Feature/Akademik/`, BUKAN `Feature/Admin/`). 3 helper existing: `actingAsKurikulumAssignmentManager(Lembaga $lembaga)`, `actingAsYayasanKurikulumManager()`, `actingAsPlatformScopeKurikulumManager()` — JANGAN bikin helper baru, JANGAN bikin file test baru.
- **2 test existing PENTING untuk regresi Task 1**: "yayasan cuma lihat assignment global + milik yayasannya sendiri di index" dan "platform TETAP lihat SEMUA assignment lintas yayasan di index" — KEDUANYA TIDAK set `session('active_lembaga_id')`, jadi tetap masuk cabang agregat yang SUDAH ADA, seharusnya TIDAK terpengaruh perubahan Task 1. Kalau salah satu tiba-tiba gagal setelah Task 1, itu SINYAL REGRESI NYATA, bukan salah baca — STOP dan laporkan.
- **`resolveActiveLembagaId()`** (dari `ResolveLembagaScopeTrait`, SUDAH dipakai luas di controller ini) dipakai untuk Task 1 — method ini TIDAK PERNAH `abort()`, otomatis menangani validasi ulang "lembaga aktif di session masih milik yayasan aktor" (kasus stale session).
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 5.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau Task 1 Step 5 (regresi test file penuh) menunjukkan salah satu dari 2 test existing yang disebut di atas GAGAL** — STOP TOTAL, JANGAN lanjut ke Task 2, laporkan detail kegagalannya ke user SEBELUM melakukan apa pun lagi.
- **Kalau Task 5 Step 2 (regresi modul Kelas: `KelasCrudTest|CreateKelasActionTest`) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, laporkan detail ke user sebelum melanjutkan (sinyal perubahan index berdampak ke `CreateKelasAction`/`KurikulumAssignmentResolver`, bertentangan dengan premis spec bahwa index() terisolasi dari konsumen nyata data ini).
- **Kalau full suite Task 5 Step 1 menunjukkan kegagalan DI LUAR modul Kurikulum Assignment** — investigasi dulu apakah terkait; kalau tidak terkait (mis. seeder demo yang memang sudah dikenal flaky), catat sebagai pre-existing.

## 6. Catatan Serah Terima

- Spec ini lahir dari KOMBINASI masukan langsung user (header tidak responsif, tidak ada dialog konfirmasi layak) DAN temuan investigasi saya sendiri saat menjawab pertanyaan user (index tidak menyempit, `$errors` hilang) — SEMUA level prioritas SAMA (bukan cuma polish kosmetik), karena E.1 dan E.4 adalah bug data/UX nyata, bukan sekadar permintaan gaya.
- Keputusan "index() sekarang menyempit saat switch lembaga TIDAK mengubah alur bisnis apa pun" adalah HASIL KONFIRMASI LANGSUNG user menjawab pertanyaan eksplisit "apa akan berubah alur bisnisnya?" — bukan asumsi, jangan dipertanyakan ulang.
- Root-cause besar `TenantScope` (47 model, platform-scope actor = 0 baris) TETAP di luar scope plan ini juga — SAMA SEPERTI plan sebelumnya, backlog terpisah.
- Setelah Task 5 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 5 Step 5) — permintaan tambahan kalau user memang menghendaki.

## 7. Mulai dari mana

Mulai dari **Task 1** (`index()` menyempit + kirim `activeLembaga`) di `.agents/plans/2026-09-08-kurikulum-assignment-penyempurnaan-index.md`, kerjakan berurutan Task 1 → 5, JANGAN dilompat/dibalik urutannya (lihat Keputusan Kritis di atas kenapa urutan ini penting).
