# 📋 Handoff Log: Sub-Task 04b — Adaptive E-Rapor Engine: Backend & Approval Workflow

- **Spec:** [`.agents/specs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md)
- **Plan:** [`.agents/plans/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md)
- **Mode eksekusi:** Subagent-Driven Development, inline di branch `akademik-v2`, tanpa git worktree baru.
- **Base commit sebelum Task 1:** `a250683`
- **Commit akhir:** `1c01af3`
- **Status:** 🟢 SELESAI — seluruh task 1 s/d 6 lulus 100% (64 passed pada joint regression suite, 173 assertions).

---

## 1. Ringkasan Pekerjaan

Membangun fondasi backend murni (*headless by design*) untuk **Adaptive E-Rapor Engine**:
1. **Skema & Model Database**:
   - `pengajuan_rapor`: model `PengajuanRapor`, enum `StatusPengajuanRapor` (`Draft`, `Diajukan`, `Diverifikasi`, `Disetujui`, `Ditolak`), integrasi polymorphic `MorphOne` ke generic Universal Workflow Engine (`ApprovalRequest`).
   - `catatan_wali_kelas`: model `CatatanWaliKelas` (`catatan_sikap`, `catatan_perkembangan`, antropometri PAUD, `ekstrakurikuler`, `prestasi`, `pkl_info`, `keterangan_kenaikan`).
   - Penambahan kolom `kktp_minimal` (unsigned tinyint nullable, ambang numerik terpisah dari `bobot` dan `kktp` deskriptif) pada tabel `komponen_penilaian`.
2. **Perluasan Backward-Compatible 04a**:
   - DTO `KomponenPenilaianData` & `UpdateKomponenPenilaianData` mendukung `?int $kktpMinimal`.
   - `CreateKomponenPenilaianAction` & `UpdateKomponenPenilaianAction` menyimpan `kktp_minimal`.
   - 4 FormRequest (`StoreKomponenPenilaianRequest`, `UpdateKomponenPenilaianRequest`, `StoreKomponenPenilaianSendiriRequest`, `UpdateKomponenPenilaianSendiriRequest`) menambahkan rule `'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100']`.
   - `KomponenPenilaianSeeder` di-update dengan nilai default `75`.
3. **Capaian Kompetensi Generator**:
   - `CapaianKompetensiGenerator::generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array{tertinggi: ?string, terendah: ?string}` untuk auto-generate narasi capaian kompetensi tertinggi (jika $\ge$ KKTP) dan terendah/bimbingan (jika $<$ KKTP, fallback ambang 75 jika `kktp_minimal` null).
4. **Approval Workflow Engine Reuse & Actions**:
   - Definisi workflow baru `RAPOR_SEMESTER` di `WorkflowDefinitionSeeder` (Step 1: Verifikasi Waka Kurikulum `admin_akademik` [lembaga], Step 2: Persetujuan Akhir Kepala Sekolah `kepala_sekolah` [lembaga]). Definisi `PENGADAAN_SARPRAS` lama tetap terjaga 100%.
   - `SimpanCatatanWaliKelasAction`: menyimpan catatan wali kelas berbasis `CatatanWaliKelasData`.
   - `SubmitPengajuanRaporAction`: validasi kelengkapan catatan wali kelas untuk semua siswa di kelas, inisialisasi / reset approval request ke step 1 jika resubmit dari `Ditolak`.
   - `VerifyPengajuanRaporAction`: proses approval/rejection Waka Kurikulum dan sinkronisasi status ke `Diverifikasi` / `Ditolak`.
   - `ApprovePengajuanRaporAction`: proses approval/rejection Kepala Sekolah dan sinkronisasi status ke `Disetujui` / `Ditolak`.
   - Penegakan penguncian nilai di `SimpanNilaiSiswaAction`: menolak perubahan nilai untuk kelas+semester yang status pengajuan rapornya sudah `Disetujui`.
5. **Permissions & Keamanan Cross-Tenant**:
   - Permission baru: `rapor.input-wali`, `rapor.ajukan`, `rapor.verify`, `rapor.approve` terdaftar di `PermissionSeeder` dan di-assign pada `RoleSeeder` (`guru`, `admin_akademik`, `kepala_sekolah`).
   - Test keamanan cross-tenant membuktikan `PengajuanRapor` lembaga lain tidak dapat diakses dan resolver engine menolak approval dari aktor yang tidak sesuai role/lembaga.

---

## 2. Per-Task Detail & Commit History

| Task | Deskripsi | Commit | Hasil Pengujian |
|---|---|---|---|
| **Task 1** | Migrasi Skema, Model, Enum, Factory (`PengajuanRapor`, `CatatanWaliKelas`, `kktp_minimal`) | `6aece5a` | 3 passed (7 assertions) |
| **Task 2** | Perluasan `kktp_minimal` ke DTO/Action/FormRequest Komponen Penilaian (04a) | `2722ade` | 41 passed (113 assertions) |
| **Task 3** | Service `CapaianKompetensiGenerator` (narasi TP otomatis) | `0c0f38e` | 5 passed (9 assertions) |
| **Task 4** | Workflow Definition `RAPOR_SEMESTER`, DTO `CatatanWaliKelasData`, Action Simpan & Submit | `fcf9285` | 3 passed (9 assertions) + WorkflowEngineTest pass |
| **Task 5** | Action Verify, Approve, dan Penegakan Kunci Nilai pada `SimpanNilaiSiswaAction` | `4afcc77` | 3 passed (10 assertions) + 8 passed AsesmenControllerTest |
| **Task 6** | Permission Seeder & Test Keamanan Cross-Tenant (`RaporApprovalTenantScopeTest`) | `1c01af3` | 2 passed (2 assertions) + 64 passed joint regression bundle |

---

## 3. Keputusan Penting yang Diambil

1. **Pola Approval Request Reuse**:
   - Engine approval generic `Workflow` dipakai ulang tanpa memodifikasi core engine-nya.
   - Skenario *reject and resubmit* ditangani secara idempotensial pada `SubmitPengajuanRaporAction` dengan mereset step dan status `ApprovalRequest` existing ke `Pending` (bukan membuat baris baru).
2. **Kunci Nilai Konsisten**:
   - Penguncian nilai diterapkan langsung pada `SimpanNilaiSiswaAction` dengan mengecek keberadaan `PengajuanRapor` berstatus `Disetujui` untuk kombinasi `kelas_id` dan `semester_id`.
3. **Isolasi Tenant**:
   - `PengajuanRapor` dan `CatatanWaliKelas` menggunakan trait `BelongsToTenant` dengan auto-derivation `lembaga_id` dari `Kelas` / `Siswa`, memastikan akses lintas lembaga dicegah di level global scope.

---

## 4. Langkah Selanjutnya (Item Terbuka untuk Sub-Task Berikutnya)

1. **Sub-Task 04c**: Implementasi UI 4 Role (`Guru/Wali Kelas`, `Waka Kurikulum`, `Kepala Sekolah`, `Admin Lembaga`).
2. **Sub-Task 04d**: 4 Template PDF Resmi DomPDF Berbasis Jenjang (PAUD, SD, SMP/SMA, SMK).
3. **Full Test Suite**: Siap dijalankan setelah user mengonfirmasi persetujuan di Task 7.
