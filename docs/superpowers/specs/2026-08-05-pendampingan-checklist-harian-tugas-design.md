# Program Pendampingan — Checklist Per-Hari untuk Tugas Frekuensi "Harian" — Design

**Tanggal:** 2026-08-05
**Status:** Approved

## Latar Belakang

Ditemukan saat mencoba fitur Program Pendampingan langsung sebagai user (2026-08-05). Sub-proyek
4 (Sesi & Tugas Pendampingan, sudah shipped dan lengkap di `demo`) mendesain kolom `frekuensi`
pada `kasus_tugas` (enum: `sekali`, `harian`, `mingguan`, `bulanan`) untuk "menentukan tampilan
submission (checklist per-hari untuk harian, vs satu zona submit untuk yang lain)" —
lihat `docs/superpowers/specs/2026-08-04-pendampingan-04-sesi-tugas-design.md` baris 40-43.
Checklist per-hari untuk `harian` ini **tidak pernah dibangun**: implementasi saat ini
(`app/Http/Controllers/KasusTugasSubmissionController.php`,
`resources/views/kasus/partials/_tab-tugas.blade.php`) merender satu form submit generik yang
identik untuk keempat nilai `frekuensi` — nilainya cuma dipakai sebagai label/badge tampilan.

Dokumen ini menutup gap tersebut, hanya untuk `frekuensi = 'harian'`. `sekali`/`mingguan`/
`bulanan` TIDAK disentuh sama sekali.

## Tujuan

Untuk tugas `harian`, siswa (atau orang tua) melihat daftar tanggal (dari `mulai_pada` sampai
`batas_selesai_pada`, inklusif) dan mengirim bukti pengerjaan **per tanggal**, bukan satu bukti
gabungan untuk seluruh rentang. Konselor me-review dan meminta revisi **per tanggal**, sehingga
revisi di satu hari tidak memengaruhi hari lain maupun status keseluruhan tugas.

## Perubahan Skema

`kasus_tugas_submission` mendapat 1 kolom baru:
- `tanggal` (date, nullable) — tanggal spesifik yang diisi submission ini. **Hanya diisi untuk
  submission dari tugas `frekuensi = 'harian'`**; untuk submission dari tugas `sekali`/
  `mingguan`/`bulanan`, kolom ini tetap `null` selamanya (perilaku form generik yang sudah ada
  tidak berubah sama sekali).

Tidak ada perubahan pada `kasus_tugas` (kolom `frekuensi`, `mulai_pada`, `batas_selesai_pada`
tetap seperti sekarang — satu rentang tunggal, sesuai keputusan Sub-proyek 4 yang tidak diubah
di sini).

## Aturan Tanggal & Penguncian (khusus tugas harian)

- **Semua tanggal dalam rentang `mulai_pada`–`batas_selesai_pada` boleh diisi kapan saja** —
  tidak dikunci ke "hari ini", tidak ada susulan yang ditolak, tidak ada pengisian di muka yang
  ditolak. Siswa boleh mengisi tanggal yang sudah lewat maupun yang belum tiba.
- **Satu tanggal, satu submission aktif.** Begitu ada `KasusTugasSubmission` untuk tanggal
  tertentu dengan `status_review` = `menunggu_review` atau `diterima`, tanggal itu **terkunci**
  — form submit untuk tanggal itu tidak lagi ditampilkan ke siswa/orang tua.
- **Revisi membuka kembali HANYA tanggal itu.** Kalau konselor menandai `status_review =
  'revisi_diminta'` untuk submission tanggal tertentu, form submit untuk tanggal itu saja
  muncul kembali (siswa kirim satu submission baru untuk tanggal yang sama). Tanggal-tanggal
  lain dalam tugas yang sama sepenuhnya tidak terpengaruh.
- **Tidak ada resubmit bebas.** Selama status tanggal itu masih `menunggu_review` atau
  `diterima`, siswa tidak bisa kirim ulang untuk tanggal yang sama — harus menunggu konselor
  me-review, atau menunggu diminta revisi.

## Tampilan — Siswa / Orang Tua (submitter) untuk tugas harian

Ganti form submit generik (yang masih dipakai apa adanya untuk `sekali`/`mingguan`/`bulanan`)
dengan daftar vertikal, satu baris per tanggal dalam rentang, **diurutkan menaik** dari
`mulai_pada` ke `batas_selesai_pada`:
- Tanggal **belum ada submission**, atau submission-nya **`revisi_diminta`** → baris ini
  menampilkan form kirim bukti (field `teks` + `lampiran` opsional, identik dengan field form
  yang sudah ada sekarang — tidak ada field baru selain penanda tanggal tersembunyi).
- Tanggal **sudah terkunci** (`menunggu_review` atau `diterima`) → baris ini menampilkan
  riwayat submission untuk tanggal itu saja (teks, lampiran jika ada, badge status review),
  tanpa form.

## Tampilan — Konselor untuk tugas harian

Daftar per-tanggal yang sama, tapi setiap tanggal yang punya submission menampilkan aksi review
(Terima Hasil / Minta Revisi) — pola tombol dan form yang identik dengan yang sudah ada sekarang
di `_tab-tugas.blade.php`, hanya di-scope ulang per tanggal alih-alih per aliran submission
gabungan.

## Status Tugas Keseluruhan (badge di header `KasusTugas`) — khusus harian

- `Ditugaskan → Dikerjakan`: **tetap otomatis** begitu ada submission pertama di tanggal
  manapun (perilaku `KasusTugasSubmission::booted()` yang sudah ada, tidak diubah).
- `→ Revisi` otomatis: **DIHAPUS untuk tugas harian.** Konselor meminta revisi untuk satu
  tanggal TIDAK LAGI mengubah `KasusTugas.status` menjadi `Revisi` secara keseluruhan — status
  revisi hanya terlihat di badge `status_review` masing-masing baris tanggal. (Untuk tugas
  `sekali`/`mingguan`/`bulanan`, perilaku lama — seluruh tugas berubah status jadi `Revisi` saat
  submission-nya diminta revisi — **tetap seperti sekarang**, tidak disentuh.)
- `Selesai`/`Terlewat`: tetap sepenuhnya keputusan manual konselor lewat tombol "Tandai Selesai"
  yang sudah ada — tidak pernah dihitung otomatis dari kelengkapan hari, konsisten dengan
  catatan eksplisit di spec Sub-proyek 4 ("konselor tetap punya keleluasaan profesional menilai
  kecukupan bukti").

## Testing

- Konselor membuat tugas `frekuensi = harian` dengan rentang 3 hari → siswa membuka halaman
  kasus, melihat 3 baris tanggal, semuanya dengan form submit kosong (belum ada submission).
- Siswa mengirim bukti untuk tanggal tengah (hari ke-2) → baris tanggal itu berubah jadi
  terkunci menampilkan riwayat submission, baris tanggal ke-1 dan ke-3 tetap terbuka dengan
  form submit.
- Siswa TIDAK bisa mengirim submission kedua untuk tanggal ke-2 selagi masih `menunggu_review`
  (form tidak tampil / route menolak percobaan langsung).
- Konselor menandai submission tanggal ke-2 sebagai `revisi_diminta` → baris tanggal ke-2
  kembali menampilkan form submit; `KasusTugas.status` **tetap** `Dikerjakan` (bukan berubah
  jadi `Revisi`); baris tanggal ke-1 dan ke-3 tidak terpengaruh sama sekali.
- Siswa mengirim submission baru untuk tanggal ke-2 (revisi) → baris itu terkunci lagi dengan
  submission barunya.
- Untuk tugas `frekuensi = mingguan`/`bulanan`/`sekali`: form submit generik lama tetap tampil
  apa adanya, `tanggal` pada submission yang dibuat tetap `null`, dan meminta revisi pada
  submission-nya tetap mengubah `KasusTugas.status` menjadi `Revisi` secara keseluruhan seperti
  perilaku yang sudah ada sebelum perubahan ini — regresi terhadap perilaku lama untuk 3
  frekuensi ini harus nol.
- Orang tua (kontak utama) mengirim bukti untuk salah satu tanggal tugas harian anaknya → sama
  seperti siswa, submission tersimpan dengan `orang_tua_id` terisi dan `tanggal` sesuai baris
  yang diisi.
- Download lampiran per-tanggal tetap memakai route/otorisasi yang sama seperti sekarang
  (`kasus.tugas.submission.lampiran`) — tidak ada perubahan pada endpoint ini.

## Penyederhanaan yang Disepakati (v1)

- **Tidak ada indikator visual "tanggal terlewat tanpa diisi"** (mis. warna merah untuk hari
  yang sudah lewat dan masih kosong) — v1 hanya membedakan "form terbuka" vs "form terkunci
  (ada submission)"; menandai kekosongan sebagai state ketiga yang berbeda secara visual bisa
  ditambahkan nanti kalau dibutuhkan, bukan bagian dari fix ini.
- **Tidak ada validasi/pemblokiran kalau rentang `mulai_pada`–`batas_selesai_pada` sangat
  panjang** (misal tugas harian sebulan penuh, 30 baris tanggal) — konselor yang menentukan
  rentang saat membuat tugas, dan itu sudah berlaku sejak Sub-proyek 4; fix ini tidak menambah
  batasan baru pada rentang tersebut.
- **`mulai_pada`/`batas_selesai_pada` tetap satu rentang tunggal untuk semua nilai frekuensi**
  (termasuk harian) — TIDAK diubah jadi sekumpulan sesi/instance terpisah. Ini murni mengubah
  cara SUBMISSION di-track dalam rentang yang sudah ada, bukan mengubah struktur `kasus_tugas`
  itu sendiri.
- **Perilaku `sekali`/`mingguan`/`bulanan` sama sekali tidak disentuh** — baik di form submit,
  status-tugas-otomatis, maupun struktur data (`tanggal` mereka tetap `null`).

## Di Luar Cakupan

- Perubahan pada `frekuensi = 'sekali'`, `'mingguan'`, atau `'bulanan'` — semuanya tetap seperti
  sekarang.
- Reminder/notifikasi otomatis untuk tanggal yang belum diisi.
- Laporan ringkasan kepatuhan harian (mis. "siswa X mengisi 5 dari 7 hari") — bisa dibangun di
  atas kolom `tanggal` yang baru ini kalau dibutuhkan nanti, tidak termasuk fix ini.
