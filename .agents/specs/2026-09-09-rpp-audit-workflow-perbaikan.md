# Audit & Perbaikan Menu RPP (Perangkat Ajar) — Workflow, Wording, Backend

**Tanggal**: 2026-09-09
**Branch**: `akademik-v2`
**Status**: Draft — menunggu review user sebelum plan+kickoff

## Ringkasan

Audit mendalam menu RPP (`RppController` + `resources/views/portals/lembaga/akademik/rpp/`) fokus pada 3 lapis: backend/keamanan, wording, dan UI/UX workflow (Draft → Diajukan → Disetujui/Perlu Revisi). Ditemukan **10 item**, dari kritis (data guru lain bocor tampil ke tab "Saya") sampai wording yang menyesatkan.

**Item A adalah temuan paling serius** — dibuktikan empiris lewat test sementara (dibuat lalu dihapus setelah verifikasi): aktor tanpa profil Guru (role `operator_akademik`, `kepala_sekolah`, `wakasek_kurikulum` — SEMUA staf non-mengajar yang punya `rpp.view`) yang membuka tab **"Perangkat Ajar Saya"** (tab default, tanpa perlu utak-atik URL) melihat RPP **SEMUA GURU** di lembaga, bukan cuma "miliknya" (karena memang tidak punya RPP pribadi). Untuk `operator_akademik` (satu-satunya yang juga punya `rpp.kelola`), tombol Edit/Hapus/Ajukan bahkan MUNCUL di baris RPP orang lain — walau backend tetap menolak (403) saat diklik. **Backend AMAN, tapi UI menyesatkan** — user akan klik tombol yang selalu gagal, tanpa penjelasan.

---

## Item A — 🔴 KRITIS: Tab "Perangkat Ajar Saya" Menampilkan RPP Semua Orang untuk Aktor Tanpa Profil Guru

### Masalah

`app/Domains/Akademik/Actions/Rpp/ListRppAction.php`:

```php
if ($tab === 'saya') {
    $guru = $user->guru;
    if ($guru) {
        $baseQuery->where('guru_id', $guru->id);
    }
}
```

Kalau `$user->guru` `null` (aktor `operator_akademik`/`kepala_sekolah`/`wakasek_kurikulum` — normal untuk staf non-mengajar), filter `where('guru_id', ...)` DILEWATI SELURUHNYA. Tab bernama "Perangkat Ajar **SAYA**" jadi menampilkan RPP milik SEMUA guru di lembaga.

**Dibuktikan empiris**: user `operator_akademik` tanpa profil Guru, buka `/admin/rpp` (default `tab=saya`) → melihat RPP "Topik Milik Guru Lain". Tombol "Edit Dokumen" MUNCUL di baris itu (`@can('rpp.kelola')` lolos karena permission generik, TANPA cek kepemilikan di view). Klik "Ajukan ke Kurikulum" pada baris itu → HTTP 403 (backend `authorizeMilikGuru()` menolak dengan benar) — tapi user sudah terlanjur bingung kenapa tombol yang terlihat aktif malah gagal.

### Perbaikan

**1. `ListRppAction`** — untuk `tab === 'saya'` TANPA profil Guru, JANGAN tampilkan RPP siapa pun (bukan "lewati filter", tapi hasil KOSONG eksplisit — tab ini secara harfiah berarti "milik saya", dan aktor tanpa profil Guru literal tidak punya RPP pribadi):

```php
if ($tab === 'saya') {
    $guru = $user->guru;
    if ($guru) {
        $baseQuery->where('guru_id', $guru->id);
    }
}
```

menjadi:

```php
if ($tab === 'saya') {
    $guru = $user->guru;
    if ($guru) {
        $baseQuery->where('guru_id', $guru->id);
    } else {
        // Aktor tanpa profil Guru (operator/kepsek/wakasek) tidak punya RPP
        // pribadi -- tab "Saya" WAJIB kosong, bukan menampilkan RPP orang lain.
        $baseQuery->whereRaw('1 = 0');
    }
}
```

**2. View `index.blade.php`** — beri tahu KENAPA kosong (beda dari "tidak ada yang cocok filter"). Di `_daftar.blade.php`, empty-state untuk tab "saya" (baris ±252) perlu tahu apakah aktor punya profil Guru:

```blade
<p class="font-semibold text-gray-700">
    {{ $tab === 'verifikasi' ? 'Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.' : 'Belum ada dokumen perangkat ajar yang cocok dengan filter.' }}
</p>
```

menjadi (tambah kondisi baru sebagai prioritas pertama):

```blade
<p class="font-semibold text-gray-700">
    @if ($tab === 'saya' && ! auth()->user()->guru)
        Akun Anda tidak terhubung dengan profil Guru, sehingga tidak ada dokumen RPP pribadi di sini.
    @elseif ($tab === 'verifikasi')
        Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.
    @else
        Belum ada dokumen perangkat ajar yang cocok dengan filter.
    @endif
</p>
```

**3. `_daftar.blade.php` — tombol aksi WAJIB cek kepemilikan eksplisit di view**, bukan cuma permission generik (defense-in-depth UI, supaya tombol yang terlihat SELALU valid diklik, tidak pernah menjanjikan aksi yang backend-nya pasti tolak). Baris ±109 saat ini:

```blade
@if ($rpp->canBeEditedByGuru())
    @can('rpp.kelola')
        {{-- Ajukan, Edit, Hapus --}}
    @endcan
@endif
```

menjadi:

```blade
@if ($rpp->canBeEditedByGuru() && auth()->user()->guru?->id === $rpp->guru_id)
    @can('rpp.kelola')
        {{-- Ajukan, Edit, Hapus --}}
    @endcan
@endif
```

Catatan: perbaikan #1 (query) SUDAH cukup menutup celah untuk `tab=saya` (operator tidak akan pernah melihat baris ini lagi). Perbaikan #3 ditambahkan sebagai lapis kedua yang independen — kalau suatu saat ada jalur lain yang menampilkan RPP guru lain (mis. fitur baru), tombol tidak akan pernah muncul salah lagi.

---

## Item B — `TenantContext::activeLembagaId()` Tidak Validasi Ulang Session (Scoped ke Penggunaan RPP)

### Masalah

`app/Domains/Shared/Context/TenantContext.php`:

```php
public function activeLembagaId(): ?int
{
    ...
    if ($this->isYayasanScope()) {
        return session('active_lembaga_id');
    }
    return $user->lembaga_id;
}
```

Beda dari `ResolveLembagaScopeTrait::resolveActiveLembagaId()` (dipakai SEMUA menu lain yang sudah kita audit sepanjang sesi ini), method ini TIDAK validasi ulang apakah `session('active_lembaga_id')` benar milik yayasan aktor. `ListRppAction` memakai `TenantContext::activeLembagaId()` untuk scoping index/stats/Inbox Verifikasi.

**Dampak**: aktor yayasan-scope dengan session basi (lembaga di luar yayasannya — skenario yang sudah berkali-kali kita uji aman di menu lain) akan mendapati query `WHERE lembaga_id = <lembaga asing>`. `TenantScope` otomatis di model `Rpp` MENCEGAH kebocoran data (irisan dengan filter asing = kosong), TAPI hasilnya jadi **kosong TOTAL secara salah** — bukan mode agregat yang benar. Paling berbahaya di tab **Inbox Verifikasi**: verifikator level yayasan bisa melihat inbox kosong padahal ada RPP menunggu.

**Catatan cakupan**: `TenantContext` dipakai di 13 file LAIN (Sarpras: Ruangan/Gedung/AsetBarang/dll, Pengadaan: Pengajuan/Approval/Disbursement/AuditLpj) — kemungkinan bug sama ada di sana juga. **DI LUAR SCOPE spec ini** (fokus RPP dulu, sesuai arahan) — dicatat sebagai backlog terpisah, sama seperti kita mendeferred bug sistemik `TenantScope` platform-scope di banyak spec sebelumnya.

### Perbaikan

`ListRppAction` BERHENTI memakai `TenantContext`, ganti dengan pola established `ResolveLembagaScopeTrait::resolveActiveLembagaId()` yang SUDAH tervalidasi aman di seluruh menu lain. Karena `ListRppAction` adalah Action class (bukan Controller, tidak otomatis punya trait ini), `$targetLembagaId` dihitung di CONTROLLER dan di-passing sebagai parameter, bukan di-resolve di dalam Action:

`app/Http/Controllers/Admin/RppController.php`, method `index()`, tambahkan sebelum pemanggilan `listRppAction`:

```php
$targetLembagaId = $user->widestScopeLevel() === 'yayasan'
    ? $this->resolveActiveLembagaId($user)
    : $user->lembaga_id;
```

Ganti pemanggilan:

```php
[
    'rppList' => $rppList,
    'stats' => $stats,
    'status' => $status,
    'targetLembagaId' => $targetLembagaId,
] = $this->listRppAction->execute(
    user: $user,
    tab: $tab,
    search: $search,
    tahunAjaranId: $tahunAjaranId ? (int) $tahunAjaranId : null,
    semesterId: $semesterId ? (int) $semesterId : null,
    kelasId: $kelasId ? (int) $kelasId : null,
    mapelId: $mapelId ? (int) $mapelId : null,
    status: $status,
    perPage: $perPage,
    kurikulum: $kurikulum,
);
```

menjadi:

```php
[
    'rppList' => $rppList,
    'stats' => $stats,
    'status' => $status,
] = $this->listRppAction->execute(
    user: $user,
    tab: $tab,
    search: $search,
    tahunAjaranId: $tahunAjaranId ? (int) $tahunAjaranId : null,
    semesterId: $semesterId ? (int) $semesterId : null,
    kelasId: $kelasId ? (int) $kelasId : null,
    mapelId: $mapelId ? (int) $mapelId : null,
    status: $status,
    perPage: $perPage,
    kurikulum: $kurikulum,
    targetLembagaId: $targetLembagaId,
);
```

`ListRppAction::execute()` — tambah parameter `?int $targetLembagaId`, HAPUS dependency `TenantContext`:

```php
final class ListRppAction
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(
        User $user,
        string $tab,
        ?string $search,
        ?int $tahunAjaranId,
        ?int $semesterId,
        ?int $kelasId,
        ?int $mapelId,
        ?string $status,
        int $perPage,
        ?string $kurikulum = null,
    ): array {
        $targetLembagaId = $this->tenantContext->activeLembagaId();

        $baseQuery = Rpp::query();
        if ($targetLembagaId) {
            $baseQuery->where('lembaga_id', $targetLembagaId);
        }
        ...
        return [
            'rppList' => $rppList,
            'stats' => $stats,
            'status' => $status,
            'targetLembagaId' => $targetLembagaId,
        ];
    }
}
```

menjadi:

```php
final class ListRppAction
{
    public function execute(
        User $user,
        string $tab,
        ?string $search,
        ?int $tahunAjaranId,
        ?int $semesterId,
        ?int $kelasId,
        ?int $mapelId,
        ?string $status,
        int $perPage,
        ?string $kurikulum = null,
        ?int $targetLembagaId = null,
    ): array {
        $baseQuery = Rpp::query();
        if ($targetLembagaId) {
            $baseQuery->where('lembaga_id', $targetLembagaId);
        }
        ...
        return [
            'rppList' => $rppList,
            'stats' => $stats,
            'status' => $status,
        ];
    }
}
```

(Hapus `use App\Domains\Shared\Context\TenantContext;` dan constructor promosi properti kalau sudah tidak dipakai lagi di file itu.)

`RppController::index()` — bagian bawah yang MEMAKAI `$targetLembagaId` untuk dropdown (`Kelas::query()->where('lembaga_id', $targetLembagaId)` dst, baris ±97-125) TIDAK berubah — variabel `$targetLembagaId` sekarang datang dari `$this->resolveActiveLembagaId()` di atas, bukan dari hasil destructuring Action lagi, isinya SAMA (aman untuk aktor lembaga-scope, DIPERBAIKI untuk aktor yayasan-scope dengan session basi).

---

## Item C — Wording Salah: "Waka Kurikulum" Di-hardcode Padahal 3 Role Bisa Verifikasi

### Masalah

`_daftar.blade.php` baris ±116, dialog konfirmasi "Ajukan ke Kurikulum":

```blade
@submit.prevent="confirmDialog('Ajukan RPP ke Kurikulum?', 'Apakah Anda yakin ingin mengajukan berkas ini untuk diverifikasi oleh Waka Kurikulum?', { confirmLabel: 'Ya, Ajukan' }).then(c => { if(c) $el.submit() })"
```

`database/seeders/RoleSeeder.php` mengonfirmasi permission `rpp.verify` digrant ke **3 role**: `kepala_sekolah`, `wakasek_kurikulum`, `operator_akademik` — bukan cuma Waka Kurikulum.

### Perbaikan

```blade
@submit.prevent="confirmDialog('Ajukan RPP ke Kurikulum?', 'Apakah Anda yakin ingin mengajukan berkas ini untuk diverifikasi oleh pihak kurikulum?', { confirmLabel: 'Ya, Ajukan' }).then(c => { if(c) $el.submit() })"
```

("pihak kurikulum" netral terhadap role spesifik yang bertugas — sesuai kenyataan bisa Kepala Sekolah, Waka Kurikulum, atau operator akademik.)

---

## Item D — Empty State Tab "Inbox Verifikasi" Tidak Sadar Filter

### Masalah

`_daftar.blade.php` baris ±247-258:

```blade
@if ($rppList->isEmpty())
    <tr>
        <td colspan="8" class="px-5 py-12 text-center text-gray-500">
            <x-icon name="description" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="font-semibold text-gray-700">
                {{ $tab === 'verifikasi' ? 'Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.' : 'Belum ada dokumen perangkat ajar yang cocok dengan filter.' }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $tab === 'verifikasi' ? 'Semua pengajuan RPP telah selesai ditinjau.' : 'Silakan sesuaikan kriteria filter atau unggah dokumen baru.' }}
            </p>
        </td>
    </tr>
@endif
```

Untuk tab "verifikasi", pesan SELALU klaim "Semua pengajuan RPP telah selesai ditinjau" — walau kekosongan itu murni karena filter aktif (mis. Mata Pelajaran tertentu yang kebetulan 0 pengajuan), BUKAN karena benar-benar semua sudah ditinjau. Berbeda dari tab "saya" yang sudah benar sadar-filter.

### Perbaikan

Deteksi apakah ADA filter aktif (selain default status=Diajukan untuk tab verifikasi), tampilkan pesan berbeda:

```blade
@if ($rppList->isEmpty())
    <tr>
        <td colspan="8" class="px-5 py-12 text-center text-gray-500">
            <x-icon name="description" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            @php
                $adaFilterAktif = $search || $semesterId || $kelasId || $mapelId || $kurikulum
                    || ($tahunAjaranId && $tahunAjaranId != ($tahunAjaranAktif->id ?? null))
                    || ($tab === 'verifikasi' && $status !== \App\Domains\Akademik\Enums\StatusRpp::Diajukan->value);
            @endphp
            <p class="font-semibold text-gray-700">
                @if ($tab === 'saya' && ! auth()->user()->guru)
                    Akun Anda tidak terhubung dengan profil Guru, sehingga tidak ada dokumen RPP pribadi di sini.
                @elseif ($tab === 'verifikasi' && ! $adaFilterAktif)
                    Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.
                @elseif ($tab === 'verifikasi')
                    Tidak ada dokumen yang cocok dengan filter di Inbox Verifikasi.
                @else
                    Belum ada dokumen perangkat ajar yang cocok dengan filter.
                @endif
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                @if ($tab === 'verifikasi' && ! $adaFilterAktif)
                    Semua pengajuan RPP telah selesai ditinjau.
                @else
                    Silakan sesuaikan kriteria filter atau unggah dokumen baru.
                @endif
            </p>
        </td>
    </tr>
@endif
```

(Blok `@if ($tab === 'saya' && ! auth()->user()->guru)` di sini SAMA dengan Item A poin 2 — kalau Item A dan D dikerjakan bersamaan, gabungkan jadi satu edit, JANGAN dobel.)

**Catatan penting soal `$adaFilterAktif`**: kondisi terakhir (`$tab === 'verifikasi' && $status !== StatusRpp::Diajukan->value`) WAJIB ada. `$status` yang diterima view SUDAH HASIL AKHIR dari `ListRppAction` (kalau awalnya `null`, sudah di-default jadi `'diajukan'` oleh Action sebelum sampai ke view) — TIDAK ADA cara membedakan "auto-default" vs "user pilih Diajukan sendiri" dari view, dan memang TIDAK PERLU dibedakan (keduanya sama-sama wajar dianggap "masih default Inbox"). Yang WAJIB dideteksi adalah kalau `$status` **BUKAN** `'diajukan'` (user pilih status lain atau "Semua Status" secara eksplisit) — itu artinya user SUDAH keluar dari default Inbox, jadi hasil kosong bukan berarti "semua sudah ditinjau".

**PENTING — gap nyata yang WAJIB diperbaiki lebih dulu**: `_daftar.blade.php` dirender di KEDUA cabang `index()` (ajax dan halaman penuh), tapi cabang ajax SAAT INI cuma mengirim 3 variabel:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', compact('rppList', 'tab', 'perPage'));
}
```

Item D dan E butuh `$search`, `$semesterId`, `$kelasId`, `$mapelId`, `$kurikulum`, `$tahunAjaranId`, `$tahunAjaranAktif` — SEMUA variabel ini TIDAK tersedia di cabang ajax saat ini, akan menghasilkan error "Undefined variable" begitu `_daftar.blade.php` dirender via AJAX (yaitu SETIAP KALI user ganti filter — jalur paling sering dipakai). WAJIB diperbaiki SEBELUM Item D/E, ganti baris di atas menjadi:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', compact(
        'rppList', 'tab', 'perPage', 'search', 'tahunAjaranId', 'semesterId', 'kelasId', 'mapelId', 'kurikulum', 'tahunAjaranAktif'
    ));
}
```

---

## Item E — Dropdown Status Bisa "Membocorkan" Non-Diajukan ke Inbox Tanpa Indikasi Jelas

### Masalah

`ListRppAction`:

```php
} elseif ($tab === 'verifikasi' && $status === null) {
    $status = StatusRpp::Diajukan->value;
}
```

Default HANYA berlaku kalau `$status === null` (belum disentuh). Dropdown Status di `index.blade.php` (dipakai KEDUA tab, tidak ada pembeda) punya opsi "— Semua Status —" — begitu dipilih, `$status` jadi string kosong `''` (bukan `null`), default TIDAK berlaku, dan tab "Inbox Verifikasi" menampilkan RPP berstatus Draft/Disetujui/PerluRevisi juga — padahal tombol "Tinjau" cuma ada untuk baris Diajukan.

### Perbaikan

Sembunyikan opsi "— Semua Status —" di tab verifikasi, DAN beri label eksplisit menjelaskan defaultnya. `index.blade.php` baris ±179-187:

```blade
{{-- Status --}}
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status</label>
    <select x-model="filters.status" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
        <option value="">— Semua Status —</option>
        @foreach (\App\Domains\Akademik\Enums\StatusRpp::cases() as $s)
            <option value="{{ $s->value }}">{{ $s->label() }}</option>
        @endforeach
    </select>
</div>
```

menjadi:

```blade
{{-- Status --}}
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-500">
        Status
        @if ($tab === 'verifikasi')
            <span class="font-normal text-gray-400">(Inbox default: Menunggu Verifikasi)</span>
        @endif
    </label>
    <select x-model="filters.status" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
        <option value="">{{ $tab === 'verifikasi' ? '— Semua Status (Keluar dari Inbox Default) —' : '— Semua Status —' }}</option>
        @foreach (\App\Domains\Akademik\Enums\StatusRpp::cases() as $s)
            <option value="{{ $s->value }}">{{ $s->label() }}</option>
        @endforeach
    </select>
</div>
```

(Opsi "biarkan bisa dipilih tapi diberi label jelas" dipilih daripada "sembunyikan total" — supaya verifikator yang MEMANG ingin melihat riwayat status lain di tab yang sama tetap bisa, tapi sadar dia sedang keluar dari default Inbox.)

---

## Item F — KPI Stats Tidak Terikat Tab/Filter Aktif

### Masalah

**KOREKSI dari draf pertama spec ini**: awalnya saya klaim `$stats` "tidak ikut tab aktif sama sekali" — SALAH setelah dicek ulang. `ListRppAction` (baris 34-56) menghitung `$stats` dari `$baseQuery`, dan `$baseQuery` SUDAH kena filter `where('guru_id', ...)` untuk tab `saya` SEBELUM `$stats` dihitung — jadi 4 kartu KPI MEMANG berbeda antara tab "Saya" (scoped ke RPP sendiri) dan tab "Verifikasi" (lembaga penuh, tanpa filter status apa pun termasuk default Diajukan — 4 kartu justru breakdown PER status, jadi wajar tidak ikut filter status).

Yang BENAR jadi masalah: `$stats` TIDAK ikut filter KONTROL (search, Tahun Ajaran, Semester, Kelas, Mata Pelajaran, Kurikulum) yang ada di form filter tepat di bawahnya — filter-filter itu HANYA diterapkan ke `$query` (dipakai tabel), bukan ke `$baseQuery` (dipakai stats). `index.blade.php` baris ±38-91, 4 kartu KPI diposisikan tepat di atas kontrol filter tsb, berisiko user kira angka itu ikut berubah saat filter kontrol diisi.

### Perbaikan

Tambahkan keterangan kecil di bawah judul section KPI menjelaskan cakupannya. `index.blade.php`, sebelum baris ±39 (`<div class="grid grid-cols-1 gap-3 sm:grid-cols-4">`):

```blade
<p class="text-[11px] text-gray-400 -mb-1">Ringkasan {{ $tab === 'saya' ? 'dokumen Anda' : 'seluruh dokumen di lembaga ini' }} (tidak berubah mengikuti filter pencarian/Tahun Ajaran/Semester/Kelas/Mapel/Kurikulum di bawah).</p>
```

(Perbaikan minimal murni tambahan teks, tidak mengubah query/struktur — mengubah `$stats` agar ikut filter kontrol adalah perubahan produk yang lebih besar dan tidak diminta di sini. Teks dibuat kondisional per tab supaya akurat — bukan klaim generik "seluruh dokumen lembaga" yang salah untuk tab Saya.)

---

## Item G — Tidak Ada Badge Scope (isYayasan/activeLembaga)

### Masalah

Halaman RPP tidak punya badge scope sama sekali — pola yang sudah kita tegakkan di semua menu lain (Tahun Ajaran, Kelas, Mata Pelajaran, Kurikulum Assignment, Pola Jam, Jadwal Pelajaran). **Baru bisa benar dipasang SETELAH Item B selesai** (kalau dipasang sebelum Item B, badge-nya sendiri berisiko salah karena `$targetLembagaId` yang dipakai masih dari sumber yang tidak tervalidasi).

### Perbaikan

**PENTING**: `RppController.php` SAAT INI belum meng-`use App\Models\Lembaga;` (dicek langsung — tidak ada di daftar import). Tambahkan import ini di bagian atas file.

`RppController::index()`, tambahkan `scopeHeaderData()`-style info, pola PERSIS sama seperti menu lain yang sudah diaudit:

```php
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Dipanggil di KEDUA return `view(...)` di `index()` (baik cabang ajax `_daftar` maupun halaman penuh `index`) — sama seperti pola yang sudah diterapkan di Jadwal Pelajaran (Item A spec itu).

**Urutan pengerjaan penting**: kalau Item D (yang menambah variabel ke `compact()` cabang ajax) dan Item G (yang menambah `scopeHeaderData()` ke cabang yang SAMA) dikerjakan sebagai task terpisah, pastikan hasil AKHIR cabang ajax menggabungkan KEDUANYA, bukan salah satu menimpa yang lain:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', array_merge(compact(
        'rppList', 'tab', 'perPage', 'search', 'tahunAjaranId', 'semesterId', 'kelasId', 'mapelId', 'kurikulum', 'tahunAjaranAktif'
    ), $this->scopeHeaderData($request)));
}
```

(Dipakai `array_merge(compact(...), $this->scopeHeaderData($request))` di sini KARENA `compact()` cuma bisa menerima nama variabel sederhana, sedangkan `scopeHeaderData()` mengembalikan array asosiatif dari method call — tidak bisa digabung langsung dalam satu `compact()`.)

`index.blade.php` baris ±28-36, header:

```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Perangkat Ajar (RPP / Modul Ajar)</h1>
    <p class="text-xs text-gray-500 mt-0.5">Kelola penyusunan dokumen perencanaan pembelajaran, pengajuan, dan verifikasi kurikulum.</p>
</div>
```

menjadi:

```blade
<div>
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Perangkat Ajar (RPP / Modul Ajar)</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
            </span>
        @endif
    </div>
    <p class="text-xs text-gray-500 mt-0.5">Kelola penyusunan dokumen perencanaan pembelajaran, pengajuan, dan verifikasi kurikulum.</p>
</div>
```

---

## Item H — `UpdateRppRequest` Tidak Cek Lembaga `kelas_id` (Asimetris dari `StoreRppRequest`)

### Masalah

`StoreRppRequest::withValidator()` cek eksplisit `$guru->lembaga_id !== $kelas->lembaga_id` untuk jalur admin-tanpa-profil-guru. `UpdateRppRequest::withValidator()` (baris ±37-48) CUMA cek `$kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id` — TIDAK ADA cek `$kelas->lembaga_id !== $rpp->lembaga_id`. Risiko eksploitasi rendah (jalur ini cuma bisa diakses guru pemilik RPP via `authorizeMilikGuru()`, dan guru biasanya lembaga-scope bukan yayasan-scope) — tapi tetap inkonsisten dengan filosofi "selalu validasi eksplisit" yang sudah ditegakkan di semua menu lain.

### Perbaikan

`app/Http/Requests/Akademik/UpdateRppRequest.php`:

```php
$validator->after(function (Validator $validator) {
    $kelasId = $this->input('kelas_id');
    $rpp = $this->route('rpp');
    if (! $kelasId || ! $rpp) {
        return;
    }

    $kelas = Kelas::find($kelasId);
    if ($kelas && $kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id) {
        $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester dokumen RPP ini.');
    }
});
```

menjadi:

```php
$validator->after(function (Validator $validator) {
    $kelasId = $this->input('kelas_id');
    $rpp = $this->route('rpp');
    if (! $kelasId || ! $rpp) {
        return;
    }

    $kelas = Kelas::find($kelasId);
    if (! $kelas) {
        return;
    }

    if ($kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id) {
        $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester dokumen RPP ini.');
    }

    if ($kelas->lembaga_id !== $rpp->lembaga_id) {
        $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari lembaga yang sama dengan dokumen RPP ini.');
    }
});
```

---

## Item I — Urutan Hapus File Sebelum Commit Transaksi di `UpdateRppAction`

### Masalah

`app/Domains/Akademik/Actions/Rpp/UpdateRppAction.php`, file lama DIHAPUS DARI DISK sebelum `$rpp->update()` di dalam `DB::transaction()`:

```php
if ($data->file) {
    if ($rpp->file_path && Storage::disk('public')->exists($rpp->file_path)) {
        Storage::disk('public')->delete($rpp->file_path);
    }

    $file = $data->file;
    ...
}

return DB::transaction(function () use (...) {
    $rpp->update([...]);
    return $rpp->fresh();
});
```

Kalau `$rpp->update()` gagal (jarang terjadi di sini, tapi mungkin), file lama SUDAH hilang dari disk padahal DB rollback ke `file_path` lama yang sudah tidak ada filenya — state jadi tidak konsisten.

### Perbaikan

Pindahkan penghapusan file lama ke DALAM closure transaksi, SETELAH `update()` berhasil (bukan sebelum):

```php
return DB::transaction(function () use ($rpp, $data, $storedPath, $originalFileName, $fileSize, $mimeType) {
    $oldFilePath = $rpp->file_path;
    $fileBerubah = $data->file !== null;

    $rpp->update([
        'kelas_id' => $data->kelasId,
        'mata_pelajaran_id' => $data->mataPelajaranId,
        'judul_topik' => $data->judulTopik,
        'alokasi_waktu' => $data->alokasiWaktu,
        'pertemuan_ke' => $data->pertemuanKe,
        'file_path' => $storedPath,
        'file_name' => $originalFileName,
        'file_size_bytes' => $fileSize,
        'mime_type' => $mimeType,
    ]);

    if ($fileBerubah && $oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
        Storage::disk('public')->delete($oldFilePath);
    }

    return $rpp->fresh();
});
```

Dan hapus blok penghapusan file lama yang lama (sebelum `DB::transaction`) serta pindahkan logic upload file baru (`$file->store(...)`) TETAP di luar transaksi (upload fisik tidak perlu terikat transaksi DB, cuma urutan hapus-file-lama yang perlu dipindah).

Kode lengkap method setelah perbaikan:

```php
public function execute(Rpp $rpp, RppData $data): Rpp
{
    if (! $rpp->canBeEditedByGuru()) {
        throw ValidationException::withMessages([
            'status' => 'Dokumen RPP ini sedang diverifikasi atau sudah disetujui, sehingga tidak dapat disunting.',
        ]);
    }

    $storedPath = $rpp->file_path;
    $originalFileName = $rpp->file_name;
    $fileSize = $rpp->file_size_bytes;
    $mimeType = $rpp->mime_type;
    $fileBerubah = false;

    if ($data->file) {
        $fileBerubah = true;
        $file = $data->file;
        $originalFileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $storedPath = $file->store("rpp/{$data->lembagaId}", 'public');
    }

    return DB::transaction(function () use ($rpp, $data, $storedPath, $originalFileName, $fileSize, $mimeType, $fileBerubah) {
        $oldFilePath = $rpp->file_path;

        $rpp->update([
            'kelas_id' => $data->kelasId,
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'judul_topik' => $data->judulTopik,
            'alokasi_waktu' => $data->alokasiWaktu,
            'pertemuan_ke' => $data->pertemuanKe,
            'file_path' => $storedPath,
            'file_name' => $originalFileName,
            'file_size_bytes' => $fileSize,
            'mime_type' => $mimeType,
        ]);

        if ($fileBerubah && $oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
            Storage::disk('public')->delete($oldFilePath);
        }

        return $rpp->fresh();
    });
}
```

---

## Di Luar Scope (Tidak Dikerjakan, Sengaja Ditunda)

- **`TenantContext` untuk 13 file LAIN di luar RPP** (Sarpras, Pengadaan) — bug yang sama KEMUNGKINAN ada di sana, tapi TIDAK diaudit/diperbaiki di spec ini. Backlog terpisah.
- **Bug sistemik `TenantScope` platform-scope** — konsisten dengan semua spec sebelumnya di rangkaian audit ini.
- **Jalur "admin membuat RPP atas nama guru" tidak verifikasi guru benar-benar mengajar kombinasi kelas+mapel** (beda dari jalur guru mengisi sendiri yang divalidasi ketat via `JadwalPelajaran`) — ini PERTANYAAN PRODUK yang belum dijawab user (apakah kelonggaran ini disengaja untuk fleksibilitas admin, atau celah yang perlu ditutup). TIDAK diubah di spec ini sampai ada keputusan eksplisit.
- **Modal create/edit/verify RPP masih pakai POST + reload halaman penuh** (bukan AJAX seperti Pola Jam/Jadwal Pelajaran) — konsisten SECARA INTERNAL di RPP sendiri (semua 3 modal sama-sama reload), cuma beda gaya dari menu lain yang lebih baru. Modernisasi ke AJAX adalah pekerjaan besar terpisah, bukan bug, TIDAK termasuk spec ini.
- **`$stats` KPI tidak ikut filter tabel** (Item F hanya menambah keterangan teks, TIDAK mengubah agar KPI ikut filter) — perubahan produk lebih besar, di luar scope.

## Tabel Panduan Test

| Item | Test yang dibutuhkan |
|---|---|
| A | Aktor tanpa profil Guru (mis. `operator_akademik`) di tab "saya" melihat 0 RPP (BUKAN RPP guru lain); tombol Edit/Hapus/Ajukan TIDAK muncul untuk baris bukan miliknya walau di tab manapun; empty-state khusus tampil |
| B | Yayasan-scope actor dengan `active_lembaga_id` stale di tab "verifikasi" TETAP melihat mode agregat (bukan kosong salah); regresi "menolak actor yayasan dengan active_lembaga_id stale saat memverifikasi RPP" (existing) tetap lulus |
| C | Response TIDAK mengandung "Waka Kurikulum", mengandung "pihak kurikulum" |
| D | Empty-state tab verifikasi DENGAN filter aktif menampilkan pesan berbeda dari tanpa filter |
| E | Dropdown Status tab verifikasi menampilkan label "(Inbox default: ...)" dan opsi "Semua Status" berlabel beda |
| F | Response mengandung teks keterangan cakupan KPI |
| G | Badge scope muncul konsisten dengan pola menu lain (agregat/narrow) |
| H | `update()` menolak `kelas_id` dari lembaga lain dengan pesan error jelas (test baru, belum ada) |
| I | Regresi test existing untuk update RPP dengan file baru tetap lulus; tambahan test memverifikasi file lama TETAP ADA di disk kalau update gagal (skenario simulasi kegagalan) |
