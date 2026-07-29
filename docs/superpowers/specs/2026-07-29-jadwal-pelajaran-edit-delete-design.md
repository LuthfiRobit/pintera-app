# Jadwal Pelajaran — Edit & Hapus (Poin A)

## Goal

Menutup gap fungsional yang sempat di-skip: entri Jadwal Pelajaran yang sudah dibuat saat ini tidak bisa diedit atau dihapus dari UI sama sekali (tidak ada tombol, route, atau controller method untuk itu). Tambahkan kemampuan edit dan hapus per entri, konsisten dengan pola yang sudah ada di fitur Pola Jam/Jam Pelajaran (termasuk dialog konfirmasi sebelum hapus).

## Requirements (hasil diskusi)

1. Setiap baris jadwal di daftar (`_daftar.blade.php`) mendapat tautan **Edit** dan tombol **Hapus**, digerbang oleh permission `jadwal-pelajaran.kelola` yang sama dengan create/store (tidak perlu permission baru — beda dengan Jam Pelajaran yang punya permission terpisah per aksi).
2. Tombol **Hapus** memakai `confirmDialog` global (persis pola Pola Jam/Jam Pelajaran): `x-data @submit.prevent="confirmDialog('Hapus Jadwal?', pesan, { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })"`, pesan menyebutkan mapel/guru/slot yang akan dihapus.
3. Halaman **Edit** (`edit.blade.php`) baru, desainnya konsisten dengan `create.blade.php` yang baru selesai: Tom Select pada Jam Pelajaran/Mata Pelajaran/Guru, info konteks Tahun Ajaran/Semester/Kelas ditampilkan, toast/flash blocks.
4. Saat edit, **Kelas dan Semester tidak bisa diubah** (tetap sebagai konteks, ditampilkan read-only) — yang bisa diubah hanya Jam Pelajaran (single-select, karena mengedit satu entri yang sudah ada, bukan batch), Mata Pelajaran, dan Guru.
5. Validasi keamanan/integritas saat update sama seperti store (jam pelajaran harus is_pelajaran dan milik pola_jam kelas ini; guru/mapel harus dari lembaga yang sama dengan kelas) — gagal kalau melanggar.
6. Validasi bentrok jadwal (duplikat slot untuk kelas+semester ini, guru bentrok di jam+semester yang sama) dicek ulang saat update, **tapi mengecualikan record yang sedang diedit itu sendiri** (supaya tidak bentrok dengan dirinya sendiri).
7. Hapus (`destroy()`) tidak perlu validasi bentrok apapun — cukup resolve dengan aman lalu hapus.

## Arsitektur

### Routes (`routes/admin.php`)

Tambahkan setelah baris `store` yang sudah ada (baris 133), mengikuti persis konvensi `jam-pelajaran` (implicit route model binding pada model yang tidak tenant-scoped langsung):

```php
Route::get('jadwal-pelajaran/{jadwalPelajaran}/edit', [JadwalPelajaranController::class, 'edit'])->name('jadwal-pelajaran.edit');
Route::put('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('jadwal-pelajaran.update');
Route::delete('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('jadwal-pelajaran.destroy');
```

### Backend — `JadwalPelajaranController`

`JadwalPelajaran` model punya implicit route-model-binding (Laravel resolves `{jadwalPelajaran}` otomatis) tapi model ini **tidak** tenant-scoped langsung (tidak ada `lembaga_id` kolom atau `BelongsToTenant` trait) — sama seperti `JamPelajaran`. Tenant isolation harus ditegakkan secara eksplisit dengan me-resolve `Kelas` (yang tenant-scoped) lewat `kelas_id`-nya dan `abort(404)` kalau `null` — persis pola yang sudah dipakai `JamPelajaranController::edit()`/`update()`/`destroy()` untuk `PolaJam`.

```php
public function edit(JadwalPelajaran $jadwalPelajaran): View
{
    $this->authorize('jadwal-pelajaran.kelola');

    $kelas = Kelas::with(['lembaga', 'tahunAjaran'])->find($jadwalPelajaran->kelas_id);
    if (! $kelas) {
        abort(404);
    }

    $semester = Semester::find($jadwalPelajaran->semester_id);

    $hariAktif = Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? []);

    $jamPelajaranPerHari = collect();
    if ($kelas->pola_jam_id) {
        $mentah = JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)
            ->isPelajaran()
            ->orderBy('urutan')
            ->get()
            ->groupBy(fn ($jam) => $jam->hari->value);

        foreach ($hariAktif as $hari) {
            if ($mentah->has($hari->value)) {
                $jamPelajaranPerHari->push(['hari' => $hari, 'items' => $mentah->get($hari->value)]);
            }
        }
    }

    return view('admin.jadwal-pelajaran.edit', [
        'jadwalPelajaran' => $jadwalPelajaran,
        'kelas' => $kelas,
        'semester' => $semester,
        'jamPelajaranPerHari' => $jamPelajaranPerHari,
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        'guruList' => Guru::orderBy('nama')->get(),
    ]);
}

public function update(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse
{
    $this->authorize('jadwal-pelajaran.kelola');

    $kelas = Kelas::find($jadwalPelajaran->kelas_id);
    if (! $kelas) {
        abort(404);
    }

    $data = $request->validate([
        'jam_pelajaran_id' => ['required', 'integer'],
        'mata_pelajaran_id' => ['nullable', 'integer'],
        'guru_id' => ['required', 'integer'],
    ]);

    $guru = Guru::find($data['guru_id']);
    if (! $guru) {
        abort(404);
    }

    if (! empty($data['mata_pelajaran_id'])) {
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
        if (! $mataPelajaran) {
            abort(404);
        }
    }

    if ($guru->lembaga_id !== $kelas->lembaga_id) {
        return back()->withErrors(['guru_id' => 'Guru harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
    }

    if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
        return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
    }

    $jamPelajaran = JamPelajaran::where('id', $data['jam_pelajaran_id'])
        ->where('pola_jam_id', $kelas->pola_jam_id)
        ->isPelajaran()
        ->first();
    if (! $jamPelajaran) {
        abort(404);
    }

    $duplikat = JadwalPelajaran::where('kelas_id', $jadwalPelajaran->kelas_id)
        ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
        ->where('semester_id', $jadwalPelajaran->semester_id)
        ->where('id', '!=', $jadwalPelajaran->id)
        ->exists();
    if ($duplikat) {
        return back()->withErrors(['jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.'])->withInput();
    }

    $guruBentrok = JadwalPelajaran::where('guru_id', $data['guru_id'])
        ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
        ->where('semester_id', $jadwalPelajaran->semester_id)
        ->where('id', '!=', $jadwalPelajaran->id)
        ->exists();
    if ($guruBentrok) {
        return back()->withErrors(['guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.'])->withInput();
    }

    $jadwalPelajaran->update([
        'jam_pelajaran_id' => $data['jam_pelajaran_id'],
        'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
        'guru_id' => $data['guru_id'],
    ]);

    return redirect()->route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $jadwalPelajaran->kelas_id,
        'semester_id' => $jadwalPelajaran->semester_id,
    ])->with('status', 'Jadwal pelajaran berhasil diperbarui.');
}

public function destroy(JadwalPelajaran $jadwalPelajaran): RedirectResponse
{
    $this->authorize('jadwal-pelajaran.kelola');

    $kelas = Kelas::find($jadwalPelajaran->kelas_id);
    if (! $kelas) {
        abort(404);
    }

    $kelasId = $jadwalPelajaran->kelas_id;
    $semesterId = $jadwalPelajaran->semester_id;
    $jadwalPelajaran->delete();

    return redirect()->route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelasId,
        'semester_id' => $semesterId,
    ])->with('status', 'Jadwal pelajaran berhasil dihapus.');
}
```

### Frontend

**`_daftar.blade.php`** — di setiap `<li>` baris jadwal (baris 36-70 saat ini), tambahkan blok aksi Edit/Hapus persis pola Pola Jam:

```blade
<div class="flex items-center gap-4">
    @can('jadwal-pelajaran.kelola')
        <a href="{{ route('admin.jadwal-pelajaran.edit', $jadwal) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
        <form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
        </form>
    @endcan
</div>
```

**`edit.blade.php`** (baru) — struktur identik dengan `create.blade.php` (toast blocks, context bar Tahun Ajaran/Semester/Kelas, Tom Select pada 3 dropdown), dengan perbedaan:
- Jam Pelajaran jadi **single-select** (bukan multi, bukan `name="jam_pelajaran_id[]"`), opsi `@selected` berdasarkan `$jadwalPelajaran->jam_pelajaran_id`, bukan `old()`.
- Mata Pelajaran/Guru ter-prefill dari `$jadwalPelajaran->mata_pelajaran_id`/`guru_id`.
- Form method PUT (`@method('PUT')`), action `route('admin.jadwal-pelajaran.update', $jadwalPelajaran)`.
- Tidak ada hidden input `kelas_id`/`semester_id` yang bisa diubah — konteksnya cuma ditampilkan di context bar, controller sudah tahu dari `$jadwalPelajaran` itu sendiri.
- Tombol submit "Simpan Perubahan" (bukan "Simpan Jadwal"), tautan batal ke index dengan `kelas_id`/`semester_id` dari `$jadwalPelajaran`.

### JS

Reuse `jadwalPelajaranCreateForm()` yang sudah ada di `resources/js/jadwal-pelajaran-create.js` — method `initJamPelajaranSelect`/`initMataPelajaranSelect`/`initGuruSelect` sudah generik (tidak spesifik ke multi-select create), jadi `edit.blade.php` bisa memakai `x-data="jadwalPelajaranCreateForm()"` yang sama tanpa perlu modul JS baru. `initJamPelajaranSelect` tetap dipakai untuk single-select karena Tom Select otomatis mendeteksi `multiple` attribute dari elemen `<select>`-nya sendiri (opsi `plugins`/config yang dipakai tidak menspesifikkan `maxItems` untuk jam pelajaran, jadi aman dipakai untuk keduanya).

## Self-Review

- **Placeholder scan**: tidak ada — kode edit()/update()/destroy() lengkap dan konkret.
- **Konsistensi tenant-isolation**: mengikuti pola standar proyek ini (resolve FK tenant-scoped via `Model::find()` + `abort(404)`, bukan `exists:table,column`) — sama seperti `JamPelajaranController` menangani `PolaJam` yang juga tidak diakses langsung via route model binding tenant-aware.
- **Konsistensi UX**: dialog konfirmasi hapus pakai helper global `confirmDialog` yang sama persis dengan Pola Jam/Jam Pelajaran, bukan komponen baru.
- **Cakupan scope**: hanya menambah edit/hapus untuk entri yang sudah ada. Tidak menyentuh create/store (poin B/C yang sudah selesai) atau index/filter (sudah selesai).
