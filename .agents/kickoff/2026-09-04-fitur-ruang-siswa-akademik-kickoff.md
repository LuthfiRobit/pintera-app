# Kickoff: Fitur Ruang Siswa Akademik

**Base commit**: `bce8a6d0` (branch `akademik-v2`)
**Spec**: `.agents/specs/2026-09-04-fitur-ruang-siswa-akademik.md`
**Plan**: `.agents/plans/2026-09-04-fitur-ruang-siswa-akademik.md`

## Konteks

Fitur BARU, kelanjutan langsung dari paket Ruang Orang Tua (`.agents/kickoff/2026-09-04-fitur-ruang-orang-tua-akademik-kickoff.md`) yang baru selesai bersih (2.813 test passed). Membangun 3 halaman self-service Ruang Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) menggantikan placeholder `/dalam-pengembangan`.

**PENTING — beda scope dari paket Orang Tua**: user EKSPLISIT akan mengerjakan UI/UX sendiri lewat agent lain setelah backend ini selesai. Fokus paket ini murni BACKEND yang benar (controller, query, otorisasi, test) — UI/view SENGAJA dibuat minimal/fungsional, JANGAN habiskan waktu untuk polish visual seperti paket Orang Tua kemarin.

## Dokumen Wajib Dibaca

1. `.agents/specs/2026-09-04-fitur-ruang-siswa-akademik.md` — spec lengkap §1-§4.
2. `.agents/plans/2026-09-04-fitur-ruang-siswa-akademik.md` — plan 4 task, kode lengkap tiap step.
3. **Referensi pola yang SUDAH terbukti benar**: `.agents/plans/2026-09-04-fitur-ruang-orang-tua-akademik.md` dan `.agents/logs/2026-09-04-fitur-ruang-orang-tua-akademik.md` — paket Orang Tua sudah di-review bersih, banyak pola (factory `Asesmen`/`KomponenPenilaian`, `HARI_ORDER` const, `RaporPdfDataBuilder` usage) disalin langsung dari situ. Kalau ragu soal suatu pola, cek dulu bagaimana paket itu menyelesaikannya.

## Keputusan Kritis (JANGAN diubah tanpa eskalasi ke user)

1. **TIDAK ADA trait resolve-anak** — beda dari paket Orang Tua. Setiap controller pakai `$request->user()->siswa` langsung. Relasi `User::siswa(): HasOneThrough` (`app/Models/User.php:100-110`) SUDAH membungkus `withoutGlobalScope(TenantScope::class)` di level definisi — otomatis aman, JANGAN tambah bypass lagi di titik pakai.
2. **`NilaiRaporSiswaController::unduhRapor()` TIDAK menerima parameter route apa pun** — `$siswa` SELALU `$request->user()->siswa`. TIDAK ADA celah IDOR yang perlu ditutup (beda dari Orang Tua yang perlu `abort_unless(anakList->contains(...))`) — JANGAN tambahkan pengecekan itu di sini, tidak relevan.
3. **Pola `withoutGlobalScope(TenantScope::class)` per-model WAJIB ikuti PERSIS kode di plan**: `JadwalPelajaran` — PAKAI; `NilaiSiswa`/`PengajuanRapor` — TIDAK PAKAI. Untuk `Presensi`/`sesiPembelajaran` di `PresensiSayaController` — status BELUM PASTI, WAJIB dibuktikan lewat hasil test yang benar-benar dijalankan (Task 3 Step 2 & 6 di plan menjelaskan detail: kalau test gagal karena riwayat kosong padahal seharusnya ada data, itu bukti bypass diperlukan, TAMBAHKAN — JANGAN mengubah assertion test untuk memaksa lolos).
4. **Route BARU sama sekali** (`admin.nilai-rapor-saya.index`, `admin.jadwal-pelajaran-saya.index`, `admin.presensi-saya.index`) — JANGAN menimpa/memakai ulang route existing admin/guru untuk KELOLA jadwal (`admin.jadwal-pelajaran.index` dkk, konteks BEDA).
5. **UI WAJIB minimal** — `<x-app-layout>` + HTML/Tailwind paling dasar (`<table>`, `<select onchange>`). JANGAN pakai `<x-panel>`/`<x-badge>`/token warna khusus seperti paket Orang Tua — akan ditimpa user sendiri, effort ekstra di situ sia-sia.
6. **Sidebar**: kondisi guard TETAP `Auth::user()->hasRole('siswa')` PERSIS seperti baris asli sebelum dikomentari — JANGAN diubah ke pola `Auth::user()->siswa !== null` meski tampak lebih konsisten dengan Orang Tua.
7. Tidak pindah branch, tetap di `akademik-v2`.

## Fakta Operasional

- `Siswa::factory()->create(['user_id' => $user->id, ...])` — pola SAMA seperti `OrangTuaFactory` (verifikasi: `database/factories/SiswaFactory.php`) — `user_id` dibaca sebagai override untuk link `Person`, BUKAN kolom asli di tabel `siswa`. WAJIB buat `User` DULU, baru `Siswa::factory()->create(['user_id' => $user->id])`.
- `Asesmen`/`KomponenPenilaian` entitas TERPISAH DAN SEJAJAR (bukan nested) — `NilaiSiswa` punya `asesmen_id` DAN `komponen_penilaian_id` sebagai 2 FK independen. `JenisAsesmen` valid case: `SumatifLingkupMateri`/`SumatifAkhirSemester`/`SumatifAkhirJenjang`/dst — BUKAN `Sumatif`. (Lihat bukti nyata kesalahan serupa yang pernah terjadi & diperbaiki di plan Orang Tua Task 2 sebelum eksekusi.)
- Task 1, 2, 3 SEMUA menyunting file `routes/admin/siswa-akademik.php` yang sama — kalau lewat subagent, WAJIB serial, JANGAN paralel.
- Jalankan `ps aux | grep artisan | grep -v grep` sebelum test suite apa pun.
- Format commit: `feat(akademik): ...`, akhiri dengan `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

## Kalau Menemukan Ambiguitas

STOP dan laporkan ke user, jangan menebak-nebak, terutama untuk:
- Kalau ternyata `withoutGlobalScope` DIPERLUKAN untuk `Presensi`/`sesiPembelajaran` (lihat Keputusan Kritis #3) — ini BOLEH langsung diperbaiki sesuai instruksi plan (sudah diantisipasi eksplisit, bukan ambiguitas yang perlu eskalasi), TAPI kalau ternyata ada PERILAKU LAIN yang tidak terduga (mis. error lain, bukan sekadar "kosong"), laporkan dulu.
- Kalau ada file test sidebar existing (Task 4) yang strukturnya beda signifikan dari yang diasumsikan plan — baca dulu, sesuaikan, tapi laporkan kalau terasa perlu perubahan besar di luar yang diinstruksikan.
- Kalau `RaporPdfDataBuilder`/`AkunSiswaGenerator` ternyata sudah berubah signature dari yang dipakai di paket Orang Tua sebelumnya — laporkan, jangan modifikasi service itu sendiri.

## Catatan Serah-Terima (di luar scope paket ini, JANGAN dikerjakan)

- Polish UI/UX — SENGAJA di luar scope, user kerjakan sendiri lewat agent lain setelah backend ini selesai.
- Perubahan `DashboardController` — TIDAK disentuh, widget dashboard existing tetap seperti sebelumnya.

## Mulai dari Mana

Gunakan `superpowers:subagent-driven-development`. Urutan: Task 1 → 2 → 3 (boleh urutan bebas di antara ketiganya TAPI serial, berbagi file route) → Task 4 (sidebar + full suite final). Setiap task: implementer subagent → task reviewer subagent → fix loop kalau ada temuan Critical/Important → lanjut task berikutnya. Setelah Task 4 (full suite hijau), lakukan final whole-branch review sebelum melapor selesai ke user.
