<?php

namespace App\Imports;

use App\Models\Kelas;
use Illuminate\Support\Collection;

class SiswaImportRow
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{data: array<string, mixed>, error: string|null}
     */
    public static function parse(array $row, int $lembagaId): array
    {
        $nis = trim((string) ($row['nis'] ?? ''));
        $namaLengkap = trim((string) ($row['nama_lengkap'] ?? ''));
        $jenisKelamin = trim((string) ($row['jenis_kelamin'] ?? ''));
        $namaKelas = trim((string) ($row['kelas'] ?? ''));

        $data = [
            'nis' => $nis,
            'nisn' => trim((string) ($row['nisn'] ?? '')) ?: null,
            'nama_lengkap' => $namaLengkap,
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => trim((string) ($row['tempat_lahir'] ?? '')) ?: null,
            'tanggal_lahir' => trim((string) ($row['tanggal_lahir'] ?? '')) ?: null,
            'agama' => trim((string) ($row['agama'] ?? '')) ?: null,
            'kelas_nama' => $namaKelas,
        ];

        if ($nis === '' || $namaLengkap === '') {
            return ['data' => $data, 'error' => 'NIS dan Nama Lengkap wajib diisi.'];
        }

        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            return ['data' => $data, 'error' => 'Jenis kelamin harus L atau P.'];
        }

        $kelas = Kelas::where('lembaga_id', $lembagaId)->where('nama', $namaKelas)->first();

        if (! $kelas) {
            return ['data' => $data, 'error' => "Kelas \"{$namaKelas}\" tidak ditemukan di lembaga ini."];
        }

        $data['kelas_id'] = $kelas->id;

        return ['data' => $data, 'error' => null];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{valid: array<int, array<string, mixed>>, invalid: array<int, array<string, mixed>>}
     */
    public static function parseAll(Collection $rows, int $lembagaId): array
    {
        $valid = [];
        $invalid = [];

        foreach ($rows as $row) {
            $result = self::parse($row->toArray(), $lembagaId);

            if ($result['error'] === null) {
                $valid[] = $result['data'];
            } else {
                $invalid[] = [...$result['data'], 'error' => $result['error']];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
