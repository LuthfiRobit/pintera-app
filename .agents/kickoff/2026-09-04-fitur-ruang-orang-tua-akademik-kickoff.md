# Kickoff: Fitur Ruang Orang Tua Akademik

**Base commit**: `5841eb3d` (branch `akademik-v2`)
**Spec**: `.agents/specs/2026-09-04-fitur-ruang-orang-tua-akademik.md`
**Plan**: `.agents/plans/2026-09-04-fitur-ruang-orang-tua-akademik.md`

## Konteks

Fitur BARU (bukan bug fix) — membangun 3 halaman self-service Ruang Orang Tua (Nilai & Rapor Anak, Jadwal Anak, Riwayat Izin/Sakit Anak) yang saat ini sengaja disembunyikan di sidebar sejak 2026-09-03, menggantikan placeholder generik `/dalam-pengembangan`. Fondasi query untuk ketiganya sudah terbukti jalan di `DashboardController` (widget ringkas) — proyek ini "melepas batasan" (limit 5/hari-ini) jadi halaman detail penuh. Sisi Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) SENGAJA DITUNDA, proyek terpisah menyusul.

## Dokumen Wajib Dibaca

1. `.agents/specs/2026-09-04-fitur-ruang-orang-tua-akademik.md` — spec lengkap §1-§4.
2. `.agents/plans/2026-09-04-fitur-ruang-orang-tua-akademik.md` — plan 5 task, kode lengkap tiap step, TERMASUK 2 "Catatan penting" krusial soal factory `OrangTua` dan `Asesmen`/`KomponenPenilaian` (lihat Keputusan Kritis #3 di bawah — WAJIB dibaca sebelum menulis test apa pun).

## Keputusan Kritis (JANGAN diubah tanpa eskalasi ke user)

1. **`resolveAnakTerpilih()` di trait `ResolveAnakOrangTuaTrait` HARUS diam-diam fallback** ke anak pertama kalau `siswa_id` di request tidak valid/bukan milik actor — JANGAN diubah jadi `abort()`/error. Ini pola "derive, don't validate" yang sudah dipakai berkali-kali di paket-paket sebelumnya (`ResolveLembagaScopeTrait`).
2. **`NilaiAnakController::unduhRapor()` adalah PENGECUALIAN dari poin 1** — WAJIB `abort_unless(..., 403)` TEGAS kalau `$siswa` bukan anak actor. JANGAN samakan dengan fallback diam-diam poin 1 — beda filosofi karena ini endpoint unduh file (spec §2.2 menjelaskan kenapa).
3. **2 jebakan factory yang SUDAH ditemukan dan diperbaiki di plan — JANGAN tulis ulang versi lama yang salah**:
   - `OrangTua` TIDAK punya kolom `user_id` asli (link sebenarnya lewat `person_id` → `Person.user_id`). Pola BENAR: `OrangTua::factory()->create(['user_id' => $user->id])` (user dibuat DULU, id-nya dioper SAAT create — factory punya logic khusus baca ini). Pola SALAH yang pernah ditulis lalu diperbaiki: `OrangTua::factory()->create()` lalu `->update(['user_id' => ...])` — ini diam-diam no-op, bikin SEMUA test gagal karena `$user->orangTua` selalu null.
   - `Asesmen` dan `KomponenPenilaian` adalah entitas TERPISAH DAN SEJAJAR (masing-masing punya `subjek_type`/`subjek_id` sendiri) — TIDAK ADA kolom `komponen_penilaian_id` di tabel `asesmen`. `NilaiSiswa` punya `asesmen_id` DAN `komponen_penilaian_id` sebagai 2 foreign key independen, keduanya WAJIB dibuat terpisah lewat factory masing-masing.
   - `JenisAsesmen` cuma 6 case valid (`DiagnostikKognitif`, `DiagnostikNonKognitif`, `Formatif`, `SumatifLingkupMateri`, `SumatifAkhirSemester`, `SumatifAkhirJenjang`) — TIDAK ADA case `Sumatif`.
4. **JANGAN tambahkan `$this->authorize()`/permission check apa pun** di method `index()` ketiga controller — akses cukup lewat middleware `auth` (dari `routes/admin.php`) + `resolveAnakList()` yang otomatis kosong untuk non-orang-tua. Ini keputusan sadar (spec §2.2), BUKAN kelalaian — jangan "diperbaiki" dengan menambah Policy check sendiri.
5. **Style UI WAJIB konsisten dengan `resources/views/admin/dashboard/orang-tua.blade.php`** (persona yang sama) — token `text-ink`/`text-slate`/`bg-paper`/`font-display`, komponen `<x-panel>`/`<x-badge tone="...">`, BUKAN token abu-abu generik (`text-gray-*`) yang dipakai halaman admin operasional lain.
6. **2 sistem ikon BERBEDA, jangan dicampur**: ikon SIDEBAR pakai Lucide via `<x-dynamic-component :component="'lucide-'.$icon">` (nama kebab-case seperti `award`, `calendar-clock`) — TETAP PERTAHANKAN nama yang sudah ada di spec saat membuka komentar sidebar. Ikon DI DALAM KONTEN halaman pakai `<x-icon name="...">` (Material-Symbols-SVG, HANYA nama yang terdaftar di `resources/views/components/icon.blade.php` valid — daftar lengkap ada di plan Task 2/3/4, mis. `assessment`, `event`, `history`, `receipt`, `print`).
7. Tidak pindah branch, tetap di `akademik-v2`.

## Fakta Operasional

- Task 2, 3, 4 SEMUA bergantung pada Task 1 (trait) — kerjakan Task 1 dulu sampai selesai.
- Task 2/3/4 sama-sama menyunting file `routes/admin/orang-tua-akademik.php` — kalau lewat subagent, WAJIB serial (satu per satu), JANGAN paralel, supaya tidak saling menimpa.
- Jalankan `ps aux | grep artisan | grep -v grep` sebelum test suite apa pun.
- Beberapa step plan minta baca struktur aktual dulu sebelum finalisasi (field factory `KomponenPenilaian`/`PengajuanRapor`, pola hidden-input existing untuk form filter, file test sidebar existing) — WAJIB dibaca dulu, JANGAN menebak (2 bug nyata sudah ditemukan dari kesalahan menebak struktur factory saat menulis plan ini — lihat Keputusan Kritis #3).
- Format commit: `feat(akademik): ...`, akhiri dengan `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

## Kalau Menemukan Ambiguitas

STOP dan laporkan ke user, jangan menebak-nebak, terutama untuk:
- Kalau ternyata ADA field/relasi lain yang meleset dari asumsi plan (di luar 2 yang sudah dikoreksi) — laporkan sebelum memaksakan test lolos dengan cara yang tidak sesuai desain.
- Kalau `PermissionCatalog`/route existing ternyata punya konvensi berbeda dari yang diasumsikan plan (mis. `routes/admin.php` sudah berubah strukturnya) — cek dulu, jangan asumsi tetap sama.
- Kalau `RaporPdfDataBuilder::build()`/`templateUntukJenjang()` ternyata beda signature dari yang dipakai `Guru\RaporController::cetak()` — laporkan, jangan modifikasi service itu untuk "memudahkan" tanpa konfirmasi (service ini dipakai 2 controller lain, perubahan bisa berdampak luas).

## Catatan Serah-Terima (di luar scope paket ini, JANGAN dikerjakan)

- Sisi Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) — proyek terpisah menyusul, TIDAK disentuh di paket ini.
- Form pengajuan izin/sakit baru dari orang tua — TIDAK dibangun, `RiwayatIzinSakitAnakController` murni read-only (keputusan sadar dari brainstorming).
- Perubahan model `Presensi` (menambah `BelongsToTenant`) — TIDAK diperlukan, sudah dianalisis di spec §1 kenapa tidak jadi gap.
- Bottom-nav (`resources/views/layouts/bottom-nav.blade.php`) — cek keberadaan pola serupa untuk orang tua saat implementasi Task 5, tambahkan HANYA kalau presedennya sudah ada (lihat spec §3 Non-Goals).

## Mulai dari Mana

Gunakan `superpowers:subagent-driven-development`. Urutan WAJIB: Task 1 (trait) → Task 2/3/4 (boleh urutan bebas di antara ketiganya TAPI serial, tidak paralel, karena berbagi 1 file route) → Task 5 (sidebar + full suite final). Setiap task: implementer subagent → task reviewer subagent → fix loop kalau ada temuan Critical/Important → lanjut task berikutnya. Setelah Task 5 (full suite hijau), lakukan final whole-branch review sebelum melapor selesai ke user.
