<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Identity\Actions\CreatePersonAction;
use App\Enums\SumberDataSiswa;
use App\Exports\SiswaImportTemplateExport;
use App\Imports\SiswaImportRow;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Services\AkunSiswaGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiswaImportController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.import');

        return view('admin.siswa.import');
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('siswa.import');

        return Excel::download(new SiswaImportTemplateExport, 'template-import-siswa.xlsx');
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

        DB::transaction(function () use ($validRows, $lembagaId) {
            $lembaga = Lembaga::withoutGlobalScopes()->findOrFail($lembagaId);

            foreach ($validRows as $row) {
                $person = app(CreatePersonAction::class)->execute(
                    identityData: [
                        'nama_lengkap' => $row['nama_lengkap'],
                        'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                        'tempat_lahir' => $row['tempat_lahir'] ?? null,
                        'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
                        'agama' => $row['agama'] ?? null,
                    ],
                    lembagaId: $lembagaId,
                    actingYayasanId: $lembaga->yayasan_id,
                );

                $user = app(AkunSiswaGenerator::class)->buat($row['nama_lengkap'], $row['nis'], $lembaga);
                $person->update(['user_id' => $user->id]);

                Siswa::create([
                    'person_id' => $person->id,
                    'lembaga_id' => $lembagaId,
                    'user_id' => $user->id,
                    'kelas_id' => $row['kelas_id'],
                    'sumber_data' => SumberDataSiswa::Import->value,
                    'nis' => $row['nis'],
                    'nisn' => $row['nisn'],
                    'status' => 'aktif',
                ]);
            }
        });

        session()->forget('siswa_import_valid_rows');

        return redirect()->route('admin.siswa.index')->with('status', count($validRows).' siswa berhasil diimport.');
    }
}
