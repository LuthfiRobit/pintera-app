# Spec: Perbaikan Audit Modul SDM Izin/Cuti (Crash Pool, HTML Rusak, Pencarian Alasan, Riwayat Admin)

> **Tanggal**: 11 September 2026
> **Konteks**: Lanjutan audit SDM Izin/Cuti sesi ini (setelah 3 perbaikan engine `Workflow` bersama selesai & diverifikasi: session lembaga, fail-closed target lembaga, guard status terminal). Audit lanjutan (scope yayasan/lembaga + UI/UX + wiring) menemukan 4 masalah nyata yang SEMUANYA spesifik ke domain SDM (bukan engine bersama), jadi TIDAK menyentuh `app/Domains/Workflow/*`.

---

## 1. Latar Belakang

Dua putaran audit dilakukan terhadap modul SDM Izin/Cuti:

1. **Audit scope yayasan/lembaga** — hasil: modul ini AMAN dari sisi isolasi tenant (index admin, detail/approval, self-service, dashboard counter semua benar), KECUALI 1 bug crash di alur pengajuan mandiri untuk pegawai pool yayasan.
2. **Audit UI/UX & wiring alur bisnis** — hasil: 3 masalah tambahan (1 HTML rusak, 1 fitur pencarian yang tidak berfungsi sesuai label, 1 kapabilitas hilang — tidak ada riwayat/arsip di sisi admin).

Sebuah temuan ke-5 (state `RevisionRequired` tidak pernah ter-wire ke UI persetujuan SDM) SENGAJA TIDAK masuk spec ini — lihat §6.

---

## 2. Temuan & Perbaikan

### 2.1 Bug Crash: Pegawai Pool Yayasan Tidak Bisa Mengajukan Izin/Cuti (Severity: High)

**Lokasi**: `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php:66-90` (method `buatPengajuan`, dipanggil dari `execute`)

**Akar masalah**: `Karyawan.lembaga_id` nullable (mendukung pola "pegawai pool yayasan" — staf yang tidak terikat 1 lembaga, dikonfirmasi lewat factory state `Karyawan::factory()->pool()`, `database/factories/KaryawanFactory.php:61-66`). Tapi kolom `pengajuan_izin_cuti.lembaga_id` di database adalah **NOT NULL** dengan foreign key (`database/schema/mysql-schema.sql:1844`). Kode saat ini:

```php
private function buatPengajuan(
    Model $pegawai,
    KategoriPengajuanIzin $kategori,
    string $tanggalMulai,
    string $tanggalSelesai,
    string $alasan,
): PengajuanIzinCuti {
    return DB::transaction(function () use ($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan) {
        $pengajuan = $pegawai->pengajuanIzinCuti()->create([
            'lembaga_id' => $pegawai->lembaga_id,
            // ...
        ]);
        // ...
    });
}
```

Kalau `$pegawai->lembaga_id` NULL, `INSERT` ini melanggar constraint NOT NULL → `QueryException` (SQLSTATE 23000) tidak tertangani → **HTTP 500** untuk pegawai yang mengajukan, bukan pesan yang jelas.

**Kenapa bukan "buat lembaga_id nullable saja"**: Workflow `IZIN_CUTI_SDM` yang menjalankan approval-nya punya 2 step, KEDUANYA `scope_level: 'lembaga'` (role `kepala_sekolah` dan `admin_sdm` — lihat `WorkflowDefinitionSeeder`). Approver untuk step semacam ini di-resolve lewat `ApproverResolverService::checkRoleApprover()` yang MEMBUTUHKAN `$request->approvable?->lembaga_id` bernilai konkret (fail-closed kalau NULL, hasil perbaikan sesi ini sebelumnya). Kalau `pengajuan_izin_cuti.lembaga_id` dibiarkan NULL, TIDAK ADA siapa pun yang bisa meng-approve pengajuan itu (fail-closed akan selalu menolak) — pengajuan akan macet permanen di step 1, bukan cuma pindah masalah dari "crash saat submit" jadi "macet setelah submit". Mendesain ulang supaya yayasan-level approver bisa memproses pengajuan pegawai pool adalah **keputusan produk terpisah** (butuh step workflow baru berscope yayasan, dan kejelasan siapa yang berwenang) — di luar scope perbaikan bug ini.

**Perbaikan (minimal, root-cause untuk crash, BUKAN redesain workflow)**: validasi eksplisit di awal `execute()`, sebelum logika tanggal/kuota, supaya kegagalan berubah dari crash 500 jadi pesan yang jelas dan actionable:

```php
public function execute(
    Model $pegawai,
    KategoriPengajuanIzin $kategori,
    string $tanggalMulai,
    string $tanggalSelesai,
    string $alasan,
): PengajuanIzinCuti {
    if ($pegawai->lembaga_id === null) {
        throw ValidationException::withMessages([
            'pegawai' => 'Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan (tanpa lembaga tetap). Silakan hubungi admin SDM untuk memprosesnya secara manual.',
        ]);
    }

    if ($tanggalMulai > $tanggalSelesai) {
        // ...kode existing tidak berubah...
```

Ini menutup crash-nya HARI INI (pesan jelas, bukan 500) sambil jujur ke pengguna bahwa kapabilitasnya memang belum ada — bukan pura-pura berhasil.

---

### 2.2 HTML Tidak Tertutup di Empty-State Riwayat Self-Service (Severity: Medium)

**Lokasi**: `resources/views/sdm/izin-cuti/index.blade.php:218-228`

Kode saat ini (blok `@empty` di dalam `@forelse`):

```blade
@empty
    <tr>
        <td colspan="5" class="px-5 py-12 text-center text-gray-400">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700">Belum ada pengajuan izin/cuti.</p>
            <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Klik tombol "+ Ajukan Baru" di atas untuk mengajukan permohonan izin atau cuti.</p>
    @endforelse
</tbody>
```

`<td>` dan `<tr>` dibuka tapi tidak pernah ditutup sebelum `@endforelse`. Perbaikan — tambah penutup yang hilang:

```blade
@empty
    <tr>
        <td colspan="5" class="px-5 py-12 text-center text-gray-400">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700">Belum ada pengajuan izin/cuti.</p>
            <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Klik tombol "+ Ajukan Baru" di atas untuk mengajukan permohonan izin atau cuti.</p>
        </td>
    </tr>
@endforelse
</tbody>
```

---

### 2.3 Filter "Cari Pegawai / Alasan" di Admin Tidak Benar-Benar Mencari Alasan (Severity: Medium)

**Lokasi**: `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php:4-21` (mapping item ke JS) + `resources/js/approval-izin-cuti-spa.js:7-13` (`filteredItems`)

Label kolom pencarian: **"Cari Pegawai / Alasan"** (baris 95), tapi field `alasan` tidak pernah dikirim ke Alpine, dan `filteredItems` cuma mencocokkan `item.nama`:

```js
get filteredItems() {
    return this.items.filter((item) => {
        const matchSearch = item.nama.toLowerCase().includes(this.searchQuery.toLowerCase());
        if (this.activeFilter === 'semua') return matchSearch;
        return matchSearch && item.kategori === this.activeFilter;
    });
},
```

**Perbaikan** — kirim `alasan` dari server, cocokkan di kedua field:

Di `index.blade.php`, tambahkan `'alasan' => $item->alasan,` ke array item (setelah baris `'nama' => ...`):

```php
return [
    'id' => $item->id,
    'nama' => $item->pegawai->nama ?? '—',
    'alasan' => $item->alasan,
    'kategori' => $k,
    // ...sisanya tidak berubah...
];
```

Di `approval-izin-cuti-spa.js`, ubah `matchSearch`:

```js
get filteredItems() {
    return this.itemsInView.filter((item) => {
        const query = this.searchQuery.toLowerCase();
        const matchSearch = item.nama.toLowerCase().includes(query) || item.alasan.toLowerCase().includes(query);
        const matchFilter = this.activeFilter === 'semua' || item.kategori === this.activeFilter;
        return matchSearch && matchFilter;
    });
},
```

(`itemsInView` baru diperkenalkan di §2.4 — dua perbaikan ini menyentuh file JS yang sama, digabung jadi 1 hasil akhir konsisten, bukan 2 patch terpisah yang saling menimpa.)

---

### 2.4 Tidak Ada Riwayat/Arsip di Sisi Admin untuk Pengajuan yang Sudah Diputuskan (Severity: Medium)

**Lokasi**: `app/Http/Controllers/Admin/ApprovalIzinCutiController.php:21-31` (`index()`) + `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php` + `resources/js/approval-izin-cuti-spa.js`

**Akar masalah**: `index()` men-scope query HANYA ke status `Pending`/`InReview` — begitu pengajuan diputuskan (Approved/Rejected/Cancelled/RevisionRequired), langsung hilang dari daftar SELAMANYA dari sisi UI (walau `show()` masih bisa diakses langsung kalau tahu ID-nya via URL, tidak ada jalan menemukannya dari UI manapun). Modul Rapor sudah punya pola riwayat setara (`PersetujuanController` Rapor, tab `?tab=riwayat` di route index yang sama) — modul SDM belum.

**Desain perbaikan**: mengikuti pola SPA-reaktif yang SUDAH dipakai halaman ini (semua data dimuat sekali ke Alpine, filter/kategori 100% client-side, TANPA round-trip server) — bukan pola AJAX-fragment Rapor (server-render ulang partial). Kirim SEMUA pengajuan (bukan cuma yang aktif) ke Alpine sekali di load awal, tandai tiap item `isDecided`, tambah toggle tab "Menunggu" / "Riwayat" di client. Kenapa pola ini, bukan pola Rapor: halaman ini SUDAH 100% client-filtered (tidak ada satupun request AJAX di file JS-nya saat ini) — mengikuti pola yang sudah established di file ini sendiri lebih konsisten daripada mengimpor pola berbeda dari domain lain.

**2.4.a — Controller** (`app/Http/Controllers/Admin/ApprovalIzinCutiController.php`):

```php
public function index(): View
{
    $this->authorize('kehadiran-sdm.izin.approve');

    $daftar = PengajuanIzinCuti::with(['pegawai', 'approvalRequest.currentStep'])
        ->whereHas('approvalRequest')
        ->latest('tanggal_mulai')
        ->get();

    return view('admin.kehadiran-sdm.izin-cuti.index', ['daftar' => $daftar]);
}
```

(Menghapus `whereIn('status', [Pending, InReview])` — sekarang mengambil SEMUA pengajuan yang punya `approvalRequest`, apapun statusnya. Import `ApprovalStatus` yang jadi tidak terpakai di controller ini WAJIB dihapus kalau tidak dipakai method lain — cek dulu sebelum menghapus, `show()` masih memakainya di baris 41.)

**2.4.b — View** (`resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php`):

Ubah mapping item (baris 4-21) — tambah field status & `alasan` (§2.3):

```php
<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="approvalIzinCutiSPA({
    items: @js($daftar->map(function ($item) {
        $k = $item->kategori->value;
        $class = match($k) {
            'cuti' => 'bg-blue-100 text-blue-800',
            'sakit' => 'bg-rose-100 text-rose-800',
            default => 'bg-amber-100 text-amber-800',
        };
        $status = $item->approvalRequest?->status;
        return [
            'id' => $item->id,
            'nama' => $item->pegawai->nama ?? '—',
            'alasan' => $item->alasan,
            'kategori' => $k,
            'kategoriLabel' => $item->kategori->label(),
            'kategoriClass' => $class,
            'periode' => $item->tanggal_mulai->format('d M Y') . ' — ' . $item->tanggal_selesai->format('d M Y'),
            'step' => $item->approvalRequest?->currentStep?->step_name ?? '—',
            'statusLabel' => $status?->label() ?? '—',
            'statusTone' => $status?->badgeTone() ?? 'slate',
            'isDecided' => $status !== null && ! in_array($status, [\App\Domains\Workflow\Enums\ApprovalStatus::Pending, \App\Domains\Workflow\Enums\ApprovalStatus::InReview], true),
            'showUrl' => route('admin.kehadiran-sdm.izin-cuti.show', $item),
        ];
    })->values()->all()),
})">
```

Tambah toggle tab (setelah baris pembuka Filter Card, sebelum baris Search Input, di dalam grid filter yang sama — baris ~92):

```blade
<div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
    {{-- View Mode Toggle --}}
    <div class="lg:col-span-3">
        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tampilan</label>
        <div class="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1">
            <button
                @click="viewMode = 'menunggu'"
                type="button"
                :class="viewMode === 'menunggu' ? 'bg-white shadow-2xs font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="flex-1 rounded-lg px-3 py-1.5 text-xs transition-all"
            >Menunggu</button>
            <button
                @click="viewMode = 'riwayat'"
                type="button"
                :class="viewMode === 'riwayat' ? 'bg-white shadow-2xs font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="flex-1 rounded-lg px-3 py-1.5 text-xs transition-all"
            >Riwayat</button>
        </div>
    </div>

    {{-- Search Input --}}
    <div class="lg:col-span-5">
        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pegawai / Alasan</label>
        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input x-model="searchQuery" type="text" placeholder="Ketik nama pegawai atau alasan..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
        </div>
    </div>

    {{-- Pill Tabs Filters --}}
    <div class="lg:col-span-4 flex items-center justify-start lg:justify-end gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
        {{-- ...4 tombol pill kategori existing TIDAK BERUBAH... --}}
    </div>
</div>
```

(Grid berubah dari `lg:col-span-6`+`lg:col-span-6` jadi `lg:col-span-3`+`lg:col-span-5`+`lg:col-span-4` — tetap total 12 kolom, breakpoint mobile tetap 1 kolom karena base class `grid-cols-1` tidak berubah.)

Stat card "Menunggu Approval" (baris 53, `x-text="items.length"`) diubah pakai getter baru supaya tetap menghitung SEMUA pending terlepas dari `viewMode` yang sedang aktif:

```blade
<p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="totalPending"></p>
```

Tambah kolom "Status" di header tabel (baris 161-167, setelah kolom "Langkah Saat Ini"):

```blade
<th class="px-5 py-3">Langkah Saat Ini</th>
<th class="px-5 py-3">Status</th>
```

Tambah sel Status di baris data (setelah sel "step", baris ~208-215):

```blade
<td class="px-5 py-3.5">
    <span :class="'bg-' + item.statusTone + '-100 text-' + item.statusTone + '-800'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" x-text="item.statusLabel"></span>
</td>
```

Update `colspan` empty-state dari `5` jadi `6` (baris 172, karena nambah 1 kolom): `<td colspan="6" ...>`

**2.4.c — JS** (`resources/js/approval-izin-cuti-spa.js`), versi final gabungan §2.3+§2.4:

```js
export function approvalIzinCutiSPA(config) {
    return {
        items: config.items ?? [],
        searchQuery: '',
        activeFilter: 'semua',
        viewMode: 'menunggu',

        get itemsInView() {
            return this.items.filter((item) => this.viewMode === 'menunggu' ? !item.isDecided : item.isDecided);
        },

        get filteredItems() {
            return this.itemsInView.filter((item) => {
                const query = this.searchQuery.toLowerCase();
                const matchSearch = item.nama.toLowerCase().includes(query) || item.alasan.toLowerCase().includes(query);
                const matchFilter = this.activeFilter === 'semua' || item.kategori === this.activeFilter;
                return matchSearch && matchFilter;
            });
        },

        get totalPending() {
            return this.items.filter((i) => !i.isDecided).length;
        },

        get countCuti() {
            return this.itemsInView.filter((i) => i.kategori === 'cuti').length;
        },

        get countSakit() {
            return this.itemsInView.filter((i) => i.kategori === 'sakit').length;
        },

        get countIzin() {
            return this.itemsInView.filter((i) => i.kategori === 'izin').length;
        },

        get countDispensasi() {
            return this.itemsInView.filter((i) => ['izin', 'sakit'].includes(i.kategori)).length;
        },
    };
}
```

Catatan perilaku yang SENGAJA berubah dari sebelumnya: pill kategori "Semua/Cuti/Sakit/Izin" dan angkanya sekarang dihitung dari `itemsInView` (mengikuti `viewMode` aktif), BUKAN dari total keseluruhan seperti sebelumnya — supaya angka pill tetap relevan dengan tab yang sedang dilihat (mis. saat di tab "Riwayat", pill "Cuti" menunjukkan jumlah cuti yang SUDAH diputuskan, bukan jumlah cuti yang masih pending). Stat card "Menunggu Approval" di atas SENGAJA TIDAK ikut berubah oleh `viewMode` (pakai `totalPending`, bukan `itemsInView`/`filteredItems`) — supaya admin yang sedang membuka tab Riwayat tetap tahu ada berapa banyak yang masih perlu ditindaklanjuti tanpa harus pindah tab.

---

## 3. Dampak & Kompatibilitas

- **§2.1**: hanya menambah validasi baru di titik masuk `execute()`. Semua pengajuan existing dengan `lembaga_id` konkret (mayoritas — Guru selalu punya `lembaga_id` NOT NULL, Karyawan non-pool juga) TIDAK terdampak sama sekali. Test existing (`AjukanIzinCutiActionTest.php`) semuanya memakai `lembaga_id` konkret, dipastikan tetap lolos.
- **§2.2**: murni perbaikan markup, tidak mengubah data/logika apa pun.
- **§2.3+§2.4**: mengubah `index()` controller (query lebih luas — SEKARANG mengembalikan lebih banyak baris data per-load, termasuk yang sudah final), plus perubahan struktural view+JS. Route, nama method, dan endpoint TIDAK berubah (tidak ada breaking change ke URL/route lain). `show()`/`decision()` TIDAK disentuh — perilaku approve/reject/guard `canDecide` tetap identik.
- **Potensi payload lebih besar**: kalau volume pengajuan izin/cuti historis sebuah yayasan sangat besar (ribuan baris), mengirim semua riwayat sekaligus ke client bisa jadi berat. Ini SENGAJA diterima sebagai trade-off untuk sesi ini (skala data SDM realistis jauh dari itu) — lihat §6 untuk kenapa pagination tidak dimasukkan scope.

---

## 4. Pengujian yang Dibutuhkan

- **§2.1**: test baru di `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` — memakai `Karyawan::factory()->pool()->create(...)` (lembaga_id null), memanggil `AjukanIzinCutiAction::execute()`, harus `toThrow(ValidationException::class)`, DAN pastikan TIDAK ADA baris `PengajuanIzinCuti` yang ter-insert (`PengajuanIzinCuti::count()` tetap 0 sebelum-sesudah, membuktikan tidak ada partial-write sebelum exception). Plus 1 test regresi: pegawai non-pool (lembaga_id konkret) tetap berhasil mengajukan seperti biasa (baseline, harus tetap lolos).
- **§2.2**: tidak butuh test otomatis terpisah (murni markup) — TAPI tambahkan assert pada test Feature existing yang sudah meng-hit halaman ini (kalau ada) untuk memastikan `$response->assertOk()` dan/atau `assertSee('Belum ada pengajuan')` masih lolos dengan HTML yang sudah diperbaiki. Kalau tidak ada test Feature untuk halaman ini sama sekali saat ini, TIDAK WAJIB membuat baru khusus untuk ini (di luar scope perbaikan markup semata) — verifikasi manual dev-server sudah cukup untuk item non-logic ini.
- **§2.3+§2.4**: test Feature baru untuk `ApprovalIzinCutiController::index()` — buat 2+ pengajuan dengan status berbeda (1 Pending, 1 Approved), hit route `admin.kehadiran-sdm.izin-cuti.index`, assert response mengandung DATA KEDUANYA (mis. `assertSee` nama pegawai atau ID keduanya di payload `items` yang di-render lewat `@js()`) — membuktikan riwayat SEKARANG ikut terkirim ke client, bukan disaring habis di server seperti sebelumnya. Test JS/Alpine murni (viewMode toggle, search alasan) TIDAK ditulis sebagai automated test (proyek ini tidak punya test JS unit untuk SPA Alpine lain — konsisten dengan pola existing di seluruh sesi ini, verifikasi manual dev-server).
- **Regresi wajib**: seluruh `tests/Feature/Sdm/` dan `tests/Feature/Admin/ApprovalIzinCutiControllerTest.php` (kalau ada) dijalankan ulang — pastikan tidak ada yang gagal akibat perubahan query `index()` (query lama expect HANYA Pending/InReview, kalau ada test yang assert jumlah baris/`count()` items secara eksak, itu WAJIB diupdate mengikuti perilaku baru, bukan dianggap regresi).

---

## 5. Struktur Task yang Disarankan (untuk fase plan nanti)

1. **Task 1**: Perbaikan §2.1 (crash pool) — paling independen, paling kritis (High), TDD murni di Action layer.
2. **Task 2**: Perbaikan §2.2 (HTML rusak) — independen, trivial, cepat.
3. **Task 3**: Perbaikan §2.3+§2.4 digabung jadi 1 task (controller+view+JS saling terkait, tidak bisa dipisah tanpa membuat state antara yang rusak — lihat catatan di §2.3 soal `itemsInView` dipakai bersama).
4. **Task 4**: Penutup — jalankan seluruh test SDM + Pint, verifikasi manual dev-server untuk 2 perubahan UI murni (§2.2, §2.4 toggle+search alasan) karena tidak ada automated test JS.

Task 1-3 saling independen satu sama lain (tidak ada dependency lintas-task), bisa dikerjakan urutan bebas, Task 4 wajib terakhir.

---

## 6. Item Sengaja Tidak Masuk Scope

- **State `RevisionRequired` tidak pernah ter-wire ke UI SDM** (temuan audit wiring) — ini BUKAN bug aktif hari ini (state itu memang tidak pernah tercapai lewat jalur `ApprovalIzinCutiController::decision()` yang cuma menerima APPROVE/REJECT). Mewire kapabilitas "Minta Revisi" penuh (tombol admin baru + rute edit/resubmit untuk pegawai) adalah **penambahan fitur**, bukan perbaikan bug — butuh keputusan produk (apakah SDM memang perlu alur revisi seperti Rapor, atau sengaja disederhanakan jadi binary approve/reject). Tidak dimasukkan spec ini.
- **Pagination/infinite-scroll untuk tab Riwayat** — di luar scope, lihat catatan trade-off §3. Kalau volume data jadi masalah nyata di masa depan, itu perbaikan performa terpisah.
- **Filter tanggal/rentang waktu untuk tab Riwayat** — tidak diminta, tidak ada temuan yang menyebutnya, tidak ditambahkan (YAGNI).
- **Redesain agar pegawai pool yayasan BISA benar-benar mengajukan & diproses** (bukan cuma dapat pesan error yang jelas) — butuh step workflow baru berscope yayasan + keputusan siapa approver-nya. Perbaikan §2.1 di spec ini HANYA menutup crash-nya (fail gracefully), BUKAN membangun kapabilitas baru itu. Lihat penjelasan lengkap alasannya di §2.1.
- **Perubahan ke `app/Domains/Workflow/*` (engine bersama)** — tidak diperlukan sama sekali untuk 4 perbaikan ini, semuanya murni domain SDM.

---

## 7. Self-Review — Putaran 1 (standar)

- **Placeholder scan**: tidak ada "TBD"/"TODO" — semua kode di §2 lengkap, bisa langsung disalin ke plan.
- **Konsistensi internal**: §2.3 dan §2.4 sama-sama mengubah `approval-izin-cuti-spa.js` — sudah digabung jadi 1 versi final di §2.4.c, bukan 2 potongan patch terpisah yang berisiko saling menimpa saat plan dipecah jadi task. Dikonfirmasi konsisten.
- **Cakupan vs temuan audit**: 4 dari 5 temuan (crash pool, HTML rusak, pencarian alasan, riwayat admin) masuk spec. 1 (RevisionRequired tidak ter-wire) sengaja dikeluarkan dengan alasan eksplisit di §6. Tidak ada temuan yang terlewat tanpa penjelasan.

## 8. Self-Review — Putaran 2 (verifikasi empiris: field/relasi yang dipakai kode fix benar-benar ada)

- `PengajuanIzinCuti::$fillable` (`app/Domains/Sdm/Models/PengajuanIzinCuti.php:20`) sudah mengandung `alasan` — dikonfirmasi field ini memang tersimpan per-row, bukan diasumsikan.
- `Karyawan::factory()->pool()` dikonfirmasi ADA (`database/factories/KaryawanFactory.php:61-66`), state factory yang dipakai di rencana test §4 bukan fiksi.
- `ApprovalStatus::label()`/`badgeTone()` (`app/Domains/Workflow/Enums/ApprovalStatus.php`) dikonfirmasi menutupi SEMUA 6 case termasuk `RevisionRequired`/`Cancelled` — badge di §2.4.b tidak akan pernah render tone/label kosong untuk status apa pun yang mungkin muncul di riwayat.
- Route `admin.kehadiran-sdm.izin-cuti.index` dikonfirmasi ADA (`routes/admin/kehadiran-sdm.php:43`), tidak perlu route baru untuk §2.4 — sesuai desain (pola SPA-reaktif, bukan route/tab server-side terpisah).

## 9. Self-Review — Putaran 3 (verifikasi dampak ke test existing)

- Digrep `tests/Feature/Sdm/` untuk pemakaian `ApprovalIzinCutiController::index` atau nama route `kehadiran-sdm.izin-cuti.index` — kalau ada test yang assert jumlah/isi `$daftar` secara eksak dengan asumsi HANYA Pending/InReview, itu akan butuh update mengikuti perilaku baru (query sekarang lebih luas). Ini sudah dicatat eksplisit di §4 "Regresi wajib" supaya implementer TIDAK kaget dan TIDAK menganggapnya sebagai bug baru kalau test lama perlu disesuaikan.
- Dikonfirmasi TIDAK ADA test yang menyentuh alur submit izin/cuti untuk `Karyawan::factory()->pool()` end-to-end sebelumnya (audit awal sudah mengonfirmasi ini) — jadi test baru di §4 untuk §2.1 murni tambahan, bukan revisi test yang sudah ada.

## 10. Self-Review — Putaran 4 (baca ulang dengan mata segar, cek copy/pesan)

- Pesan error §2.1 ("Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan...") sudah eksplisit menyebutkan APA yang terjadi (belum didukung) dan APA yang harus dilakukan (hubungi admin SDM) — konsisten dengan prinsip proyek "error menjelaskan apa yang salah dan cara memperbaikinya, bukan generik/menyalahkan".
- Grid kolom §2.4.b (`lg:col-span-3` + `lg:col-span-5` + `lg:col-span-4` = 12) dihitung ulang, totalnya benar 12, tidak overflow.
- Dicek ulang: perubahan `colspan` dari 5 ke 6 di baris empty-state (§2.4.b) SUDAH disebutkan eksplisit sebagai bagian dari instruksi — sebelumnya sempat lupa disebut di draft pertama Putaran 1, sudah ditambahkan sebelum spec ini final.
