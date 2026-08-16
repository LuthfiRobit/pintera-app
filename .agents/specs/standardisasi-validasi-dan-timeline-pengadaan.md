# Spesifikasi: Standardisasi Validasi, Unified Audit Trail, dan Penyempurnaan Workflow Pengadaan

## 1. Ringkasan Masalah & Tujuan
Pengujian menyeluruh alur Pengadaan (dari approval internal Kepsek, persetujuan Yayasan, pencairan kas, hingga penyerahan LPJ belanja) telah berfungsi secara fungsional. Namun, ditemukan beberapa area kritis yang memerlukan penyempurnaan:
1. **Validasi Inputan (Frontend & Backend):** Belum ada validasi menyeluruh pada form pembuatan usulan, form LPJ, modal pencairan kas, dan form keputusan approval, baik di sisi client (Alpine.js / HTML5) maupun server (FormRequest dengan custom pesan berbahasa Indonesia dan komponen `<x-input-error>`).
2. **Unified Audit Trail (Riwayat Persetujuan & Aktivitas End-to-End):** Riwayat persetujuan sebelumnya hanya menampung log persetujuan step workflow (Kepsek & Yayasan). Aktivitas krusial lainnya (Pembuatan Proposal, Pencairan Kas, Pengunggahan LPJ Belanja, Audit Verifikasi Nota oleh Yayasan, dan Konversi Inventaris Sarpras) harus terakomodasi dalam satu kronologi riwayat aktivitas yang utuh dan terpadu.
3. **Penyempurnaan Dynamic Workflow Engine:** Memperkuat resolusi multi-tenant pada `ApproverResolverService` agar selalu memeriksa `approvable->lembaga_id` di samping `requester->lembaga_id`, serta memastikan konsistensi penanganan status.

---

## 2. Scope of Work

### In-Scope:
1. **Backend Validation & FormRequests:**
   - Menyempurnakan `StorePengajuanRequest`: validasi array item (nama, kategori, target ruangan, qty min:1, estimasi harga min:0, tipe pencatatan, file foto maks 5MB format image).
   - Menyempurnakan `StoreLpjRequest`: validasi harga riil per item, file foto nota, file foto fisik barang, file bukti kembali sisa kas jika ada sisa.
   - Menyempurnakan `StoreDisbursementRequest`: validasi nominal cair min:1, metode pembayaran (transfer/tunai), tanggal cair, file bukti transfer jika metode transfer.
   - Menyempurnakan `ProcessApprovalRequest`: validasi aksi (`approve`, `reject`, `request_revision`), catatan keputusan, dan keputusan per-item.
   - Menambahkan custom error messages berbahasa Indonesia di seluruh FormRequest tersebut.
2. **Frontend Validation & UI Feedback:**
   - Memasang komponen `<x-input-error>` pada semua input di form `create.blade.php`, `lpj/create.blade.php`, `disbursement/index.blade.php` (modal), `inbox/review.blade.php`, dan `audit-lpj/show.blade.php`.
   - Menambahkan client-side validation interaktif (Alpine.js prevent submit jika item kosong, validasi kalkulasi subtotal/grand total).
3. **Unified Audit Trail Presenter / Aggregator:**
   - Membuat method `PengajuanPengadaan::timelineEvents(): Collection` atau Presenter khusus yang mengagregasikan seluruh fase menjadi koleksi kronologis terurut waktu:
     - Event 1: *Pembuatan & Pengajuan Proposal* (Pengaju, waktu).
     - Event 2+: *Persetujuan Step Workflow* (Kepala Sekolah, Bendahara Yayasan, status keputusan, catatan review).
     - Event 3: *Pencairan Kas* (Bendahara Yayasan, nominal cair, metode, tanggal pencairan, bukti & catatan kasir).
     - Event 4: *Pengunggahan LPJ Belanja* (Admin Sarpras, total realisasi belanja, selisih sisa kas, berkas nota & foto barang).
     - Event 5: *Audit & Verifikasi LPJ* (Auditor/Bendahara Yayasan, status verifikasi/revisi, catatan audit).
     - Event 6: *Penerbitan / Konversi Aset Sarpras* (Waktu staging selesai, nomor kode aset terdaftar).
   - Menampilkan komponen Timeline UI modern dan elegan di `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php` dan `resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php`.
4. **Penyempurnaan Dynamic Workflow Engine:**
   - Memperbaiki `ApproverResolverService::checkRoleApprover()` agar memeriksa `$request->approvable?->lembaga_id ?? $request->requester?->lembaga_id`.

### Out-of-Scope:
- Mengubah skema struktur tabel database utama (tetap memanfaatkan relasi data yang ada secara efisien tanpa migrasi destruktif).
- Mengubah hak akses role di luar modul Sarpras dan Pengadaan.

---

## 3. Asumsi & Ketergantungan
- `PengajuanPengadaan` memiliki relasi ke `approvalRequest.logs.user`, `pengaju`, `lembaga`, `items`, dan `lpj.items`.
- Multi-tenancy dikelola oleh `TenantContext` dan role-based permissions Spatie.
- Menggunakan standar Blade, Tailwind CSS, Alpine.js, dan icon component `<x-icon>`.

---

## 4. Acceptance Criteria

| # | Kriteria Keberhasilan | Verifikasi |
|---|------------------------|------------|
| 1 | Seluruh form pengadaan menolak input tidak valid (misal: qty 0, harga minus, item kosong, format file salah) dan menampilkan `<x-input-error>` berwarna merah di bawah input yang bersangkutan. | Unit / Feature Test & Browser manual test. |
| 2 | Form pembuatan pengajuan mencegah submit jika belum ada item barang yang diisi. | Alpine.js validation & FormRequest test. |
| 3 | Halaman detail usulan (`proposal.show`) menampilkan **Timeline Riwayat Lengkap** yang merangkum setiap langkah dari draft awal, persetujuan tiap level, pencairan dana, pengunggahan LPJ, audit yayasan, hingga status inventarisasi. | Browser inspection & HTML assertion test. |
| 4 | `ApproverResolverService` secara akurat mencocokkan scope lembaga approver terhadap `approvable->lembaga_id`. | Feature Test `SequentialApprovalTest`. |
| 5 | Seluruh automated test suite (Pengadaan, Sarpras, Workflow) berjalan **100% PASS (0 failure, 0 regression)**. | `php artisan test`. |
