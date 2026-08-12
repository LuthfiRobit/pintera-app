# Handoff Log: Keuangan Cross-Sub-Project Audit Fixes (Sub-project 1-4)

**Tanggal:** 2026-08-11 (sesi terputus lalu dilanjutkan setelah user melanjutkan implementasi sendiri sampai keuangan-04)
**Branch:** `demo` (langsung, tanpa worktree)
**Status:** 3 dari 3 bug fix selesai + diverifikasi. Belum di-push ke remote.

---

## Apa yang dikerjakan

Sesi ini dimulai dari instruksi user: "analisa sub project 2b2 dan 2b3 yang sudah aku kerjakan" (dilanjut ke seluruh sub-project 1-4, karena user sudah mengimplementasikan jauh lebih banyak dari yang saya tahu terakhir kali — sesi sebelumnya terputus karena session limit di tengah Task 2 plan `keuangan-02b2-proses-tagihan`, dan user melanjutkan sendiri sampai selesai 2b2, 2b3, sub-project 03 (Wallet & Auto-Allocation), dan sub-project 04 (Payment Channels), plus rombak UI/UX terpisah untuk halaman jenis-tagihan).

### Audit lintas sub-project 1-4 (bukan implementasi baru, verifikasi kode nyata vs spec/log)

Dibaca ulang: `.agents/specs/`, `.agents/plans/`, `.agents/logs/` untuk 02b1-02b3, 03, 04, dibandingkan dengan spec asli di `docs/superpowers/specs/2026-08-10-keuangan-0{3,4}-*-design.md` (dari sebelum konvensi `.agents/` dipakai). Ditemukan 4 hal:

1. **Kontradiksi kebijakan Auto-Allocation Engine (sub-project 03)** — spec asli bilang "skip penuh" (lewati tagihan yang tak bisa dilunasi penuh oleh saldo, coba tagihan berikutnya), spec refresh di `.agents/` diam-diam menggantinya jadi "Partial Top-Down" (bayar sebagian, berhenti begitu saldo habis) tanpa penanda bahwa ini pembalikan keputusan. **Dikonfirmasi ke user: Partial Top-Down (kode `AutoAllocationEngine.php` saat ini) adalah kebijakan yang benar dan final** — bukan bug. Dicatat di `.agents/specs/keuangan-03-wallet-auto-allocation.md` bagian "Ambiguitas Terselesaikan". **Tidak ada perubahan kode untuk ini.**
2. **`JenisTagihanMonitoringController::batalTagihan()`** (sub-project 2b-3) — ownership check (`tagihan` milik `jenisTagihan` yang mana) berjalan SETELAH business-rule check (status `belum_bayar`), membocorkan status pembayaran tagihan lintas-tenant lewat perbedaan kode respons 422 vs 403. Aksi pembatalan sendiri tetap terlindungi (ownership check tetap jalan sebelum `->update()`), tapi urutan-nya membocorkan info. **Diperbaiki (Task 1).**
3. **`BriWebhookController` selalu return HTTP 200** (sub-project 04) — bahkan saat blok catch terluar (mencakup exception "Payment reference not found") tereksekusi. Payment gateway sungguhan mengandalkan respons non-2xx untuk retry; bug ini berarti webhook yang gagal diproses TIDAK PERNAH di-retry oleh BRI, dan cron reconciliation cuma polling record `WAITING` yang SUDAH ADA (tidak bisa menemukan payment yang reference-nya sama sekali tidak match). **Diperbaiki (Task 2).**
4. **`BriWebhookController` tidak validasi amount untuk `BILL_DIRECT`** — `$amountPaid` dari webhook di-parse tapi tidak pernah dicek untuk VA tipe `BILL_DIRECT`; `PaymentAllocationService::allocate()` percaya penuh ke `pembayaran_tagihan.amount_allocated` yang tersimpan sejak VA dibuat, apa pun yang benar-benar dilaporkan BRI sebagai amount masuk. **Diperbaiki (Task 3)** dengan guard `bccomp()` yang throw (ditangkap 500 oleh fix Task 2) kalau `$va->amount` tidak cocok dengan `$amountPaid`.

### 3 Task eksekusi (subagent-driven-development, masing-masing TDD + task review)

- **Task 1**: `batalTagihan()` — tukar urutan 2 `if` block, tambah test yang membedakan "ownership dicek dulu" (403) dari "status dicek dulu" (422 — bug lama). Commit `48053fa`.
- **Task 2**: `BriWebhookController` outer catch — return 500 alih-alih 200 saat exception. Inner try/catch di sekitar `$wallet->topup()` SENGAJA tidak disentuh (kegagalan itu memang harus tetap 200, karena transaction utama sudah commit, hanya kredit wallet-nya yang di-retry lewat reconciliation cron). Commit `4bbcdf7`.
- **Task 3**: `BriWebhookController` — guard `bccomp($va->amount, $amountPaid)` di cabang `BILL_DIRECT`, throw kalau tidak cocok (rollback transaction penuh — VA/pembayaran/tagihan semua tidak tersentuh saat mismatch). Cabang `WALLET_PERMANENT` (amount memang dinamis/tidak fixed) tidak disentuh. Commit `e2e6137`.

Semua 3 task review bersih (0 findings tiap task) — tidak ada fix round yang diperlukan.

---

## Keputusan penting yang diambil

1. **Kebijakan Partial Top-Down dikonfirmasi FINAL**, bukan bug — lihat poin 1 di atas. Spec asli di `docs/superpowers/specs/2026-08-10-keuangan-03-...design.md` pada poin ini dianggap usang/superseded.
2. **Task 2 dan Task 3 sengaja diurutkan berurutan** (bukan diparalelkan) karena Task 3 secara eksplisit bergantung pada perilaku 500-response yang Task 2 hasilkan — Task 3 tidak menduplikasi logic response-code, cuma throw dan mengandalkan catch block Task 2 yang sudah ada.
3. **Tidak menyentuh `AutoAllocationEngine.php`** sesuai Global Constraint plan ini — kebijakan alokasinya sudah dikonfirmasi benar (poin 1), di luar scope perbaikan bug apa pun di plan ini.

---

## Hasil Verification (Stage 6/7)

**Test file yang disentuh langsung — 16 tests, 59 assertions: SEMUA PASS**
`JenisTagihanMonitoringTest` (9/9, termasuk 1 test baru), `WebhookControllerTest` (7/7, termasuk 2 test baru).

**`tests/Feature/Keuangan/` — 102 tests, 298 assertions: SEMUA PASS**

**Full suite: 1456 passed, 9 failed (4585 assertions)**

⚠️ **PENTING — baseline berubah dari yang tercatat di log-log sebelumnya (6 failure):** ditemukan 3 failure BARU yang **BUKAN disebabkan oleh 3 fix di plan ini** (dikonfirmasi: diff Task 1-3 cuma menyentuh `JenisTagihanMonitoringController.php`, `BriWebhookController.php`, dan 2 file test terkait — sama sekali tidak menyentuh apa pun terkait form jenis-tagihan):

- `Tests\Feature\Admin\JenisTagihanFormPageTest` — FAIL
- `Tests\Feature\Admin\JenisTagihanKeringananFormTest` — FAIL
- `Tests\Feature\Admin\JenisTagihanSasaranFormTest` — FAIL

Dicek satu contoh (`JenisTagihanFormPageTest`): gagal karena `assertSee('Tambah Jenis Tagihan')` tidak ketemu lagi di halaman — konsisten dengan rombak UI/UX terpisah yang saya lihat di git log sesi ini (`d562cee` "rombak ui/ux master jenis tagihan ke standar sticky sidebar dan partial spa", `526b358` "migrasi penuh jenis tagihan ke arsitektur server-side pagination", `e667174` "implementasi form gold standard jenis tagihan dengan tomselect, modal, dan validasi pre-submit", dst. — sampai `9383f72`). Rombak UI ini mengubah struktur/teks halaman `admin/jenis-tagihan` cukup jauh sehingga 3 test lama dari plan `keuangan-02b1-form-jenis-tagihan` (yang saya tulis sebelum rombak UI ini terjadi) sekarang basi.

**Baseline pre-existing yang SUDAH DIKETAHUI (6 failure, tidak berubah, tidak terkait modul Keuangan):**
`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`.

**Baseline pre-existing BARU (3 failure, ditemukan sesi ini, TIDAK diperbaiki — di luar scope plan ini):**
`JenisTagihanFormPageTest`, `JenisTagihanKeringananFormTest`, `JenisTagihanSasaranFormTest` — basi akibat rombak UI/UX jenis-tagihan yang terjadi di luar sesi/plan ini.

**Total baseline pre-existing sekarang: 9 failure** (bukan 6 lagi) — siapa pun yang lanjut kerja di modul ini harus pakai angka 9 sebagai baseline, bukan 6.

---

## Hal yang masih perlu direview manusia/Claude

1. **3 test form jenis-tagihan yang basi akibat rombak UI** — perlu diputuskan: update test-nya mengikuti struktur UI baru (TomSelect/gold-standard), atau hapus kalau sudah digantikan test lain yang lebih relevan dengan UI baru. Tidak diperbaiki di sesi ini karena di luar scope 4 bug yang diaudit (murni soal 1-4), dan menyentuhnya butuh riset terpisah tentang apa sebenarnya yang berubah di rombak UI tsb (saya tidak terlibat dalam sesi rombak UI itu).
2. **Amount-validation di webhook (Task 3) bersifat defensif** — efektivitasnya di produksi nyata bergantung pada apakah BRI sendiri sudah enforce exact-amount settlement di VA/QRIS mereka (belum bisa diverifikasi tanpa kredensial sandbox BRI, sesuai catatan log sub-project 04 sebelumnya).
3. **Git state:** Semua 5 commit (3 fix + 2 docs) ada di `demo` langsung. **Belum di-push ke remote.**
4. **Belum ada final whole-plan review terpisah** untuk 3 fix ini (task-level review sudah 0 findings tiap task, dianggap cukup mengingat scope kecil dan independen antar-task — beda dengan plan besar seperti 2b-1/2b-2 yang butuh whole-plan pass terpisah).
