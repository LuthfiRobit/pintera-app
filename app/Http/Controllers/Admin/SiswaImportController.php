<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberDataSiswa;
use App\Imports\SiswaImportRow;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class SiswaImportController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.import');

        return view('admin.siswa.import');
    }

    public function preview(Request $request): View
    {
        $this->authorize('siswa.import');

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        /** @var Collection<int, Collection<string, mixed>> $sheet */
        $sheet = Excel::toCollection(null, $request->file('file'))->first();
        $header = $sheet->first();
        $rows = $sheet->skip(1)->map(fn ($row) => $header->combine($row->values()));

        $result = SiswaImportRow::parseAll($rows, $lembagaId);

        session(['siswa_import_valid_rows' => $result['valid']]);

        return view('admin.siswa.import-preview', [
            'validRows' => $result['valid'],
            'invalidRows' => $result['invalid'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('siswa.import');

        $validRows = session('siswa_import_valid_rows', []);
        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        foreach ($validRows as $row) {
            Siswa::create([
                'lembaga_id' => $lembagaId,
                'kelas_id' => $row['kelas_id'],
                'sumber_data' => SumberDataSiswa::Import->value,
                'nis' => $row['nis'],
                'nisn' => $row['nisn'],
                'nama_lengkap' => $row['nama_lengkap'],
                'jenis_kelamin' => $row['jenis_kelamin'],
                'tempat_lahir' => $row['tempat_lahir'],
                'tanggal_lahir' => $row['tanggal_lahir'],
                'agama' => $row['agama'],
            ]);
        }

        session()->forget('siswa_import_valid_rows');

        return redirect()->route('admin.siswa.index')->with('status', count($validRows).' siswa berhasil diimport.');
    }
}
