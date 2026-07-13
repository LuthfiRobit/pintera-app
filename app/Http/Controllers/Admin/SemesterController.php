<?php

namespace App\Http\Controllers\Admin;

use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use RuntimeException;

class SemesterController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('semester.create');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'nama' => ['required', 'in:Ganjil,Genap'],
            'urutan' => ['required', 'integer', 'in:1,2'],
            'kode_dapodik' => ['nullable', 'string', 'max:5'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        Semester::create($data);

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Semester berhasil dibuat.');
    }

    public function activate(Semester $semester): RedirectResponse
    {
        $this->authorize('semester.activate');

        try {
            $semester->activate();
        } catch (RuntimeException $e) {
            return back()->withErrors(['semester' => $e->getMessage()]);
        }

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Semester berhasil diaktifkan.');
    }
}
