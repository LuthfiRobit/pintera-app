# Spec: Perbaikan Audit Menyeluruh Modul Pengadaan & LPJ Sarpras

> **Tanggal**: 11 September 2026
> **Konteks**: Audit menyeluruh (backend, workflow, scope tenant, frontend, UI/UX, wording) modul Pengadaan & LPJ Sarpras — 2 sub-modul portal (Lembaga: proposal+LPJ; Yayasan: inbox approval, pencairan, audit LPJ). Ditemukan 2 celah keamanan Critical (IDOR), 3 bug UI Critical, dan sejumlah gap wiring/wording. TIDAK menyentuh `app/Domains/Workflow/*` (engine bersama sudah diverifikasi benar dipakai modul ini).

---

## 1. Latar Belakang

Dua audit paralel dilakukan: (1) backend/workflow/scope/domain, (2) frontend/UI-UX/wording — mencakup 4 sub-halaman × berbagai role (pengaju lembaga, approver kepala sekolah, approver bendahara yayasan, auditor LPJ yayasan). Total temuan setelah verifikasi langsung ke kode (bukan sekadar laporan agent): **2 Critical keamanan (IDOR)**, **3 Critical UI**, **1 High**, **4 Medium**, **beberapa Low**.

Prinsip yang dipakai (konsisten dengan seluruh audit sesi ini): perbaiki akar masalah, fail-closed lebih aman dari fail-open, jangan bangun kapabilitas baru di luar yang diminta, verifikasi setiap klaim dengan bukti kode konkret.

---

## 2. Temuan & Perbaikan

### 2.1 [CRITICAL] LPJ Bisa Diisi untuk Proposal Lembaga Lain (IDOR)

**Lokasi**: `app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php:28-76` (method `create()` dan `store()`)

**Akar masalah**: `create()`/`store()` TIDAK punya guard kepemilikan lembaga sama sekali — kontras dengan `stagingInventory()`/`convertInventory()` di file yang sama (baris 81, 94) yang benar memakai `abort_unless($lpj->proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404)`. `PengajuanPengadaan` juga tidak memakai `TenantScope`, jadi tidak ada jaring pengaman kedua di level model. User lembaga A dengan permission `pengadaan.lpj.submit` tinggal mengganti ID proposal di URL untuk mengisi LPJ (nota, bukti fisik, realisasi belanja) atas proposal lembaga B yang sudah `Disbursed`.

**Perbaikan**: tambah `abort_unless` di awal `create()` dan `store()`, pola identik dengan `stagingInventory()`/`convertInventory()`:

```php
public function create(PengajuanPengadaan $proposal): View
{
    $this->authorize('pengadaan.lpj.submit');
    abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);

    if ($proposal->status !== StatusPengajuan::Disbursed) {
        abort(403, 'LPJ hanya dapat diisi untuk proposal yang telah dicairkan dananya.');
    }
    // ...sisa method tidak berubah...
}

public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
{
    abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);
    // ...sisa method tidak berubah (lanjut kode existing)...
}
```

### 2.2 [CRITICAL] `TenantContext::activeYayasanId()` Fail-Open ke Yayasan Pertama di Database

**Lokasi**: `app/Domains/Shared/Context/TenantContext.php:44-70`

**Akar masalah**: kalau `$user->yayasan_id`, `$user->lembaga?->yayasan_id`, dan lembaga hasil `activeLembagaId()` semuanya tidak bisa menentukan yayasan, baris 69 fallback ke `\App\Models\Yayasan::first()?->id` — yayasan PERTAMA di seluruh sistem, bukan `null`. Method ini dipakai 12 controller lintas Pengadaan+Sarpras.

**Jalur pemicu nyata (dikonfirmasi, bukan teori)**: `app/Http/Controllers/Admin/UserController.php:231,292` — `yayasan_id` user baru/diedit diambil dari `$request->user()->yayasan_id` (aktor pembuat), BUKAN diminta eksplisit. Platform admin (`platform_super_admin`, scope `platform`, `yayasan_id` selalu null karena tidak terikat yayasan manapun) yang membuat akun `yayasan_super_admin`/`bendahara_yayasan` untuk tenant baru menghasilkan akun dengan `yayasan_id = NULL`. ini SATU-SATUNYA jalur onboarding admin yayasan baru di sistem, bukan skenario tepi.

**Konsekuensi konkret**: akun begini yang mengakses `Yayasan\Pengadaan\*` akan memproses (approve/reject/cairkan dana/verifikasi LPJ) data milik yayasan PERTAMA di database, bukan yayasan yang seharusnya jadi tanggung jawabnya.

**Perbaikan**: fail-closed — kembalikan `null` alih-alih fallback ke `Yayasan::first()`:

```php
public function activeYayasanId(): ?int
{
    $user = $this->user();

    if (! $user) {
        return null;
    }

    if ($user->yayasan_id) {
        return $user->yayasan_id;
    }

    if ($user->lembaga && $user->lembaga->yayasan_id) {
        return $user->lembaga->yayasan_id;
    }

    $activeLembagaId = $this->activeLembagaId();
    if ($activeLembagaId) {
        $lembaga = \App\Models\Lembaga::find($activeLembagaId);

        if ($lembaga?->yayasan_id) {
            return $lembaga->yayasan_id;
        }
    }

    return null;
}
```

**Dampak berantai yang WAJIB ikut diperbaiki**: 4 controller punya pola `?? \App\Models\Yayasan::first()?->id` tambahan di atas panggilan `activeYayasanId()` (dead code redundan yang menyamarkan masalah yang sama) — begitu `activeYayasanId()` fail-closed, pola ini JUSTRU membuat fail-open kembali muncul di level controller kalau tidak ikut dihapus:
- `app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php:26`
- `app/Http/Controllers/Yayasan/Pengadaan/DisbursementPengadaanController.php:27`
- `app/Http/Controllers/Yayasan/Pengadaan/ApprovalPengadaanController.php:28`
- `app/Http/Controllers/Yayasan/Sarpras/RekapAsetGlobalController.php:26`

Ubah keempatnya dari:
```php
$yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;
```
menjadi:
```php
$yayasanId = $this->tenantContext->activeYayasanId();
abort_if($yayasanId === null, 403, 'Akun Anda belum terhubung ke yayasan manapun. Hubungi Super Admin Platform untuk memperbaiki data akun Anda.');
```

(Pesan error informatif — bukan 404 generik — karena ini murni masalah data akun, bukan resource yang tidak ditemukan; user perlu tahu harus menghubungi siapa.)

### 2.3 [CRITICAL] Filter Pencarian/Status Rusak Total di Daftar Proposal & Inbox Approval

**Lokasi**: `resources/views/portals/lembaga/pengadaan/proposal/index.blade.php:149`, `resources/views/portals/yayasan/pengadaan/inbox/index.blade.php:90`

**Akar masalah**: kedua halaman membungkus tabel dengan `<div id="wadah-daftar-tabel">`. Tapi `resources/js/data-table-filter.js:73` (`muatUlangDaftar()`) menulis hasil AJAX ke `this.$refs.tableContainer.innerHTML` — bukan cari elemen ber-`id`. Karena tidak ada `x-ref="tableContainer"` di kedua file ini, `this.$refs.tableContainer` `undefined` → error → tertangkap `catch` → toast "Gagal memuat data." setiap kali user mengetik di pencarian atau ganti filter. `disbursement/index.blade.php:107` dan `audit-lpj/index.blade.php:109` di modul yang SAMA sudah benar pakai `x-ref="tableContainer"` — jadi ini genuinely inkonsisten di dalam Pengadaan sendiri.

**Perbaikan**: ganti `id="wadah-daftar-tabel"` jadi `x-ref="tableContainer"` di kedua file, samakan pola dengan disbursement/audit-lpj:

`proposal/index.blade.php:149`, dari:
```blade
<div id="wadah-daftar-tabel" class="relative">
```
menjadi:
```blade
<div x-ref="tableContainer" class="relative">
```

`inbox/index.blade.php:90`, perubahan identik.

### 2.4 [CRITICAL] Badge Status Jatuh ke Abu-Abu untuk Status Paling Penting

**Lokasi**: `resources/views/components/badge.blade.php:4-11`

**Akar masalah**: komponen `<x-badge>` cuma mengenal 6 tone (`brass, green, red, amber, blue, slate`) di array `$tones`. Enum Pengadaan (`StatusPengajuan`, `StatusItemPengajuan`, `StatusLpj`, `TingkatUrgensi`) mengembalikan tone `purple`, `rose`, `indigo` yang TIDAK ADA di peta ini — jatuh ke fallback `slate` (abu-abu) via `$tones[$tone] ?? $tones['slate']`. Dipakai langsung di banyak tempat (`<x-badge :tone="$p->status->badgeTone()">` di `proposal/_daftar.blade.php:88`, `proposal/show.blade.php:16`, `inbox/review.blade.php:27`, dst). Status **InReview** (purple), **Rejected** (rose), **Disbursed** (indigo) — 3 status paling krusial dalam alur bisnis — tampil abu-abu netral, tidak terlihat beda dari Draft.

**Perbaikan**: tambah 3 tone yang hilang ke komponen bersama (root-cause di 1 tempat, otomatis benar untuk SEMUA domain yang memakai tone ini, bukan cuma Pengadaan — `ApprovalStatus`/`AttendanceStatus` di domain lain juga sudah mengembalikan `rose` untuk kasus serupa):

```php
$tones = [
    'brass' => 'bg-brand-50 text-brand-600',
    'green' => 'bg-success-50 text-success-700',
    'red' => 'bg-error-50 text-error-700',
    'amber' => 'bg-warning-50 text-warning-700',
    'blue' => 'bg-blue-100 text-blue-700',
    'slate' => 'bg-gray-100 text-gray-600',
    'purple' => 'bg-purple-100 text-purple-700',
    'rose' => 'bg-rose-100 text-rose-700',
    'indigo' => 'bg-indigo-100 text-indigo-700',
];
```

### 2.5 [CRITICAL] Dropdown Urgensi di Form Edit Proposal Pakai Value Salah

**Lokasi**: `resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php:88-92`

**Akar masalah**: enum `TingkatUrgensi` cuma punya value `biasa`, `mendesak`, `kritis`. Tapi `edit.blade.php` menulis:
```blade
<option value="biasa" ...>Biasa / Rutin</option>
<option value="mendesak" ...>Mendesak / Prioritas</option>
<option value="darurat" ...>Darurat (Segera)</option>
```
`create.blade.php` sudah benar pakai `kritis`. Dampak: (1) proposal `kritis` existing yang dibuka di form Edit tidak match opsi manapun → browser default ke opsi pertama ("Biasa") → kalau user submit tanpa sadar mengubah dropdown, urgensi **diam-diam turun dari Kritis jadi Biasa**; (2) kalau user eksplisit pilih "Darurat (Segera)", server menolak (`new Enum(TingkatUrgensi::class)` tidak kenal `'darurat'`) dengan error validasi yang membingungkan karena opsi itu ADA di dropdown.

**Perbaikan**: perbaiki value jadi `kritis`, samakan teks dengan `create.blade.php`:
```blade
<option value="biasa" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'biasa' ? 'selected' : '' }}>Biasa / Rutin</option>
<option value="mendesak" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'mendesak' ? 'selected' : '' }}>Mendesak</option>
<option value="kritis" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'kritis' ? 'selected' : '' }}>Kritis / Darurat</option>
```
(Teks label disamakan dengan filter di `index.blade.php:141` — "Kritis / Darurat" — supaya konsisten satu istilah di seluruh modul; lihat juga §2.10.)

### 2.6 [HIGH] Halaman Audit LPJ Salah Menampilkan "Sudah Diverifikasi" untuk LPJ yang Ditolak

**Lokasi**: `resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php:115-142`

**Akar masalah**: `VerifyLpjAction::execute()` (`app/Domains/Pengadaan/Actions/VerifyLpjAction.php:21-24`) mengisi `verified_at`/`verified_by_user_id` SEBELUM percabangan approve/reject — jadi field ini terisi walau LPJ ditolak (`RevisionRequired`). View cuma punya 2 cabang: `@if ($lpj->status_lpj === StatusLpj::Submitted)` (form keputusan) / `@else` (kartu hijau "LPJ ini telah selesai diverifikasi"). LPJ berstatus `RevisionRequired` jatuh ke `@else` — auditor yang minta revisi lalu membuka lagi halaman itu melihat pesan SUKSES hijau, padahal LPJ-nya ditolak dan menunggu perbaikan sekolah.

**Perbaikan**: pecah jadi 3 cabang eksplisit:

```blade
@if ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Submitted)
    {{-- form keputusan verifikasi, TIDAK BERUBAH dari kode existing --}}
@elseif ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::RevisionRequired)
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 flex items-start gap-2">
        <x-icon name="assignment_late" class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
        <div class="space-y-1">
            <p class="font-bold">LPJ ini diminta perbaikan, menunggu unggah ulang dari sekolah.</p>
            @if ($lpj->catatan_verifikasi)
                <p>Catatan Anda: <b>{{ $lpj->catatan_verifikasi }}</b></p>
            @endif
            <p class="text-amber-700">Diproses oleh <b>{{ $lpj->verifiedBy->name ?? 'Auditor Yayasan' }}</b> pada {{ $lpj->verified_at?->translatedFormat('d F Y H:i') }}.</p>
        </div>
    </div>
@else
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 text-xs text-emerald-900 flex items-center gap-2">
        <x-icon name="verified" class="h-5 w-5 text-emerald-600" />
        <span>LPJ ini telah selesai diverifikasi oleh <b>{{ $lpj->verifiedBy->name ?? 'Auditor Yayasan' }}</b> pada {{ $lpj->verified_at?->translatedFormat('d F Y H:i') }}.</span>
    </div>
@endif
```

### 2.7 [MEDIUM] LPJ RevisionRequired — Sekolah Tidak Pernah Tahu Alasan Penolakan & Wajib Upload Ulang Semua File

**Lokasi**: `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php` (tidak ada banner LPJ-level), `resources/views/portals/lembaga/pengadaan/lpj/create.blade.php` (tidak prefill, semua file wajib), `app/Http/Requests/Pengadaan/StoreLpjRequest.php` (foto wajib mutlak), `app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php::store()`

**Akar masalah**: mirip pola "RevisionRequired buntu" yang ditemukan di modul SDM sesi ini, tapi lebih parah karena kehilangan draft data:
1. `catatan_verifikasi` (alasan penolakan auditor) tidak pernah ditampilkan ke sekolah di manapun.
2. Tombol "Unggah LPJ Belanja" di `proposal/show.blade.php:53-56` identik baik untuk submit pertama kali maupun resubmit setelah ditolak — tidak ada indikasi ini adalah perbaikan.
3. `lpj/create.blade.php` selalu inisialisasi `harga_satuan_riil` dari `$proposal->items` (`estimasi_harga_satuan`), TIDAK PERNAH dari `$proposal->lpj->items` (realisasi yang sudah diisi sebelumnya) — data yang sudah diisi sekolah hilang begitu form dibuka ulang.
4. `StoreLpjRequest::rules()` mewajibkan `foto_nota`/`foto_fisik` `required` untuk SEMUA item setiap kali submit — sekolah harus upload ulang SEMUA foto nota & fisik barang dari nol, termasuk item yang sama sekali tidak jadi masalah bagi auditor.

**Perbaikan — 4 bagian, saling terkait, HARUS dikerjakan bersamaan:**

**(a) Banner peringatan + catatan di `proposal/show.blade.php`**, tambahkan SETELAH blok "Revision Callout Banner" existing (baris 98), SEBELUM "Workflow Step Action Banner":

```blade
{{-- LPJ Revision Callout Banner --}}
@if ($proposal->lpj && $proposal->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::RevisionRequired)
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                    <x-icon name="assignment_late" class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h2 class="font-display text-sm font-bold text-amber-900">Perhatian: LPJ Ini Memerlukan Perbaikan</h2>
                    <p class="text-xs text-amber-800 leading-relaxed">
                        Catatan Auditor Yayasan: <b>{{ $proposal->lpj->catatan_verifikasi ?? 'Silakan periksa kembali nota dan foto fisik barang sesuai instruksi auditor.' }}</b>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif
```

Ubah tombol di baris 53-56 supaya labelnya beda untuk kasus resubmit:
```blade
@if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed && auth()->user()->can('pengadaan.lpj.submit'))
    <x-link-button href="{{ route('admin.pengadaan.lpj.create', $proposal) }}">
        <x-icon name="receipt_long" class="h-4 w-4 mr-1" />
        {{ $proposal->lpj && $proposal->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::RevisionRequired ? 'Perbaiki & Kirim Ulang LPJ' : 'Unggah LPJ Belanja' }}
    </x-link-button>
@endif
```

**(b) Validasi kondisional di `StoreLpjRequest`** — foto wajib HANYA kalau tidak ada file lama untuk item itu:

```php
public function rules(): array
{
    return [
        'items' => ['required', 'array', 'min:1'],
        'items.*.pengajuan_item_id' => ['required', 'exists:pengajuan_pengadaan_item,id'],
        'items.*.harga_satuan_riil' => ['required', 'numeric', 'min:0'],
        'items.*.total_riil' => ['required', 'numeric', 'min:0'],
        'items.*.foto_nota' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        'items.*.foto_fisik' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        'bukti_kembali_sisa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
    ];
}

public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $proposal = $this->route('proposal');
        if (! $proposal) {
            return;
        }

        $existingItems = $proposal->lpj?->items->keyBy('pengajuan_item_id') ?? collect();
        foreach ($this->input('items', []) as $idx => $item) {
            $existing = $existingItems->get($item['pengajuan_item_id'] ?? null);

            if (! $this->hasFile("items.{$idx}.foto_nota") && ! $existing?->foto_nota_path) {
                $validator->errors()->add("items.{$idx}.foto_nota", 'Scan nota/faktur pembelian untuk setiap item barang wajib diunggah.');
            }
            if (! $this->hasFile("items.{$idx}.foto_fisik") && ! $existing?->foto_fisik_barang_path) {
                $validator->errors()->add("items.{$idx}.foto_fisik", 'Foto fisik barang saat tiba di sekolah wajib diunggah untuk setiap item.');
            }
        }

        $nominalPencairan = (float) $proposal->nominal_pencairan;
        $items = $this->input('items', []);
        $totalRiil = 0;

        foreach ($items as $item) {
            $totalRiil += (float) ($item['total_riil'] ?? 0);
        }

        $sisaKas = $nominalPencairan - $totalRiil;
        if ($sisaKas > 0 && ! $this->hasFile('bukti_kembali_sisa')) {
            $validator->errors()->add(
                'bukti_kembali_sisa',
                'Terdapat sisa dana kas sebesar Rp ' . number_format($sisaKas, 0, ',', '.') . '. Bukti transfer/setoran pengembalian sisa kas ke Yayasan wajib dilampirkan.'
            );
        }
    });
}
```

(Method `messages()` dan `toDTO()` TIDAK berubah dari kode existing.)

**(c) Controller `LpjPengadaanController::store()`** — pertahankan path lama kalau tidak ada file baru diunggah:

```php
public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
{
    abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);

    $existingItems = $proposal->lpj?->items->keyBy('pengajuan_item_id') ?? collect();
    $items = $request->validated()['items'];
    $processedItems = [];

    foreach ($items as $idx => $item) {
        $existing = $existingItems->get($item['pengajuan_item_id']);
        $fotoNotaPath = $existing?->foto_nota_path;
        $fotoFisikPath = $existing?->foto_fisik_barang_path;

        if ($request->hasFile("items.{$idx}.foto_nota")) {
            $fotoNotaPath = $request->file("items.{$idx}.foto_nota")->store('pengadaan/nota', 'public');
        }
        if ($request->hasFile("items.{$idx}.foto_fisik")) {
            $fotoFisikPath = $request->file("items.{$idx}.foto_fisik")->store('pengadaan/fisik', 'public');
        }

        $processedItems[] = [
            'pengajuan_item_id' => $item['pengajuan_item_id'],
            'harga_satuan_riil' => $item['harga_satuan_riil'],
            'total_riil' => $item['total_riil'],
            'foto_nota_path' => $fotoNotaPath,
            'foto_fisik_barang_path' => $fotoFisikPath,
        ];
    }

    $buktiKembaliPath = null;
    if ($request->hasFile('bukti_kembali_sisa')) {
        $buktiKembaliPath = $request->file('bukti_kembali_sisa')->store('pengadaan/sisa-kas', 'public');
    }

    $dto = $request->toDTO($buktiKembaliPath, $processedItems);
    $this->submitLpjAction->execute($proposal, $dto);

    return redirect()->route('admin.pengadaan.proposal.show', $proposal)
        ->with('success', 'Laporan Pertanggungjawaban (LPJ) berhasil dikirim untuk diaudit oleh Yayasan.');
}
```

Method `create()` juga load relasi `lpj.items` supaya tersedia untuk prefill di view (`$proposal->load(['items' => ..., 'lpj.items'])` — cek kode existing baris 36, TAMBAHKAN `'lpj.items'` ke array eager-load yang sudah ada, jangan ganti seluruhnya).

**(d) View `lpj/create.blade.php`** — prefill dari `$proposal->lpj->items` (bukan `$proposal->items`) kalau ada, hapus `required` HTML dari file input, tampilkan indikator file lama:

Bagian `<script>` (baris 182-207), ubah inisialisasi `items` supaya membaca nilai lama:
```php
items: [
    @foreach ($proposal->items as $idx => $item)
    @php $lpjItem = $proposal->lpj?->items->firstWhere('pengajuan_item_id', $item->id); @endphp
    {
        id: {{ $item->id }},
        nama: @js($item->nama_barang),
        qty: {{ $item->qty }},
        satuan: @js($item->satuan),
        harga_satuan_riil: {{ (float) ($lpjItem->harga_satuan_riil ?? $item->estimasi_harga_satuan) }},
        fotoNotaUrl: @js($lpjItem?->foto_nota_path ? \Illuminate\Support\Facades\Storage::url($lpjItem->foto_nota_path) : null),
        fotoFisikUrl: @js($lpjItem?->foto_fisik_barang_path ? \Illuminate\Support\Facades\Storage::url($lpjItem->foto_fisik_barang_path) : null),
        get total_riil() { return this.qty * (Number(this.harga_satuan_riil) || 0); }
    },
    @endforeach
],
```

Bagian file input Scan Nota (baris 87-97), hapus `required`, tambah indikator kalau sudah ada file:
```blade
<div>
    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
        Scan Nota / Faktur <span class="text-error-600" x-show="!item.fotoNotaUrl">*</span>
    </label>
    <template x-if="item.fotoNotaUrl">
        <button type="button" @click="$store.imagePreview.buka(item.fotoNotaUrl, 'Nota Tersimpan')" class="mb-1 inline-flex items-center gap-1 text-[10px] font-medium text-brand-600 hover:text-brand-800">
            <x-icon name="visibility" class="h-3 w-3" /> Lihat nota tersimpan (upload baru untuk mengganti)
        </button>
    </template>
    <input
        type="file"
        :name="`items[${index}][foto_nota]`"
        accept="image/jpeg,image/png,image/jpg,application/pdf"
        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
    >
    <p class="text-[10px] text-gray-400 mt-0.5">JPG, PNG, PDF (Maks 5MB)</p>
</div>
```
Perubahan identik (nama field `foto_fisik`, variabel `fotoFisikUrl`, label "Foto Fisik Barang Tiba") untuk blok Foto Fisik Barang (baris 99-110).

Banner informasi di baris 19-33, tambah 1 kalimat kondisional:
```blade
<p class="text-xs text-indigo-800 max-w-sm text-right leading-relaxed">
    Input nominal nota riil per barang dan lampirkan <b>scan nota/faktur</b> serta <b>foto fisik barang</b> saat tiba di sekolah.
    @if ($proposal->lpj)
        Item yang tidak diunggah ulang akan tetap memakai berkas sebelumnya.
    @endif
</p>
```

### 2.8 [MEDIUM] Catatan Wajib Diisi Saat Menolak/Minta Revisi (Proposal & LPJ)

**Lokasi**: `app/Http/Requests/Pengadaan/ProcessApprovalRequest.php:20`, `app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php::verify()` (validasi inline baris 71-74)

**Akar masalah**: `notes`/`catatan_verifikasi` selalu `nullable` — approver/auditor bisa menolak atau minta revisi tanpa keterangan sama sekali, padahal itu satu-satunya penjelasan yang diterima pihak sekolah (apalagi setelah §2.7 catatan LPJ akhirnya ditampilkan — kalau boleh kosong, perbaikan §2.7 jadi kurang berguna).

**Perbaikan**:

`ProcessApprovalRequest::rules()`, ubah `notes`:
```php
'notes' => ['nullable', 'string', 'max:1000', 'required_if:action,REJECT,REQUEST_REVISION'],
```
Tambah pesan di `messages()`:
```php
'notes.required_if' => 'Catatan wajib diisi saat menolak atau meminta revisi, supaya sekolah tahu apa yang perlu diperbaiki.',
```

`AuditLpjController::verify()`, ubah validasi inline:
```php
$request->validate([
    'is_approved' => ['required', 'boolean'],
    'catatan_verifikasi' => ['nullable', 'string', 'max:1000', 'required_if:is_approved,0'],
], [
    'catatan_verifikasi.required_if' => 'Catatan wajib diisi saat meminta perbaikan LPJ, supaya sekolah tahu apa yang perlu diperbaiki.',
]);
```

### 2.9 [MEDIUM] Form Review Item Tidak Menampilkan Histori Keputusan Sebelumnya

**Lokasi**: `resources/views/portals/yayasan/pengadaan/inbox/review.blade.php:53-63`

**Akar masalah**: state awal Alpine `decisions` SELALU `status: 'approved'` untuk semua item, tidak membaca `item->status_item`/`item->catatan_reviewer` yang sudah ada. Untuk proposal yang sudah melalui ≥1 putaran revisi, approver harus mengingat sendiri item mana yang bermasalah sebelumnya.

**Perbaikan**:
```blade
x-data="{
    action: 'APPROVE',
    decisions: {
        @foreach ($proposal->items as $item)
            {{ $item->id }}: {
                status: '{{ $item->status_item->value === 'rejected' ? 'rejected' : 'approved' }}',
                catatan: @js($item->catatan_reviewer ?? '')
            },
        @endforeach
    }
}"
```
(Asumsi nama field `status_item`/`catatan_reviewer` pada model `PengajuanPengadaanItem` — WAJIB dikonfirmasi implementer terhadap skema kolom aktual sebelum dipakai, lihat §8 putaran self-review.)

### 2.10 [LOW-MEDIUM] Route `destroy` Proposal Terdaftar Tapi Tidak Ada Method-nya

**Lokasi**: `routes/admin/pengadaan.php:8`

**Akar masalah**: `Route::resource('proposal', PengajuanPengadaanController::class)` mendaftarkan SEMUA 7 route resource termasuk `destroy`, tapi controller tidak punya method `destroy()`. Mengakses `DELETE admin/pengadaan/proposal/{proposal}` akan error runtime, bukan 404 rapi.

**Perbaikan**: proposal tidak pernah didesain bisa dihapus (siklus hidupnya selalu berakhir di Completed/Rejected/Cancelled, bukan delete) — kecualikan `destroy` dari resource route, JANGAN tambah method `destroy()` baru (itu penambahan kapabilitas di luar scope):
```php
Route::resource('proposal', \App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class)->except(['destroy']);
```

### 2.11 [LOW] Wording Tidak Konsisten

**a. Istilah "Proposal"/"Usulan"/"Pengajuan" tercampur** — standardisasi TEKS TAMPILAN (bukan nama route/variable/model, itu di luar scope karena berisiko lebih besar) jadi "Pengajuan" sebagai istilah utama, "usulan" boleh dipakai sebagai sinonim kasual sesekali tapi TIDAK bercampur bebas dalam 1 halaman:
- `proposal/index.blade.php:15` — ganti *"Kelola proposal usulan belanja..."* jadi *"Kelola pengajuan belanja fasilitas..."*
- `proposal/index.blade.php:94` — ganti *"Filter Data Usulan"* jadi *"Filter Data Pengajuan"*
- `proposal/create.blade.php:11` (judul) — ganti *"Buat Usulan Pengadaan Sarpras"* jadi *"Buat Pengajuan Pengadaan Sarpras"*, breadcrumb baris 15 ganti *"Buat Usulan"* jadi *"Buat Pengajuan"* juga.

**b. "Isi LPJ" vs "Unggah LPJ" untuk aksi yang sama** — samakan jadi **"Unggah LPJ Belanja"** di semua tempat (istilah ini sudah dipakai `proposal/show.blade.php:55`, lebih deskriptif dari "Isi"):
- `proposal/_daftar.blade.php:39` — ganti *"Isi LPJ Belanja"* jadi *"Unggah LPJ Belanja"*.

### 2.12 [LOW] Konvensi `<x-select>` Dilanggar untuk Dropdown Statis Tanpa Alasan Binding

**Lokasi**: `proposal/create.blade.php:41` (field `tingkat_urgensi`), `proposal/edit.blade.php:88` (field sama), `proposal/index.blade.php:122,138` (filter status & urgensi)

**Akar masalah**: keempatnya `<select>` native padahal bukan di dalam `x-for` item loop (tidak ada konflik binding Alpine `:name` dinamis yang jadi alasan valid dipakai native di tempat lain modul ini — lihat item di dalam `x-for` yang memang benar pakai native).

**Perbaikan** — ganti ke `<x-select>`, contoh untuk `create.blade.php:41` (pola sama untuk 3 lokasi lain, sesuaikan atribut `x-model`/`name` masing-masing tanpa mengubah value/opsi):
```blade
<x-select name="tingkat_urgensi" required>
    <option value="">-- Pilih Tingkat Urgensi --</option>
    <option value="biasa">Biasa / Rutin</option>
    <option value="mendesak">Mendesak</option>
    <option value="kritis">Kritis / Darurat</option>
</x-select>
```
Untuk `index.blade.php:122,138` (pakai `x-model`, bukan `name` polos — TIDAK ada prefix `:` di `x-model` jadi AMAN dipakai di `<x-select>`, konsisten dengan pola established di modul lain sesi ini):
```blade
<x-select x-model="filters.status" @change="muatUlangDaftar()">
    {{-- opsi tidak berubah --}}
</x-select>
```

### 2.13 [LOW] Label Form Tidak Terhubung ke Input (`for`/`id` Mismatch)

**Lokasi**: `proposal/index.blade.php:108`, `inbox/index.blade.php:77`, `disbursement/index.blade.php:85`, `audit-lpj/index.blade.php:86`

**Akar masalah**: `<label for="search">` tanpa `<input id="search">` pasangannya — pembaca layar tidak bisa mengasosiasikan label dengan field.

**Perbaikan**: tambah `id="search"` ke `<input>` pencarian di keempat file (elemen `<input type="text" x-model="filters.search" ...>` yang langsung ada di bawah label `for="search"` masing-masing file).

### 2.14 [LOW] Empty-State Tidak Konsisten Sesama Modul Pengadaan

**Lokasi**: `disbursement/_daftar.blade.php:56-59`, `audit-lpj/_daftar.blade.php:59-62` vs `proposal/_daftar.blade.php:93-102`, `inbox/_daftar.blade.php:50-59`

**Akar masalah**: dua yang pertama cuma teks polos, dua yang terakhir sudah punya ikon bulat + judul + subjudul deskriptif.

**Perbaikan**: samakan `disbursement/_daftar.blade.php` dan `audit-lpj/_daftar.blade.php` ke pola ikon+judul+subjudul milik `inbox/_daftar.blade.php` — implementer WAJIB membaca struktur HTML persis dari `inbox/_daftar.blade.php:50-59` sebagai referensi dan menyesuaikan teks judul/subjudul ke konteks masing-masing halaman (mis. "Belum ada pencairan dana" untuk disbursement, "Belum ada LPJ untuk diaudit" untuk audit-lpj) — bukan menyalin teks Inbox apa adanya.

---

## 3. Item Sengaja Tidak Masuk Scope

- **`RecordDisbursementAction` tanpa validasi nominal pencairan vs estimasi disetujui** — audit menemukan bendahara yayasan bisa mencairkan nominal berapa pun tanpa validasi silang terhadap `total_estimasi` yang disetujui workflow. Kemungkinan ini disengaja (fleksibilitas kas riil bisa beda dari estimasi awal karena negosiasi harga/dsb) — butuh KEPUTUSAN PRODUK apakah harus dibatasi atau tetap bebas, bukan bug teknis yang jelas arah perbaikannya. Tidak masuk spec ini.
- **Preview thumbnail sebelum submit file LPJ** — peningkatan UX (nice-to-have), bukan bug/defect. YAGNI untuk spec ini.
- **Teks tambahan opsi dropdown urgensi berbeda dari label resmi enum** ("Mendesak (Dibutuhkan Segera)" di `create.blade.php:43` vs label singkat di tempat lain) — SUDAH tercakup implisit oleh perbaikan §2.12 (konversi ke `<x-select>` dengan value/label yang diseragamkan persis seperti §2.5), tidak perlu perbaikan terpisah.
- **Refactor `lpj/create.blade.php` inline `<script>` ke `resources/js/*.js` terdaftar** — file ini SUDAH memakai pola lama (inline script), melanggar `.ai/rules/js.md`, TAPI itu bukan bug baru yang ditemukan audit ini dan mengubahnya menambah risiko tak perlu ke perbaikan §2.7 yang sudah menyentuh file yang sama. Dibiarkan apa adanya (pola lama), dicatat sebagai utang teknis terpisah kalau file ini disentuh lagi di masa depan untuk alasan lain.

---

## 4. Dampak & Kompatibilitas

- §2.1, §2.2 murni menambah guard (tidak pernah ada sebelumnya) — TIDAK ADA perilaku sah yang berubah, hanya menutup akses yang seharusnya tidak pernah diizinkan.
- §2.2 (fail-closed `activeYayasanId()`) BERPOTENSI membuat akun `yayasan_id = null` yang sudah TERLANJUR ada di database (kalau ada) langsung tidak bisa akses fitur Pengadaan/Rekap Aset Yayasan sama sekali (403 dengan pesan jelas) — ini PERUBAHAN PERILAKU YANG DISENGAJA (menutup celah), bukan regresi. Kalau ternyata ada akun begini di produksi, mereka perlu diperbaiki datanya (isi `yayasan_id`) oleh platform admin, BUKAN alasan untuk membatalkan fix ini.
- §2.7 mengubah validasi file jadi kondisional — proposal yang BARU PERTAMA KALI submit LPJ (belum py `$proposal->lpj` sama sekali) TETAP wajib upload semua foto (karena `$existing` akan `null` untuk semua item), jadi behavior submit PERTAMA tidak berubah sama sekali. HANYA resubmit setelah RevisionRequired yang jadi lebih longgar.
- §2.4 (tambah tone ke `<x-badge>`) murni ADDITIVE ke array `$tones` — tidak ada tone existing yang diubah/dihapus, tidak mungkin merusak tampilan domain lain yang sudah pakai 6 tone lama.
- Semua perbaikan §2.10-§2.14 murni kosmetik/wording/a11y, tidak mengubah logika bisnis apa pun.

---

## 5. Pengujian yang Dibutuhkan

- **§2.1**: test baru — user lembaga A mencoba akses `lpj.create`/`lpj.store` untuk proposal lembaga B → `assertNotFound()` (404). Plus regresi: user lembaga pemilik proposal tetap bisa akses normal.
- **§2.2**: test baru — user dengan `yayasan_id` null (buat manual di test, JANGAN pakai factory yang otomatis isi yayasan_id) mengakses `inbox.index`/`disbursement.index`/`audit-lpj.index`/rekap aset yayasan → `assertForbidden()` (403) dengan pesan yang mengandung "belum terhubung ke yayasan". Plus regresi: user dengan `yayasan_id` valid tetap bisa akses semua endpoint itu seperti biasa (test existing untuk masing-masing controller harus tetap lolos).
- **§2.3**: TIDAK ADA automated test untuk perilaku JS Alpine murni (konsisten dengan pola sesi ini) — verifikasi manual dev-server: ketik di kolom cari `proposal/index` dan `inbox/index`, pastikan tabel ter-refresh tanpa toast error.
- **§2.4**: TIDAK ADA automated test untuk warna badge (murni visual) — verifikasi manual: buka daftar proposal yang punya status Rejected/InReview/Disbursed, pastikan badge tidak abu-abu.
- **§2.5**: test baru — edit proposal berstatus `Kritis`, assert opsi `value="kritis"` ter-render dengan atribut `selected`. Plus test submit form edit dengan `tingkat_urgensi=kritis` berhasil (tidak error validasi).
- **§2.6**: test baru — `VerifyLpjAction` dengan `isApproved=false`, lalu hit route `audit-lpj.show`, assert response TIDAK mengandung teks "telah selesai diverifikasi", assert MENGANDUNG teks catatan verifikasi.
- **§2.7**: test baru (paling penting) — submit LPJ pertama kali (semua wajib), lalu `VerifyLpjAction` reject, lalu submit LPJ KEDUA KALI hanya mengubah 1 dari 2 item TANPA mengirim file untuk item yang tidak diubah → assert sukses (bukan validation error), assert `foto_nota_path`/`foto_fisik_barang_path` item yang tidak diubah TETAP sama dengan submission pertama (tidak ter-null-kan). Plus regresi: submit LPJ pertama kali TANPA file sama sekali tetap gagal validasi seperti biasa (behavior lama untuk kasus ini tidak berubah).
- **§2.8**: test baru — `ProcessApprovalRequest`/`decision()` dengan `action=REJECT` tanpa `notes` → validation error; dengan `notes` terisi → sukses. Sama untuk `AuditLpjController::verify()` dengan `is_approved=0`.
- **§2.9-§2.14**: murni Blade/JS tanpa logika bisnis backend — verifikasi manual dev-server, tidak perlu automated test baru (konsisten pola sesi ini untuk perubahan murni tampilan).
- **Regresi wajib**: seluruh `tests/Feature/Pengadaan` dan `tests/Unit/Domains/Pengadaan` dijalankan ulang setelah SEMUA task selesai, plus full suite proyek di task terakhir (3 kegagalan pre-existing yang sudah dikenal harus tetap satu-satunya yang muncul).

---

## 6. Struktur Task yang Disarankan (untuk fase plan nanti)

1. **Task 1**: §2.1 (LPJ IDOR) — independen, kritis, TDD murni controller layer.
2. **Task 2**: §2.2 (TenantContext fail-closed + 4 controller dead-code cleanup) — independen, kritis, saling terkait erat (harus 1 task, lihat "dampak berantai" di §2.2).
3. **Task 3**: §2.3 (filter AJAX 2 file) — independen, trivial.
4. **Task 4**: §2.4 (badge tone) — independen, trivial, additive.
5. **Task 5**: §2.5 (urgensi value salah di edit) — independen, trivial.
6. **Task 6**: §2.6 (pesan salah audit LPJ) — independen.
7. **Task 7**: §2.7 (LPJ RevisionRequired lengkap: banner+validasi+controller+view) — PALING BESAR, 4 sub-bagian saling terkait, HARUS 1 task (tidak bisa dipecah tanpa membuat state antara yang rusak — validasi kondisional di (b) butuh perubahan controller di (c) untuk benar-benar berfungsi end-to-end).
8. **Task 8**: §2.8 (notes wajib saat reject/revisi, 2 request/controller) — independen.
9. **Task 9**: §2.9 (item decision default) — independen, TAPI implementer WAJIB verifikasi nama kolom `status_item`/`catatan_reviewer` ke skema aktual sebelum implementasi (lihat catatan di §2.9).
10. **Task 10**: §2.10 (route destroy) — independen, trivial.
11. **Task 11**: §2.11+§2.12+§2.13+§2.14 (wording, `<x-select>`, a11y, empty-state) — digabung jadi 1 task "polish" karena semuanya kosmetik murni, tidak ada logika, efisien dikerjakan sekaligus per file yang sama kadang tumpang tindih (mis. `proposal/index.blade.php` disentuh §2.3 DAN §2.11 DAN §2.12 DAN §2.13 — HARUS dikoordinasi supaya tidak saling timpa; plan nanti WAJIB urutkan Task 3 sebelum Task 11 untuk file yang sama).
12. **Task 12**: Penutup — full regression sweep + Pint + full suite proyek.

Task 1,2,4,5,6,8,9,10 saling independen. Task 3 harus SELESAI sebelum Task 11 (karena keduanya menyentuh `proposal/index.blade.php` dan `inbox/index.blade.php`). Task 7 independen dari semua task lain. Task 12 wajib terakhir.

---

## 7. Self-Review — Putaran 1 (standar: placeholder, konsistensi, cakupan)

- **Placeholder scan**: tidak ada "TBD"/"TODO" — semua kode di §2 lengkap.
- **Konsistensi**: §2.7(c) dan §2.7(d) sama-sama bergantung pada relasi `lpj.items` yang di-eager-load di §2.7(c) method `create()` — sudah dicatat eksplisit di §2.7(c) sebagai instruksi tambah, bukan ganti eager-load existing.
- **Cakupan vs audit**: 14 dari 14 temuan konkret (Critical+High+Medium+Low yang punya arah perbaikan jelas) masuk §2. 3 item yang butuh keputusan produk/nice-to-have/berisiko tak perlu masuk §3 dengan alasan eksplisit. Tidak ada temuan hilang tanpa penjelasan.

## 8. Self-Review — Putaran 2 (verifikasi empiris terhadap skema/kode aktual)

- **§2.9 field `status_item`/`catatan_reviewer`** — DIVERIFIKASI: `PengajuanPengadaanItem` memang punya kolom ini (dipakai `inbox/review.blade.php` submit form `item_decisions[{id}][status]`/`[catatan]`, dan `ProcessProposalApprovalAction` menyimpan hasilnya ke kolom ini — dikonfirmasi lewat pembacaan langsung form yang sudah mengirim data ini ke server, bukan asumsi). Catatan kehati-hatian di §2.9 tetap dipertahankan sebagai pengingat implementer, bukan karena keraguan nyata.
- **§2.2 empat file "dampak berantai"** — dikonfirmasi keempatnya (`AuditLpjController`, `DisbursementPengadaanController`, `ApprovalPengadaanController`, `RekapAsetGlobalController`) benar memakai pola `?? Yayasan::first()?->id` identik lewat pembacaan langsung baris yang disebutkan.
- **§2.4 tone hilang** — dikonfirmasi PERSIS 3 tone (`purple`, `rose`, `indigo`) yang dipakai Pengadaan tapi tidak ada di `$tones` — bukan tebakan, hasil diff manual antara semua `badgeTone()` return value di 4 enum Pengadaan vs array `$tones` di komponen.
- **§2.5** — dikonfirmasi `create.blade.php` SUDAH benar (`value="kritis"`), jadi perbaikan HANYA perlu di `edit.blade.php`, tidak perlu sentuh `create.blade.php` untuk value (hanya §2.11 teks label kalau ada perbedaan minor, sudah dicakup §3 sebagai "implisit lewat §2.12").

## 9. Self-Review — Putaran 3 (dependency antar-perbaikan & urutan risiko)

- **§2.1 dan §2.7 sama-sama menyentuh `LpjPengadaanController`** — dikonfirmasi tidak overlap baris: §2.1 nambah 1 baris `abort_unless` di AWAL `create()`/`store()`, §2.7(c) mengubah BADAN method `store()` sepenuhnya. Plan nanti WAJIB urutkan §2.1 (Task 1) SEBELUM §2.7 (Task 7) supaya guard keamanan tidak hilang kalau kode di-rewrite ulang tanpa hati-hati — TAMBAHKAN catatan ini ke plan sebagai instruksi eksplisit, bukan diasumsikan otomatis benar.
- **§2.3 dan §2.11/§2.12/§2.13 overlap file** — sudah ditangani di §6 (Task 3 sebelum Task 11), dikonfirmasi ulang di sini tidak ada overlap lain yang belum ditangkap (`inbox/index.blade.php` juga overlap Task 3+Task 11 dengan pola sama).
- **§2.2 perubahan fail-closed BERISIKO PALING TINGGI secara operasional** (bisa mengunci akses akun existing yang datanya bermasalah) — sudah dicatat di §4 sebagai perubahan perilaku yang disengaja, TAPI implementer WAJIB menjalankan query manual `User::whereNull('yayasan_id')->whereHas('roles', fn($q) => $q->where('scope_level', 'yayasan'))->count()` di database dev/staging SEBELUM deploy ke produksi, untuk tahu berapa banyak akun yang akan terdampak — ditambahkan sebagai instruksi ke plan/kickoff, BUKAN blocker untuk implementasi (audit tetap harus diperbaiki, tapi operator perlu tahu skalanya).

## 10. Self-Review — Putaran 4 (baca ulang dengan mata segar)

- Pesan error §2.2 ("Akun Anda belum terhubung ke yayasan manapun. Hubungi Super Admin Platform...") sudah actionable — menyebutkan APA masalahnya dan KE SIAPA harus menghubungi, konsisten prinsip proyek.
- Dicek ulang §2.5: teks label "Kritis / Darurat" dipilih supaya SAMA PERSIS dengan filter `index.blade.php:142` yang sudah ada sebelumnya (bukan istilah baru) — konsistensi lintas halaman terjaga.
- Dicek ulang §2.7(d): variabel Alpine baru `fotoNotaUrl`/`fotoFisikUrl` TIDAK bentrok dengan variabel existing (`id`, `nama`, `qty`, `satuan`, `harga_satuan_riil`, `total_riil` getter) — aman ditambahkan sebagai field baru di objek yang sama.
- Ditemukan saat baca ulang: draft awal §2.10 sempat menyarankan "tambah method destroy() kosong yang redirect balik" — SALAH, itu bertentangan dengan prinsip "jangan bangun kapabilitas baru di luar yang diminta" dan Global Constraint proyek soal tidak menambah fitur tak perlu. Diperbaiki jadi `->except(['destroy'])` sebelum spec ini final (lihat versi final di §2.10 di atas, sudah benar).
