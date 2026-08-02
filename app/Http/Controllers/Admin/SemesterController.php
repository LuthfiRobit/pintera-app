<?php

namespace App\Http\Controllers\Admin;

use App\Models\Semester;
use App\Models\TahunAjaran;
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
            'tahun_ajaran_id' => ['required', 'integer'],
            'ganjil_kode_dapodik' => ['nullable', 'string', 'max:5'],
            'ganjil_tanggal_mulai' => ['required', 'date'],
            'ganjil_tanggal_selesai' => ['required', 'date', 'after:ganjil_tanggal_mulai'],
            'genap_kode_dapodik' => ['nullable', 'string', 'max:5'],
            'genap_tanggal_mulai' => ['required', 'date', 'after:ganjil_tanggal_selesai'],
            'genap_tanggal_selesai' => ['required', 'date', 'after:genap_tanggal_mulai'],
        ]);

        $tahunAjaran = TahunAjaran::withoutGlobalScopes()->findOrFail($data['tahun_ajaran_id']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $tahunAjaran) {
            Semester::updateOrCreate(
                ['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 1],
                [
                    'lembaga_id' => $tahunAjaran->lembaga_id,
                    'nama' => 'Ganjil',
                    'kode_dapodik' => $data['ganjil_kode_dapodik'],
                    'tanggal_mulai' => $data['ganjil_tanggal_mulai'],
                    'tanggal_selesai' => $data['ganjil_tanggal_selesai'],
                ]
            );

            Semester::updateOrCreate(
                ['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 2],
                [
                    'lembaga_id' => $tahunAjaran->lembaga_id,
                    'nama' => 'Genap',
                    'kode_dapodik' => $data['genap_kode_dapodik'],
                    'tanggal_mulai' => $data['genap_tanggal_mulai'],
                    'tanggal_selesai' => $data['genap_tanggal_selesai'],
                ]
            );
        });

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Konfigurasi semester Ganjil & Genap berhasil disimpan.');
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
