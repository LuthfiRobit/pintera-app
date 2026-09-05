# Handoff Log: Peringatan Kelengkapan Nilai pada Alur Pengajuan & Verifikasi Rapor

- **Tanggal**: 2026-09-05
- **Branch**: `akademik-v2`
- **Sifat pekerjaan**: Lanjutan audit investigatif "alur bisnis nilai s.d. rapor" (lihat `.agents/logs/2026-09-05-audit-alur-nilai-rapor-fix-elemen-cp-paud.md`) — bukan lewat spec/plan/kickoff formal, desain disepakati lewat diskusi langsung dengan user (opsi B+C, informative-only, sebelum implementasi).
- **Base Commit**: `39c2b29d` (`fix(akademik): namaWaliKelas/namaKepalaSekolah di rapor PDF -- fakta struktural, bukan hasil workflow`)
- **Head Commit**: lihat commit setelah log ini ditulis.

---

## 1. Konteks & Temuan Awal

Audit sebelumnya menemukan `SubmitPengajuanRaporAction` cuma memvalidasi kelengkapan `CatatanWaliKelas` sebelum mengizinkan pengajuan rapor — **tidak ada validasi kelengkapan `NilaiSiswa`**. Rapor bisa diajukan, diverifikasi, disetujui, dan dicetak dengan sel nilai kosong tanpa ada yang menahan di jalan manapun. Akar masalahnya sengaja: `nilai_angka` di `UpdateNilaiSiswaRequest` selalu `nullable` (supaya guru bisa mencicil input nilai bertahap) — tapi tidak ada lapis lain di hilir yang menutup celah itu.

Widget existing (`DashboardStatsService::statistikProgressRaporKelas()`) cuma informasional (tidak pernah dipanggil sebagai validator) dan **sendiri juga bocor** — cuma menghitung komponen `assessment_type=numeric`, tidak menghitung `predicate` (PAUD, BB/MB/BSH/BSB) atau `narrative`.

## 2. Keputusan Desain (disepakati via diskusi, bukan diputuskan sepihak)

Pendekatan **dua lapis**, keduanya **non-blocking / informative-only** (tidak ada hard-block atau mekanisme override baru):

1. **Lapis 1 (Wali Kelas)** — peringatan lembut saat mau mengajukan rapor. Wali kelas tidak tersandera kelalaian guru mapel lain.
2. **Lapis 2 (Waka Kurikulum)** — rincian kelengkapan per-mapel + daftar siswa yang bolong ditampilkan di halaman verifikasi, sebagai panduan sebelum Waka memutuskan Setujui/Tolak. Wewenang keputusan tetap 100% di tangan Waka (mis. nilai kosong siswa pindahan tetap boleh di-approve tanpa birokrasi tambahan).

Alasan **informative-only** dipilih (bukan hard-block): tanggung jawab isi nilai ada di guru mapel, sementara yang mengajukan/memverifikasi adalah wali kelas/Waka — peran berbeda. Hard-block akan menyandera pihak yang tidak berwenang atas kelalaian orang lain, dan butuh mekanisme override baru yang menambah kompleksitas tak perlu untuk kasus ini.

## 3. Implementasi

1. **`RaporCalculationService::kelengkapanNilaiKelas(Kelas $kelas, Semester $semester): Collection<string, KelengkapanSubjekSel>`** (baru) — method independen dari `hitungRekapKelas()` yang sudah ada. **Sengaja independen, bukan derivasi dari hasil rekap** — dibuktikan lewat test bahwa `hitungRekapKelas()` bisa menghasilkan rata-rata "ada nilai" untuk siswa yang sebenarnya cuma 1 dari beberapa komponen numerik yang terisi (nilai komponen lain kosong tidak menghentikan perhitungan rata-rata). Method baru ini mengecek tiap slot (asesmen × komponen × siswa) secara eksplisit sesuai `assessment_type`-nya (numeric/predicate/narrative), bukan sekadar null-check pada hasil agregasi.
2. **`KelengkapanSubjekSel`** (DTO baru, `app/Domains/Akademik/DataTransferObjects/`) — `subjek`, `totalSiswa`, `siswaBelumLengkap` (Collection<Siswa>).
3. **Lapis 1** — `Guru\RaporController::index()` memanggil method baru, kirim ke view `portals.guru.rapor.catatan.index`: banner amber berisi rincian per-mapel + nama siswa bolong, dan `confirm()` JS di tombol "Ajukan Rapor" (tombol TIDAK dinonaktifkan).
4. **Lapis 2** — `Lembaga\Rapor\PersetujuanController::show()` memanggil method yang sama, kirim ke view `portals.lembaga.rapor.persetujuan.show`: tabel rincian yang sama di atas tombol Setujui/Tolak (tombol TIDAK disentuh sama sekali).

## 4. Test

- 6 test baru (`tests/Unit/Services/RaporCalculationServiceKelengkapanTest.php`): subjek 100% lengkap tidak muncul di hasil; siswa dengan `nilai_angka=null` terdeteksi (baris ada tapi kosong); siswa tanpa baris `NilaiSiswa` sama sekali terdeteksi; tipe predicate & narrative terdeteksi (bukan cuma numeric); **pembuktian eksplisit** bahwa `hitungRekapKelas()` tetap menghasilkan rekap "ada nilai" untuk kasus 1-dari-2-komponen-terisi, sementara `kelengkapanNilaiKelas()` tetap benar mendeteksinya sebagai belum lengkap.
- 4 test controller baru (2 di `RaporControllerTest.php` — banner muncul/tidak muncul; 2 di `RaporPersetujuanControllerTest.php` — tabel muncul + tombol keputusan tetap aktif, pembuktian eksplisit "informative-only" bukan hard-block).
- Hasil: **428 passed (1026 assertions)** untuk `tests/Feature/Guru` + `tests/Feature/Akademik` + `tests/Feature/Rapor` + `tests/Unit/Services`. Pint bersih.

## 5. Di Luar Cakupan

- `DashboardStatsService::statistikProgressRaporKelas()` (widget dashboard lama, cuma hitung numeric) **tidak diperbaiki/diganti** — dibiarkan sebagai widget ringkas terpisah, tidak dipakai lagi sebagai sumber untuk Lapis 1/2 yang baru (yang baru pakai `kelengkapanNilaiKelas()` sendiri). Technical debt kecil: sekarang ada 2 cara menghitung "kelengkapan nilai" di codebase dengan cakupan berbeda (satu cuma numeric buat dashboard ringkas, satu lengkap 3 tipe buat rapor) — belum dikonsolidasi, dicatat sebagai catatan follow-up kalau nanti mau disatukan.

## 6. Status Git

Committed setelah log ini ditulis — lihat commit message untuk daftar file.
