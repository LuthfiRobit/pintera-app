# Spec: Penyempurnaan Halaman Index — Menu Kurikulum Assignment (Susulan ke-4)

> **Branch**: `akademik-v2` (setara `rbac-v2`)
> **Tanggal**: 8 September 2026
> **Latar belakang**: Diskusi lanjutan setelah 3 spec susulan sebelumnya (`tahun-ajaran-scope-leak`, `bentuk-pendidikan-lembaga`, `restyle-tabel`) SELESAI & terverifikasi. User meminta "sempurnakan halaman assign kurikulum" setelah menemukan/mempertanyakan 5 hal konkret lewat percakapan: (1) index tidak pernah menyempit saat switch lembaga — dikonfirmasi INI BUG/kelalaian implementasi manual, bukan keputusan desain sengaja, (2) header tidak responsif + 2 tombol sama-sama menonjol, (3) tidak ada indikasi visual scope (badge) di index, (4) pesan error "pilih lembaga dulu" dari `create()` TIDAK PERNAH tampil di index (bug terpisah, dikonfirmasi lewat pembacaan kode), (5) tombol Hapus pakai `confirm()` browser native, bukan komponen dialog konfirmasi standar aplikasi (`confirmDialog()`), dan pesannya generik — tidak memperingatkan risiko khusus saat menghapus assignment GLOBAL (yang tidak ada pengaman "masih dipakai" sama sekali, ditemukan saat investigasi ini).

## Ringkasan Temuan & Fix (5 item)

| # | Ringkasan | Severity |
|---|---|---|
| E.1 | `index()` untuk yayasan-scope TIDAK PERNAH mengecek `session('active_lembaga_id')` — SELALU agregat penuh, beda dari SEMUA menu lain (Kelas/TahunAjaran/MataPelajaran) yang otomatis menyempit lewat `TenantScope`. Akar masalah: `KurikulumAssignment` TIDAK pakai `TenantScope` (karena konsep assignment global), jadi scoping manual di controller — dan manualnya HANYA meniru cabang "agregat" `TenantScope`, LUPA meniru cabang "menyempit kalau ada lembaga aktif". | 🟠 Sedang-Tinggi (perilaku tidak konsisten) |
| E.2 | Index tidak punya badge scope ("Semua Lembaga"/nama lembaga) — pola yang SUDAH established di Kelas/TahunAjaran/MataPelajaran. Sekarang relevan lagi setelah E.1 (index BENAR-BENAR menyempit, badge jadi informasi yang jujur, bukan cuma kosmetik). | 🟡 Sedang |
| E.3 | Header `<div class="flex items-center justify-between">` TIDAK PUNYA `flex-wrap`/`gap-3` (beda dari SEMUA halaman lain) — pecah di layar sempit. 2 tombol aksi ("Cek & Perbaiki Kurikulum/Fase" dan "Tambah Assignment") ditulis manual dengan class Tailwind sendiri-sendiri (bukan komponen `<x-link-button>` yang sudah ada), sama-sama berbobot visual besar padahal 1 aksi utama & 1 aksi sekunder. | 🟠 Sedang-Tinggi (genuinely tidak responsif) |
| E.4 | `index.blade.php` HANYA menampilkan `session('status')`/`session('error')` — TIDAK PERNAH menampilkan `$errors` (message bag validasi). Guard `create()` (dari spec 6-item sebelumnya) mengirim pesan "Pilih lembaga aktif..." lewat `->withErrors([...])`, yang masuk ke `$errors`, BUKAN `session('error')` — jadi user diam-diam di-redirect TANPA pesan apa pun yang terlihat. | 🔴 Tinggi (fitur guard yang sudah dibangun jadi tidak terlihat efeknya) |
| E.5 | Tombol Hapus pakai `onsubmit="return confirm('Hapus assignment ini?')"` — popup browser native, BUKAN komponen `confirmDialog()` standar yang dipakai HAMPIR SEMUA form Hapus lain di aplikasi ini (dikonfirmasi lewat grep, 40+ lokasi memakainya, termasuk `admin/tahun-ajaran/index.blade.php` untuk fitur serupa). Pesannya juga generik, tidak menyebutkan detail assignment yang dihapus, dan TIDAK memperingatkan risiko khusus assignment GLOBAL (yang — temuan tambahan — TIDAK PUNYA pengaman "masih dipakai Kelas" sama sekali, beda dari assignment lembaga-spesifik). | 🟡 Sedang |

## Keputusan yang Diambil

1. **E.1 diperbaiki supaya PERSIS meniru 2 cabang `TenantScope`'s logika yayasan** (`if ($activeLembagaId) { sempit } else { agregat }`) — bukan pendekatan baru, murni melengkapi apa yang seharusnya sudah ditiru sejak awal.
2. **Catatan statis lama ("Daftar ini selalu menampilkan SEMUA lembaga...", ditambahkan di spec sebelumnya) DIHAPUS** — begitu E.1 membuat index BENAR-BENAR menyempit, catatan itu jadi SALAH/menyesatkan. Digantikan badge (E.2), yang jujur mengikuti kondisi aktual.
3. **E.4: bug murni tampilan, TIDAK mengubah logic guard `create()` yang sudah benar** — `create()` SUDAH benar mengirim `withErrors()`, cuma `index.blade.php` (halaman tujuan redirect) yang tidak pernah membacanya. Fix HANYA di view.
4. **E.5: TIDAK memperluas pengaman "masih dipakai Kelas" ke assignment global** — dipertimbangkan, TAPI ditolak untuk spec ini karena secara teknis JAUH lebih rumit (Kelas TIDAK menyimpan referensi balik ke `KurikulumAssignment` mana yang dipakai — cuma snapshot nilai `kurikulum` saat dibuat; pengaman yang benar untuk kasus global butuh logika "apakah ada lembaga yang TIDAK punya override sendiri untuk kombinasi ini" yang jauh lebih kompleks & butuh diskusi produk terpisah). Untuk sekarang, CUKUP diperingatkan lebih jelas lewat teks dialog konfirmasi — dicatat eksplisit di "Di Luar Scope".
5. **`<x-link-button>` (E.3) dipakai APA ADANYA** (varian `primary` untuk "Tambah Assignment", `ghost` untuk "Cek & Perbaiki") — komponen SUDAH ADA, dikonfirmasi lewat pembacaan `resources/views/components/link-button.blade.php`, dipakai luas di Kelas dkk.

---

## E.1 & E.2 — `index()`: Menyempit Saat Switch + Kirim Data Badge

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `index()`)

Kode saat ini:
```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

    if ($scope === 'yayasan') {
        $lembagaIds = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');
        $query->where(function ($q) use ($lembagaIds) {
            $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
        });
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($actor) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $actor->lembaga_id);
        });
    }

    $assignmentList = $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get()
        ->each(function (KurikulumAssignment $assignment) use ($actor) {
            $assignment->canManage = $this->canManageAssignment($actor, $assignment->lembaga_id);
        });

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $assignmentList,
        'isYayasan' => $scope === 'yayasan',
    ]);
}
```

Fix:
```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $activeLembagaId = $scope === 'yayasan' ? $this->resolveActiveLembagaId($actor) : null;
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

    if ($scope === 'yayasan') {
        if ($activeLembagaId !== null) {
            $query->where(function ($q) use ($activeLembagaId) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $activeLembagaId);
            });
        } else {
            $lembagaIds = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');
            $query->where(function ($q) use ($lembagaIds) {
                $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
            });
        }
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($actor) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $actor->lembaga_id);
        });
    }

    $assignmentList = $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get()
        ->each(function (KurikulumAssignment $assignment) use ($actor) {
            $assignment->canManage = $this->canManageAssignment($actor, $assignment->lembaga_id);
        });

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $assignmentList,
        'isYayasan' => $scope === 'yayasan',
        'activeLembaga' => $activeLembagaId ? Lembaga::find($activeLembagaId) : null,
    ]);
}
```

`resolveActiveLembagaId()` (dari `ResolveLembagaScopeTrait`, SUDAH dipakai luas di controller ini) otomatis menangani validasi ulang "lembaga aktif di session masih milik yayasan aktor" (kasus stale session) — TIDAK perlu logic tambahan.

---

## E.2 (lanjutan) & E.3 — View: Badge, Responsivitas Header, Hierarki Tombol

**File**: `resources/views/admin/kurikulum-assignment/index.blade.php` (baris ±11-32)

Kode saat ini:
```blade
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
        <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
        @if ($isYayasan ?? false)
            <p class="mt-1 text-xs text-gray-400">Daftar ini selalu menampilkan SEMUA lembaga di yayasan Anda beserta assignment global — tidak menyempit walau Anda mengganti lembaga aktif lewat pengalih lembaga di pojok kanan atas.</p>
        @endif
    </div>
    <div class="flex items-center gap-2">
        @can('kurikulum-assignment.view')
            <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Cek & Perbaiki Kurikulum/Fase
            </a>
        @endcan
        @can('kurikulum-assignment.create')
            <a href="{{ route('admin.kurikulum-assignment.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Assignment
            </a>
        @endcan
    </div>
</div>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
            @if ($isYayasan ?? false)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @can('kurikulum-assignment.view')
            <x-link-button href="{{ route('admin.kurikulum-assignment.resync') }}" variant="ghost">
                Cek & Perbaiki Kurikulum/Fase
            </x-link-button>
        @endcan
        @can('kurikulum-assignment.create')
            <x-link-button href="{{ route('admin.kurikulum-assignment.create') }}">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Assignment
            </x-link-button>
        @endcan
    </div>
</div>
```

**Rincian**: (1) wrapper terluar tambah `flex-wrap gap-3` — header sekarang responsif, pecah ke 2 baris di layar sempit alih-alih memaksa muat 1 baris. (2) Catatan statis lama DIHAPUS (Keputusan #2), diganti badge scope — pola SAMA PERSIS Kelas/TahunAjaran/MataPelajaran. (3) 2 tombol diganti `<x-link-button>`: "Tambah Assignment" (varian default `primary`, tetap paling menonjol — aksi utama) dan "Cek & Perbaiki Kurikulum/Fase" (varian `ghost` — outline, lebih redup, sesuai statusnya sebagai aksi sekunder/tool tambahan).

---

## E.4 — Tampilkan `$errors` di Index

**File**: `resources/views/admin/kurikulum-assignment/index.blade.php` (baris ±1-9)

Kode saat ini:
```blade
<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif
```

Fix (tambah blok `$errors->any()`, pola SAMA PERSIS `create.blade.php`/`edit.blade.php` di modul yang sama):
```blade
<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif
```

Sekarang guard `create()` ("Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.") akan BENAR-BENAR terlihat saat redirect ke index.

---

## E.5 — Dialog Konfirmasi Hapus Standar + Pesan Lebih Jelas

**File**: `resources/views/admin/kurikulum-assignment/index.blade.php` (baris ±65-73, di dalam `<x-table-actions>`)

Kode saat ini:
```blade
<form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
        <x-icon name="delete" class="h-4 w-4" />
        Hapus Assignment
    </button>
</form>
```

Fix:
```blade
<form
    method="POST"
    action="{{ route('admin.kurikulum-assignment.destroy', $a) }}"
    x-data
    @submit.prevent="confirmDialog(
        'Hapus Assignment Kurikulum?',
        @js('Hapus assignment '.$a->bentuk_pendidikan.($a->tingkat ? ' tingkat '.$a->tingkat : ' (semua tingkat)').' untuk '.($a->tahunAjaran->nama ?? 'tahun ajaran ini').'?'.($a->lembaga_id === null ? ' PERINGATAN: ini assignment GLOBAL (Platform Default) — dipakai sebagai cadangan oleh lembaga mana pun yang belum punya assignment sendiri untuk kombinasi ini, dan TIDAK ADA pengecekan otomatis sebelum dihapus.' : '')),
        { confirmLabel: 'Ya, Hapus', isDanger: true }
    ).then(confirmed => { if (confirmed) $el.submit() })"
>
    @csrf
    @method('DELETE')
    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
        <x-icon name="delete" class="h-4 w-4" />
        Hapus Assignment
    </button>
</form>
```

`confirmDialog()` adalah helper global (`window.confirmDialog`, didaftarkan `resources/js/confirm-dialog-store.js`, komponen `<x-confirm-dialog />` SUDAH ter-render global lewat `app.blade.php`) — TIDAK PERLU import/registrasi tambahan apa pun, cukup dipanggil.

---

## Di Luar Scope / Backlog Terpisah

1. **Memperluas pengaman "masih dipakai Kelas" ke assignment global** — dipertimbangkan (Keputusan #4), DITOLAK untuk spec ini karena kompleksitas teknisnya (Kelas tidak menyimpan referensi balik ke assignment sumbernya) butuh desain terpisah. Untuk sekarang cukup diperingatkan lewat teks dialog (E.5).
2. **Halaman `resync.blade.php`** — TIDAK diaudit/disentuh di spec ini.
3. **Kolom "Tahun Ajaran" di filter/dropdown lain** — TIDAK ada filter tambahan di index ini (beda dari Kelas yang punya filter Tahun Ajaran) — di luar scope, tidak diminta.

---

## Ringkasan Test yang Wajib Ditambahkan

| Item | Test |
|---|---|
| E.1 | Feature test — yayasan-scope switch ke Lembaga A, assignment ada di Lembaga A DAN Lembaga B (yayasan sama); assert index HANYA menampilkan assignment Lembaga A + global, TIDAK menampilkan Lembaga B. Feature test kedua — yayasan-scope TANPA switch (mode "Semua Lembaga"): assert KEDUANYA muncul (regresi, perilaku lama untuk mode ini TETAP sama). |
| E.2 | Feature test — assert badge "Semua Lembaga"/nama lembaga muncul di index sesuai mode (pola SAMA seperti test badge Kelas/TahunAjaran/MataPelajaran sebelumnya). |
| E.4 | Feature test — yayasan-scope tanpa lembaga aktif, GET `create()` (redirect ke index), assert response REDIRECT tsb (ikuti dengan GET index) `assertSee` pesan "Pilih lembaga aktif...". Atau lebih simpel: assert `assertSessionHasErrors` lalu re-GET index dan `assertSee` pesannya (session flash bertahan 1 request berikutnya). |
| E.5 | Feature test — assert response index mengandung `confirmDialog(` DAN TIDAK mengandung `onsubmit="return confirm(` (string check) untuk memastikan native `confirm()` benar-benar tergantikan. |

Regresi wajib: seluruh `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (SEMUA test existing dari 4 spec sebelumnya — terutama test yang berkaitan dengan mode "Semua Lembaga" HARUS tetap hijau, karena E.1 hanya menambah cabang BARU, bukan menghapus cabang lama).
