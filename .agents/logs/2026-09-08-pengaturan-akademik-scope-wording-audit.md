# Handoff Log: Kejujuran Wording & UX Akses Tanpa Lembaga Aktif — Menu Pengaturan Akademik

> **Tanggal**: 8 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-08-pengaturan-akademik-scope-wording-audit.md`  
> **Plan**: `.agents/plans/2026-09-08-pengaturan-akademik-scope-wording-audit.md`  
> **Kickoff**: `.agents/kickoff/2026-09-08-pengaturan-akademik-scope-wording-audit-kickoff.md`  
> **Base commit sebelum task**: `5cd741ac`  
> **Commit range**: `5cd741ac..ebd02208` (7 commit)  
> **Status**: Selesai & Terverifikasi (Pest: 47 test domain pengaturan/kalender akademik lulus, 99 assertions; Pint passed; Verifikasi browser confirmed)

---

## 1. Apa yang Dikerjakan

Menuntaskan audit frontend dan UX flow pada menu **Pengaturan Akademik** (`admin.pengaturan.akademik.index`) yang sebelumnya sudah memiliki backend logic solid (`ResolveLembagaScopeTrait`, `abort(404)` cross-lembaga, pemisahan izin nasional vs lembaga), untuk menyelesaikan 2 temuan utama dan 1 restyling UX:

1. **Halaman Tidak Menampilkan Nama Lembaga**: Controller sudah mengirimkan `$lembaga`, tetapi namanya tidak pernah dirender di header.
2. **Redirect Paksa ke `/dashboard` Saat Lembaga Belum Dipilih**: Aktor yayasan yang belum memilih lembaga aktif sebelumnya di-redirect paksa keluar halaman ke `/dashboard`. Ini diubah menjadi pola in-page notice via flag `$lembagaBelumDipilih` (mengikuti preseden `pembayaran/index.blade.php` dan `tagihan/index.blade.php`).
3. **Restyling Modern Empty-State**: Kartu empty-state *"Pilih Lembaga Aktif Dulu"* diperbarui dengan desain modern (layered icon, container card dengan soft gradient, dan pill highlight fitur).

### Rincian Commit:

1. **Commit `3875e740` & `ee1d912c` & `71412418` — Dokumentasi Spec, Plan, & Kickoff**
   - Menulis spec `.agents/specs/2026-09-08-pengaturan-akademik-scope-wording-audit.md`.
   - Menulis rencana implementasi `.agents/plans/2026-09-08-pengaturan-akademik-scope-wording-audit.md`.
   - Menyiapkan dokumen kickoff `.agents/kickoff/2026-09-08-pengaturan-akademik-scope-wording-audit-kickoff.md`.

2. **Commit `eb3f9781` — Task 1: Controller Berhenti Redirect, Selalu Render View**
   - `app/Http/Controllers/Admin/PengaturanAkademikController.php`:
     - Menghapus import yang tidak terpakai: `use Illuminate\Http\RedirectResponse;`.
     - Mengubah return type signature `index(Request $request): View`.
     - Saat `$lembagaId === null`, controller tidak lagi redirect ke `route('dashboard')`, melainkan langsung merender view `portals.lembaga.akademik.pengaturan.akademik` dengan flag `'lembagaBelumDipilih' => true, 'lembaga' => null, 'entriList' => collect(), 'bolehNasional' => false, 'bolehKelolaHariAktif' => false`.
     - Saat lembaga aktif ada, mengirim `'lembagaBelumDipilih' => false` beserta data `$lembaga` dan entri kalender.
   - `tests/Feature/Admin/PengaturanAkademikControllerTest.php`:
     - Mengubah 2 test existing (baris 194 & 210) dari `assertRedirect(route('dashboard'))` menjadi `assertOk()`, `assertViewHas('lembagaBelumDipilih', true)`, dan `assertSee('Pilih Lembaga Aktif Dulu')`.

3. **Commit `9ab5d1a2` — Task 2: Badge Nama Lembaga + Kartu Empty-State**
   - `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`:
     - Menambahkan badge nama lembaga di samping judul: `@if (! ($lembagaBelumDipilih ?? false)) <span class="... border-brand-200 bg-brand-50 text-brand-700"><x-icon name="apartment" /> {{ $lembaga->nama }}</span> @endif`.
     - Membungkus blok tab 272 baris dengan pengkondisian `@if ($lembagaBelumDipilih ?? false) ... @else ... @endif` tanpa mengubah tag internal blok tab.
     - Memverifikasi keseimbangan tag pembuka dan penutup `<div>` (29 open vs 29 close).
   - `tests/Feature/Admin/PengaturanAkademikControllerTest.php`:
     - Menambahkan unit test baru: `it('shows the lembaga name badge in the header when an active lembaga is set')`.

4. **Commit `6dab2d81` — Task 3: Update Checklist Plan Selesai**
   - `.agents/plans/2026-09-08-pengaturan-akademik-scope-wording-audit.md`:
     - Menandai seluruh checkbox Task 1, Task 2, dan Task 3 sebagai selesai (`[x]`).

5. **Commit `ebd02208` — Restyle Empty-State Modern**
   - `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`:
     - Meningkatkan estetika empty-state: kartu berlatar `bg-gradient-to-b from-brand-50/30 via-white to-white`, layered icon gedung dengan `ring-8 ring-brand-50/60`, tipografi judul tegas `Pilih Lembaga Aktif Dulu`, 3 pill badge indikator fitur (`Hari Aktif Sekolah`, `Batas Waktu Edit Presensi`, `Kalender Akademik`), serta pill petunjuk navigasi dropdown bilah atas.
     - Memverifikasi ulang keseimbangan tag `<div>` (34 open vs 34 close).

---

## 2. Keputusan Penting yang Diambil

1. **`index()` Tidak Pernah Redirect Lagi (Konsistensi UX)**:
   - Pengalihan paksa ke `/dashboard` dihilangkan. User tidak lagi merasa "diusir" ketika membuka menu ini tanpa lembaga aktif. Sebagai gantinya, halaman tetap terbuka dengan kartu penjelasan in-page yang ramah dan instruktif.
2. **Skenario Session Stale dan Tanpa Lembaga Aktif Diperlakukan Sama**:
   - `resolveActiveLembagaId()` mengembalikan `null` untuk aktor yang belum switch lembaga maupun aktor yang membawa session `active_lembaga_id` milik lembaga yayasan lain (stale). Controller sengaja tidak membedakan dua skenario ini karena solusinya sama: aktor harus memilih lembaga aktif yang valid melalui topbar switcher.
3. **Badge Nama Lembaga Menggunakan 1 Warna (Brand)**:
   - Berbeda dari Tahun Ajaran, Kelas, atau Mata Pelajaran yang memiliki mode agregat multi-lembaga ("Semua Lembaga" dengan badge ungu), menu Pengaturan Akademik secara konsep **hanya dapat dikonfigurasi per lembaga tunggal**. Oleh karena itu, badge header hanya ada 1 varian (warna brand) saat lembaga aktif ada, dan tidak pernah memunculkan badge ungu.
4. **Endpoint AJAX Tetap Dijaga Utuh**:
   - Method `updateHariAktif()` dan `updateBatasEditAbsen()` sengaja tidak disentuh karena sudah benar mengembalikan response JSON `422 Unprocessable Entity` saat `resolveActiveLembagaId()` bernilai `null`.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `rbac-v2` (ahead dari `origin/rbac-v2`).
   - Perubahan belum di-merge ke `main` dan belum di-push (sesuai aturan dan batasan proyek).
2. **Verifikasi Visual Browser**:
   - Pengujian visual browser mandiri telah dilakukan dan didokumentasikan via screenshot:
     - State dengan Lembaga Aktif: Badge `SDIT PINTERA` muncul rapi di samping judul `Pengaturan Akademik`, seluruh tab aktif.
     - State Tanpa Lembaga Aktif: Kartu empty state modern muncul di tengah layar tanpa redirect URL.
