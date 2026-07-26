<?php

namespace App\Http\Controllers\Admin;

use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KomponenPenilaianController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('komponen-penilaian.kelola');

        return view('admin.komponen-penilaian.index', [
            'komponenList' => KomponenPenilaian::with(['mataPelajaran', 'semester'])->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('komponen-penilaian.kelola');

        return view('admin.komponen-penilaian.create', [
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'semester_id' => ['required', 'exists:semester,id'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'kktp' => ['nullable', 'string'],
        ]);

        KomponenPenilaian::create($data);

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
    }
}
