<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class SiswaImportTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
            ['2026001', '0012345678', 'Budi Santoso', 'L', 'Jakarta', '2015-03-10', 'Islam', '6A'],
        ];
    }
}
