# Spec: Audit & Perbaikan Persetujuan Rapor + Workflow Engine

- **Tanggal**: 2026-09-10
- **Cabang Git**: `rbac-v2`
- **Halaman utama**: `admin.rapor.persetujuan.*` (`PersetujuanController`)
- **Modul terdampak tidak langsung**: `app/Domains/Workflow/` (dipakai juga oleh Pengadaan dan SDM)

## 1. Latar Belakang

Audit menu Persetujuan Rapor dimulai dari controller dan view (7 temuan wording/scope/UX ringan), lalu diperdalam ke backend Actions atas permintaan user ("audit semua dong dari backend"). Penelusuran itu menemukan bug fungsional serius di **shared Workflow engine** (`ApproverResolverService`) yang salah menangani resolusi lembaga aktif untuk aktor berscope yayasan dalam mode agregat — bug ini terduplikasi persis di 2 Action Rapor, dan akar masalahnya memengaruhi Pengadaan dan SDM juga (3 domain berbagi 1 engine yang sama), meski kedua domain itu tidak punya kode duplikat lokal seperti Rapor.

## 2. Temuan & Perbaikan

### Item W (🔴 Tinggi) — Root cause: `ApproverResolverService::checkRoleApprover()` salah resolusi lembaga aktif

**File**: `app/Domains/Workflow/Services/ApproverResolverService.php`

**Kode saat ini** (baris 25-46):
```php
protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
{
    if (! $user->hasRole($step->approver_value)) {
        return false;
    }

    if ($step->scope_level === 'lembaga') {
        $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

        if ($targetLembagaId !== null) {
            $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
                ? session('active_lembaga_id')
                : $user->lembaga_id;

            if ($effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
                return false;
            }
        }
    }

    return true;
}
```

**Bug**: untuk aktor `widestScopeLevel() === 'yayasan'` yang sedang dalam **mode agregat** (belum memilih lembaga aktif via pengalih lembaga, `session('active_lembaga_id')` kosong), `$effectiveLembagaId` SELALU `null` → kondisi `$effectiveLembagaId === null` SELALU `true` → method SELALU `return false` untuk step manapun yang `scope_level === 'lembaga'`, **terlepas dari apakah pengajuan itu sah miliknya**.

**Siapa yang kena dampak nyata**: `yayasan_super_admin` TIDAK kena (ada bypass eksplisit di `canUserApprove()` baris 14-16, `if ($user->hasRole('yayasan_super_admin')) return true;`). Yang kena adalah user dengan kombinasi role **scope-carrier yayasan** (`pegawai_yayasan`, `scope_level: 'yayasan'`) + **role fungsional lembaga** (`kepala_sekolah`, `wakasek_kurikulum`, `admin_sdm` — semua `scope_level: 'lembaga'` di `RoleSeeder`). Kombinasi ini adalah pola organisasi yang memang didukung desain RBAC v2 (lihat komentar `User::functionalRoles()`), BUKAN skenario pinggiran. `User::widestScopeLevel()` (`app/Models/User.php:129-139`) mengembalikan `'yayasan'` untuk user seperti ini karena dia memang punya role dengan `scope_level: 'yayasan'`.

**Blast radius** (dari `database/seeders/WorkflowDefinitionSeeder.php`, step dengan `scope_level: 'lembaga'` + `ApproverType::Role`):
- Rapor: "Verifikasi Waka Kurikulum", "Persetujuan Akhir Kepala Sekolah"
- Pengadaan: "Verifikasi Internal Kepala Sekolah"
- SDM: "Verifikasi Kepala Sekolah", "Persetujuan Admin SDM"

Untuk user dengan kombinasi role di atas, SEMUA 5 step ini permanen terkunci di mode agregat — bug fungsional yang mengunci alur kerja institusi, bukan celah keamanan (arahnya over-restrictive, bukan bocor akses).

**Perbaikan**: ganti resolusi `$effectiveLembagaId` dengan pola yang memvalidasi `session('active_lembaga_id')` terhadap kepemilikan yayasan aktor, alih-alih mempercayainya mentah. Pola ini sudah dipakai di `App\Domains\Akademik\Support\ResolveLembagaScopeTrait::resolveActiveLembagaId()`, TAPI trait itu berada di namespace `App\Domains\Akademik` — **Workflow adalah engine generik yang dipakai 3 domain (Akademik/Pengadaan/SDM) dan TIDAK BOLEH bergantung pada namespace domain manapun** (pelanggaran layering). Maka logika yang sama ditulis ULANG secara mandiri di dalam `ApproverResolverService` (private method baru), bukan meng-import trait Akademik.

Kode pengganti:
```php
use App\Models\Lembaga;

protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
{
    if (! $user->hasRole($step->approver_value)) {
        return false;
    }

    if ($step->scope_level === 'lembaga') {
        $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

        if ($targetLembagaId !== null) {
            $effectiveLembagaId = $this->resolveEffectiveLembagaId($user);

            if ($effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
                return false;
            }
        }
    }

    return true;
}

private function resolveEffectiveLembagaId(User $user): ?int
{
    if ($user->widestScopeLevel() !== 'yayasan') {
        return $user->lembaga_id;
    }

    $lembagaId = session('active_lembaga_id');
    if ($lembagaId === null) {
        return null;
    }

    $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $user->yayasan_id)->exists();

    return $milikYayasan ? $lembagaId : null;
}
```

**Catatan penting**: perbaikan ini TIDAK menghilangkan blokir mode-agregat itu sendiri — seorang approver berscope yayasan MASIH HARUS memilih lembaga aktif via pengalih lembaga sebelum bisa approve step `scope_level: 'lembaga'` (ini benar secara bisnis: approval per-lembaga perlu konteks lembaga eksplisit, sama seperti pola "wajib switch lembaga dulu" yang sudah established di TP). Yang diperbaiki HANYA bug validasi — `session('active_lembaga_id')` yang stale/tidak sah untuk yayasan aktor sekarang benar-benar divalidasi (bukan dipercaya mentah), bukan sekadar `null`-check dangkal yang membuatnya SELALU gagal.

### Item X (🟡 Sedang) — Kode duplikat redundan di 2 Action Rapor

**File**: `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php` (baris 27-35) dan `ApprovePengajuanRaporAction.php` (baris 27-35)

Kedua Action ini punya blok pre-check manual yang PERSIS meniru bug yang sama (raw `session('active_lembaga_id')`, tanpa validasi kepemilikan yayasan) SEBELUM memanggil `ProcessApprovalAction` → `ApproverResolverService` (yang, setelah Item W diperbaiki, sudah melakukan pengecekan yang benar dan lebih lengkap). Blok ini sepenuhnya redundan dan ikut membawa bug yang sama.

**Perbaikan**: hapus blok berikut dari KEDUA file (baris 27-35 di masing-masing):
```php
$effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
    ? session('active_lembaga_id')
    : $user->lembaga_id;

if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
    throw ValidationException::withMessages([
        'approval' => 'Anda tidak berwenang memverifikasi pengajuan rapor lembaga lain.', // atau pesan approve
    ]);
}
```

Setelah dihapus, otorisasi kepemilikan-lembaga sepenuhnya didelegasikan ke `ProcessApprovalAction::execute()` → `ApproverResolverService::canUserApprove()`, yang sudah melempar `ValidationException` dengan pesan `'Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.'` bila gagal. Pesan generik ini dipakai untuk kedua arah (verify & approve) — cukup jelas karena konteks halaman (judul "Review Rapor", tombol Setujui/Tolak) sudah menjelaskan aksinya.

**PENTING — urutan pengerjaan**: Item X HANYA boleh dikerjakan SETELAH Item W selesai dan teruji, karena Item X menghapus lapis proteksi yang (walau buggy) saat ini menjadi satu-satunya guard yang lolos test existing. Menghapus Item X sebelum Item W membuat Rapor kehilangan proteksi lembaga sama sekali untuk sementara.

### Item A (🔴 Tinggi) — Filter manual redundan & tidak tervalidasi di tab "riwayat"

**File**: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`, method `index()` (baris 43-54)

**Kode saat ini**:
```php
if ($tab === 'riwayat') {
    $effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
        ? session('active_lembaga_id')
        : $request->user()->lembaga_id;

    $query = PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])
        ->when($effectiveLembagaId, fn ($q) => $q->where('lembaga_id', $effectiveLembagaId))
        ->with(['kelas.tahunAjaran', 'semester'])
        ->when($request->search, function ($q, $search) {
            $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
        })
        ->latest();
} else {
    $statusYangDicari = $this->statusUntukAktor($request);
    $query = PengajuanRapor::where('status', $statusYangDicari)
        ->with(['kelas.tahunAjaran', 'semester'])
        ->when($request->search, function ($q, $search) {
            $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
        })
        ->latest();
}
```

**Bug**: `PengajuanRapor` adalah model `BelongsToTenant` — `TenantScope` SUDAH otomatis membatasi query sesuai lembaga aktor (dan untuk yayasan-scope, TenantScope sendiri memvalidasi `session('active_lembaga_id')` terhadap kepemilikan yayasan sebelum mempercayainya). Filter manual `$effectiveLembagaId` di cabang "riwayat" REDUNDAN dengan itu DAN pakai raw session yang tidak tervalidasi — kalau session stale (lembaga aktif tidak lagi valid untuk yayasan aktor), `$effectiveLembagaId` bisa jadi nilai yang salah, menghasilkan daftar riwayat KOSONG padahal seharusnya menampilkan sesuatu (TenantScope sendiri sudah cukup dan benar). Cabang "else" (tab "menunggu") TIDAK punya filter manual ini sama sekali — sudah benar.

**Perbaikan**: hapus blok manual, samakan pola dengan cabang "else":
```php
if ($tab === 'riwayat') {
    $query = PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])
        ->with(['kelas.tahunAjaran', 'semester'])
        ->when($request->search, function ($q, $search) {
            $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
        })
        ->latest();
} else {
    // (tidak berubah)
}
```

### Item B (🟡 Sedang) — Tidak ada badge scope yayasan/lembaga di header

**File**: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` (tambah helper baru) + `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`

Pola standar (sudah dipakai di Karyawan/Guru/TP/Rekap Rapor): badge purple "Semua Lembaga" untuk aktor yayasan mode agregat, badge brand-color nama lembaga untuk aktor yayasan yang sudah pilih lembaga aktif, TIDAK TAMPIL untuk aktor lembaga-scope.

**Perbaikan — Controller**, tambahkan helper (pola identik `KaryawanController::scopeHeaderData()`):
```php
use App\Models\Lembaga;

/**
 * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
 * halaman (pola sama seperti admin/karyawan/index.blade.php) -- HANYA relevan untuk aktor
 * berscope yayasan (punya switcher lembaga); aktor lembaga-scope tidak butuh badge ini
 * karena mereka selalu berada di 1 lembaga tetap.
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = session('active_lembaga_id');

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Panggil di `index()`, gabungkan ke `compact()` view data:
```php
return view('portals.lembaga.rapor.persetujuan.index', array_merge(
    compact('pengajuanList', 'tab'),
    $this->scopeHeaderData($request)
));
```

**Perbaikan — View** (`index.blade.php`, di dalam `<h1>` header, baris 7-11):
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Persetujuan Rapor</h1>
            @if ($isYayasan ?? false)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500 mt-0.5">Daftar kelas yang menunggu keputusan Anda pada alur persetujuan rapor semester.</p>
    </div>
    <p class="text-sm text-gray-500">
        Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Persetujuan Rapor</b>
    </p>
</div>
```

Catatan: request AJAX (`$request->ajax()`, dipakai untuk memuat ulang `_daftar` fragment) TIDAK memanggil `scopeHeaderData()` — badge cukup dirender sekali di full-page load, tidak perlu ikut fragment tabel.

### Item C (🟢 Kecil) — Tahun Ajaran tidak tampil di samping Semester

**File**: `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php` dan `show.blade.php`

`kelas.tahunAjaran` SUDAH di-eager-load di controller (`index()` dan `show()`) tapi tidak pernah dipakai di view manapun — konteks tahun ajaran hilang saat semester punya nama yang sama di tahun berbeda.

**Perbaikan — `_daftar.blade.php`** (baris 19, kolom Semester):
```blade
<td class="px-5 py-3.5 text-gray-600">{{ $pengajuan->semester->nama }} — {{ $pengajuan->kelas->tahunAjaran->nama }}</td>
```

**Perbaikan — `show.blade.php`** (baris 10):
```blade
<p class="text-xs text-gray-500 mt-0.5 font-mono">Semester: {{ $pengajuanRapor->semester->nama }} — {{ $pengajuanRapor->kelas->tahunAjaran->nama }}</p>
```

### Item D (🟡 Sedang) — Empty-state message salah konteks di tab "riwayat"

**File**: `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php` (baris 26)

Pesan `"Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini."` hardcoded untuk konteks "menunggu" tapi partial ini dipakai juga oleh tab "riwayat" (di mana pesan itu tidak masuk akal — riwayat bukan soal "menunggu keputusan").

**Perbaikan**: partial ini sudah menerima variabel `$tab` (dipakai controller: `compact('pengajuanList', 'tab')` di baris 69/72 `PersetujuanController`), tapi belum diteruskan ke `_daftar.blade.php` saat di-`@include` dari `index.blade.php` (baris 44, hanya `compact('pengajuanList')`). Perbaikan 2 langkah:

1. `index.blade.php` baris 44, teruskan `tab`:
```blade
@include('portals.lembaga.rapor.persetujuan._daftar', ['pengajuanList' => $pengajuanList, 'tab' => $tab])
```

2. `_daftar.blade.php` baris 26, kondisikan pesan:
```blade
{{ $tab === 'riwayat' ? 'Belum ada riwayat keputusan persetujuan rapor.' : 'Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.' }}
```

### Item E (🟢 Kecil) — Catatan tidak wajib saat Tolak

**File**: `app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php`

Field `catatan` selalu `nullable`, termasuk saat `action === 'REJECT'` — wali kelas bisa menerima penolakan tanpa keterangan alasan sama sekali, padahal `show.blade.php` (baris 17) menampilkan `catatan_revisi` ini balik ke wali kelas sebagai panduan revisi.

**Perbaikan**:
```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'action' => ['required', Rule::in(['APPROVE', 'REJECT'])],
        'catatan' => [
            Rule::requiredIf(fn () => $this->input('action') === 'REJECT'),
            'nullable',
            'string',
            'max:1000',
        ],
    ];
}
```

Label di `show.blade.php` (baris 139) juga disesuaikan agar tidak selalu bilang "(Opsional)":
```blade
<label class="block text-xs font-semibold text-gray-700 mb-1" x-text="action === 'REJECT' ? 'Catatan (Wajib diisi untuk penolakan)' : 'Catatan (Opsional)'"></label>
```
(Menggunakan `x-text` karena form ini sudah punya `x-data="{ action: 'APPROVE' }"` di elemen `<form>` baris 125 — label bereaksi live saat radio button diganti, tanpa reload.)

### Item F (🟢 Kecil) — Lazy-load yang bisa dihindari di `cetak()`

**File**: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`, method `cetak()` (baris 116-127)

`$pengajuanRapor->kelas->lembaga->bentuk_pendidikan` (baris 122) memicu 2 lazy-load (`kelas`, lalu `kelas.lembaga`) karena tidak ada satupun yang eager-loaded di method ini. Route-model-binding `PengajuanRapor $pengajuanRapor` di signature TIDAK meng-eager-load relasi apa pun.

**Perbaikan**: tambahkan eager-load di awal method:
```php
public function cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa, Request $request): Response
{
    abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
    abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404);

    $pengajuanRapor->loadMissing('kelas.lembaga');

    $data = $this->raporPdfDataBuilder->build($siswa, $pengajuanRapor->semester);
    $template = $this->raporPdfDataBuilder->templateUntukJenjang($pengajuanRapor->kelas->lembaga->bentuk_pendidikan);

    $pdf = Pdf::loadView($template, $data);

    return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
}
```

## 3. Item yang DIPERTIMBANGKAN tapi TIDAK masuk scope

- **Audit penuh UI/wording Pengadaan & SDM**: ditunda. Grep tertarget (`session('active_lembaga_id')`/`effectiveLembagaId` di `app/Domains/Pengadaan` dan `app/Domains/Sdm`) mengonfirmasi 0 hasil — kedua domain TIDAK punya duplikat bug seperti Item X, murni mengandalkan `ApproverResolverService`. Begitu Item W selesai, kedua domain otomatis ikut benar tanpa perubahan kode apa pun di sana. Audit UI/wording terpisah (badge scope, kejelasan halaman, dst.) bisa dilakukan lain waktu sebagai audit independen.
- **`catatan_revisi` tetap ditampilkan lintas siklus pengajuan dengan caveat "(jika masih relevan)"**: sudah dikonfirmasi ini desain sengaja (bukan bug) — `SubmitPengajuanRaporAction` tidak membersihkan field ini saat resubmit, dan wording view sudah eksplisit memberi peringatan. Tidak diubah.
- **Guard "wajib switch lembaga dulu" ala TP**: TIDAK diterapkan di titik keputusan approve/reject (`decision()`), karena beda sifat dari kasus TP — target lembaga pada `PengajuanRapor` yang sudah ada TIDAK ambigu (sudah ditentukan oleh `lembaga_id` record itu sendiri), berbeda dari TP "Tambah TP" yang datanya baru dibuat dengan dropdown lintas-lembaga yang ambigu. Guard yang benar (Item W) adalah validasi kepemilikan yayasan, bukan larangan mode agregat.

## 4. Urutan Pengerjaan (untuk plan)

1. Item W (Workflow engine — root cause)
2. Item X (bersih-bersih Rapor, HANYA setelah Item W teruji)
3. Item A, B, C, D, E, F (independen satu sama lain, bisa paralel/urutan bebas)

## 5. Testing

- `ApproverResolverService` butuh test unit/feature baru: aktor dengan kombinasi role `pegawai_yayasan` + `kepala_sekolah`/`wakasek_kurikulum`/`admin_sdm`, mode agregat (session kosong) vs lembaga aktif valid vs lembaga aktif TIDAK valid (bukan milik yayasannya) vs lembaga aktif valid tapi beda dari target — harus mencakup ketiga domain konsumen (Rapor/Pengadaan/SDM) minimal 1 skenario each, atau cukup 1 test langsung di level `ApproverResolverService` (unit, tanpa perlu lewat 3 domain) ditambah 1 regression test di level Rapor (feature, karena itu yang jadi pemicu audit).
- `PersetujuanController` existing test suite: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` dan `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php` (2 file, dikonfirmasi ada via pencarian langsung) untuk Item A/B/C/D/E/F.
- Test IDOR lintas lembaga/yayasan yang sudah ada harus tetap lolos (regression guard) — pastikan Item W tidak melonggarkan proteksi cross-tenant, hanya memperbaiki false-negative untuk kasus sah.

## 6. Self-Review (multi-round)

**Round 1 — konsistensi lintas item**: Item X bergantung pada Item W selesai lebih dulu (dicatat eksplisit di §4). Item B pola badge disalin persis dari `KaryawanController` (diverifikasi baris demi baris, bukan didesain ulang). Item D memperbaiki bug nyata (variabel `$tab` tidak diteruskan ke partial) yang sebelumnya tidak disadari saat audit awal — dikonfirmasi ulang dengan membaca `index.blade.php` baris 44 SAAT menulis spec ini.

**Round 2 — placeholder & ambiguitas**: semua kode di atas adalah kode final siap tempel, tidak ada "TODO"/"nanti"/placeholder. Nama file test di §5 diverifikasi ulang via pencarian langsung sebelum ditulis final (2 file ditemukan: `RaporPersetujuanControllerTest.php` dan `PersetujuanRaporRiwayatTest.php`) — bukan tebakan.

**Round 3 — cakupan vs permintaan user**: user secara eksplisit minta root-cause fix dipertimbangkan ("jadi kamu mau perbaiki semuanya?") dan dikonfirmasi scope-nya (perbaiki root cause + Rapor, TIDAK sentuh kode Pengadaan/SDM) — tercermin di Item W+X. User juga memutuskan audit Pengadaan/SDM terpisah ditunda — tercermin di §3. Tidak ada item baru yang ditambahkan di luar yang sudah dibahas eksplisit dalam sesi audit.

**Round 4 — arsitektur/layering**: dipertimbangkan secara eksplisit apakah `ApproverResolverService` boleh reuse `ResolveLembagaScopeTrait` milik Akademik — DITOLAK karena melanggar prinsip Workflow-sebagai-engine-generik (dipakai 3 domain, tidak boleh bergantung pada satu domain spesifik). Solusi: duplikasi logika minimal (bukan reuse lintas-domain), didokumentasikan alasannya langsung di kode via lokasi private method baru `resolveEffectiveLembagaId()` scoped ke `ApproverResolverService` sendiri.
