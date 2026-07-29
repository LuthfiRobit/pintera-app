# Komponen Penilaian (TP) — Index Filter Rework, Edit & Hapus

## Goal

Perbaiki halaman index Komponen Penilaian (TP): filter Tahun Ajaran/Semester/Mata Pelajaran yang benar (bukan derivasi dari data yang kebetulan sudah termuat), Tom Select di semua dropdown, tanpa reload halaman, dan setiap baris TP menampilkan Tahun Ajaran + Semester supaya tidak ambigu. Sekaligus tambahkan kemampuan edit dan hapus untuk entri TP yang sudah ada — saat ini sama sekali tidak ada (persis gap yang sama seperti Jadwal Pelajaran sebelum diperbaiki), dengan guard khusus karena TP sudah dipakai sebagai rujukan nilai nyata (`Asesmen`, `NilaiSiswa`).

**Di luar scope ini** (jadi paket terpisah berikutnya): halaman Create — penambahan konteks Tahun Ajaran dan cascading Tahun Ajaran→Semester di form tambah TP tetap belum disentuh di sini.

## Requirements (hasil diskusi & analisa)

### Index — perbaikan filter
1. Filter Tahun Ajaran (baru, belum pernah ada) — Tom Select, default ke Tahun Ajaran aktif, ada opsi "Semua Tahun Ajaran".
2. Filter Semester — Tom Select, **opsinya diambil langsung dari tabel `Semester` yang di-scope ke Tahun Ajaran terpilih** (bukan diturunkan dari daftar TP yang kebetulan sudah termuat — ini akar bug "semester cuma muncul satu" karena `->unique()` sebelumnya dedupe berdasarkan nama, bukan id). Ketika Tahun Ajaran = "Semua", filter Semester dinonaktifkan/disembunyikan (menghindari nama semester yang ambigu lintas tahun tanpa qualifier tambahan).
3. Filter Mata Pelajaran — Tom Select, daftar penuh dari `MataPelajaran` milik lembaga (tidak bergantung Tahun Ajaran).
4. Kolom pencarian teks (kode/deskripsi) tetap ada, tapi filtering dipindah ke server (query database), bukan client-side string matching di Alpine.
5. Semua kombinasi filter berjalan **tanpa reload halaman** (AJAX fragment swap), mengikuti pola yang sudah dibangun di Jadwal Pelajaran index (`_daftar.blade.php` + deteksi `$request->ajax()` di controller).
6. Setiap baris TP menampilkan Semester **dan** Tahun Ajaran (mis. "Ganjil — 2026/2027") supaya tidak ambigu.

### Edit & Hapus
7. Tombol Edit dan Hapus ditambahkan di setiap baris TP, digerbang permission yang sama dengan sekarang: `komponen-penilaian.kelola` (tidak perlu permission baru).
8. Hapus tombol memakai `confirmDialog` global, pola yang sama persis dengan Pola Jam/Jam Pelajaran/Jadwal Pelajaran.
9. **Guard hapus**: kalau komponen ini sudah dipakai di `Asesmen` (many-to-many via pivot `asesmen_komponen_penilaian`) atau `NilaiSiswa` (FK `komponen_penilaian_id`), hapus diblokir dengan pesan error yang jelas — bukan dihapus paksa.
10. **Guard edit**: kalau komponen sudah dipakai (kondisi sama seperti poin 9), field `mata_pelajaran_id` dan `semester_id` dikunci (ditampilkan read-only, tidak bisa diubah) — supaya data nilai yang sudah tercatat untuk konteks mapel/semester tertentu tidak jadi tidak konsisten. Field `kode`, `deskripsi`, `kktp` tetap selalu bisa diedit kapan saja.
11. Saat edit dan komponen BELUM dipakai (field mata_pelajaran_id/semester_id masih bisa diubah), dropdownnya pakai Tom Select — tapi **belum** memakai cascading Tahun Ajaran→Semester (itu bagian dari paket Create yang menyusul, dan nanti diterapkan konsisten ke Create maupun Edit sekaligus).

## Arsitektur

### Backend — `KomponenPenilaianController`

**Tenant isolation**: `KomponenPenilaian` tidak tenant-scoped langsung (tidak ada kolom `lembaga_id` atau trait `BelongsToTenant`) — sama seperti `JadwalPelajaran`. `edit()`/`update()`/`destroy()` harus menegakkan isolasi secara eksplisit dengan resolve `MataPelajaran::find($komponenPenilaian->mata_pelajaran_id)` + `abort(404)` kalau `null` (karena `MataPelajaran` tenant-scoped, ini otomatis memblokir akses lintas-lembaga) — pola yang identik dengan `Kelas::find()` pada `JadwalPelajaranController`.

**Model tambahan** — tambahkan dua relasi baru ke `app/Models/KomponenPenilaian.php` (belum ada sebelumnya):

```php
public function asesmen(): BelongsToMany
{
    return $this->belongsToMany(Asesmen::class, 'asesmen_komponen_penilaian', 'komponen_penilaian_id', 'asesmen_id');
}

public function nilaiSiswa(): HasMany
{
    return $this->hasMany(NilaiSiswa::class);
}
```

**`index()`** — perubahan:
```php
public function index(Request $request): View|string
{
    $this->authorize('komponen-penilaian.kelola');

    $tahunAjaranId = $request->query('tahun_ajaran_id');
    if ($tahunAjaranId === null) {
        $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
    }
    $semesterId = $request->query('semester_id');
    $mataPelajaranId = $request->query('mata_pelajaran_id');
    $search = $request->query('search');

    $komponenList = KomponenPenilaian::whereHas('mataPelajaran')
        ->with(['mataPelajaran', 'semester.tahunAjaran'])
        ->when($tahunAjaranId, fn ($q) => $q->whereHas('semester', fn ($q2) => $q2->where('tahun_ajaran_id', $tahunAjaranId)))
        ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
        ->when($mataPelajaranId, fn ($q) => $q->where('mata_pelajaran_id', $mataPelajaranId))
        ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('kode', 'like', "%{$search}%")->orWhere('deskripsi', 'like', "%{$search}%")))
        ->orderByDesc('id')
        ->get();

    if ($request->ajax()) {
        return view('admin.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
    }

    return view('admin.komponen-penilaian.index', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        'semesterId' => $semesterId,
        'mataPelajaranId' => $mataPelajaranId,
        'search' => $search,
        'komponenList' => $komponenList,
    ]);
}
```

**`opsi()`** (baru) — endpoint JSON untuk mengisi ulang pilihan Semester saat Tahun Ajaran berganti, mengikuti pola `JadwalPelajaranController::opsi()`:
```php
public function opsi(Request $request): JsonResponse
{
    $this->authorize('komponen-penilaian.kelola');

    $data = $request->validate(['tahun_ajaran_id' => ['required', 'integer']]);

    $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
    abort_if($tahunAjaran === null, 404);

    return response()->json([
        'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
    ]);
}
```

**`edit()`** (baru):
```php
public function edit(KomponenPenilaian $komponenPenilaian): View
{
    $this->authorize('komponen-penilaian.kelola');

    $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
    if (! $mataPelajaran) {
        abort(404);
    }

    $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

    return view('admin.komponen-penilaian.edit', [
        'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran']),
        'dipakai' => $dipakai,
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        'semesterList' => Semester::orderByDesc('id')->get(),
    ]);
}
```

**`update()`** (baru):
```php
public function update(Request $request, KomponenPenilaian $komponenPenilaian): RedirectResponse
{
    $this->authorize('komponen-penilaian.kelola');

    $mataPelajaranSaatIni = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
    if (! $mataPelajaranSaatIni) {
        abort(404);
    }

    $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

    $rules = [
        'kode' => ['nullable', 'string', 'max:50'],
        'deskripsi' => ['required', 'string'],
        'kktp' => ['nullable', 'string'],
    ];
    if (! $dipakai) {
        $rules['mata_pelajaran_id'] = ['required', 'integer'];
        $rules['semester_id'] = ['required', 'integer'];
    }

    $data = $request->validate($rules);

    if (! $dipakai) {
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
        $semester = Semester::find($data['semester_id']);
        abort_if($mataPelajaran === null || $semester === null, 404);
        abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);

        $komponenPenilaian->mata_pelajaran_id = $data['mata_pelajaran_id'];
        $komponenPenilaian->semester_id = $data['semester_id'];
    }

    $komponenPenilaian->kode = $data['kode'] ?? null;
    $komponenPenilaian->deskripsi = $data['deskripsi'];
    $komponenPenilaian->kktp = $data['kktp'] ?? null;
    $komponenPenilaian->save();

    return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
}
```

**`destroy()`** (baru):
```php
public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse
{
    $this->authorize('komponen-penilaian.kelola');

    $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
    if (! $mataPelajaran) {
        abort(404);
    }

    if ($komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists()) {
        return back()->withErrors(['komponen_penilaian' => 'Komponen ini sudah dipakai pada asesmen atau nilai siswa — tidak bisa dihapus.']);
    }

    $komponenPenilaian->delete();

    return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil dihapus.');
}
```

### Routes (`routes/admin.php`)

Tambahkan setelah baris `store` yang sudah ada:
```php
Route::get('komponen-penilaian/opsi', [KomponenPenilaianController::class, 'opsi'])->name('komponen-penilaian.opsi');
Route::get('komponen-penilaian/{komponenPenilaian}/edit', [KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
Route::put('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
Route::delete('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');
```

(Route `opsi` harus didaftarkan sebelum route `{komponenPenilaian}` implicit-binding manapun yang bisa menangkap literal "opsi" sebagai id — ikuti urutan yang sama seperti `jadwal-pelajaran/opsi` didaftarkan sebelum `jadwal-pelajaran/create`.)

### Frontend

**`index.blade.php`** — rombak total bagian filter: hapus Alpine `x-model`/`x-show` client-side, ganti dengan struktur mirip `jadwal-pelajaran/index.blade.php`: filter card dengan `x-data="komponenPenilaianFilter(...)"`, Tahun Ajaran + Semester + Mata Pelajaran sebagai Tom Select, kolom pencarian teks yang men-debounce lalu memicu `muatUlangDaftar()`. Bagian daftar TP dipindah ke `_daftar.blade.php` baru (partial), di-include di halaman penuh dan dikembalikan sebagai fragment untuk request AJAX — pola identik `jadwal-pelajaran/_daftar.blade.php`.

**`_daftar.blade.php`** (baru) — badge Semester diganti jadi `{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}`, dan di setiap baris ditambahkan aksi Edit/Hapus:
```blade
@can('komponen-penilaian.kelola')
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.komponen-penilaian.edit', $komponen) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
        <form method="POST" action="{{ route('admin.komponen-penilaian.destroy', $komponen) }}" x-data @submit.prevent="confirmDialog('Hapus Komponen Penilaian?', @js('Apakah Anda yakin ingin menghapus TP ' . ($komponen->kode ?: $komponen->deskripsi) . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
        </form>
    </div>
@endcan
```

**`edit.blade.php`** (baru) — struktur identik `create.blade.php` (toast blocks, form card yang sama), dengan perbedaan:
- Kalau `$dipakai` true: `mata_pelajaran_id`/`semester_id` ditampilkan sebagai teks read-only (bukan `<select>`) dengan catatan "Sudah dipakai di asesmen/nilai — mapel dan semester tidak bisa diubah", dan TIDAK dikirim sebagai input form (server tidak menerimanya juga).
- Kalau `$dipakai` false: `mata_pelajaran_id`/`semester_id` tetap `<select>` yang di-enhance Tom Select (reuse pola `initXSelect` yang sama seperti modul-modul lain), prefilled dari `$komponenPenilaian`.
- Field `kode`/`deskripsi`/`kktp` selalu editable, prefilled dari `$komponenPenilaian`.
- Form method PUT, action `route('admin.komponen-penilaian.update', $komponenPenilaian)`.

### JS baru

`resources/js/komponen-penilaian-filter.js` — factory `komponenPenilaianFilter(config)`, isinya mengikuti pola `jadwal-pelajaran-filter.js` persis: Tom Select untuk Tahun Ajaran (memicu fetch ke `opsi()` untuk refresh Semester lalu `muatUlangDaftar()`), Tom Select untuk Semester & Mata Pelajaran (langsung `muatUlangDaftar()` saat berubah), input pencarian dengan debounce sederhana lalu `muatUlangDaftar()`, `muatUlangDaftar()` melakukan fetch AJAX ke `admin.komponen-penilaian.index` dengan query params filter dan menukar `innerHTML` container daftar — sama persis mekanismenya dengan `jadwal-pelajaran-filter.js`.

`resources/js/komponen-penilaian-edit.js` (baru, kecil) — factory `komponenPenilaianEditForm()` dengan `initMataPelajaranSelect(el)`/`initSemesterSelect(el)` (Tom Select single-select biasa, tanpa cascading) — HANYA dipakai saat `$dipakai` false di `edit.blade.php`.

## Self-Review

- **Placeholder scan**: tidak ada — semua kode controller, guard, dan struktur view sudah konkret.
- **Konsistensi pola**: AJAX fragment swap, `confirmDialog`, penamaan `opsi()`, dan struktur JS filter semuanya meniru persis pola yang sudah terbukti bekerja di Jadwal Pelajaran — tidak menciptakan pola baru.
- **Keamanan data nilai**: guard hapus/edit terhadap `Asesmen`/`NilaiSiswa` adalah requirement inti yang membedakan TP dari kasus Jadwal Pelajaran (yang tidak punya data turunan sepenting ini) — dicek dua kali (di `update()` untuk mengunci field, dan di `destroy()` untuk memblokir hapus).
- **Cakupan scope**: murni index (filter + baris) + edit + delete. Perbaikan halaman Create (konteks Tahun Ajaran, cascading) sengaja tidak disentuh, dikerjakan sebagai paket terpisah berikutnya sesuai kesepakatan.
