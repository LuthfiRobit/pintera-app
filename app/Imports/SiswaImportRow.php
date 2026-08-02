<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Services\AkunSiswaGenerator;
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

        if (Siswa::where('lembaga_id', $lembagaId)->where('nis', $nis)->exists()) {
            return ['data' => $data, 'error' => 'NIS sudah dipakai siswa lain di lembaga ini.'];
        }

        if ($data['nisn'] !== null && Siswa::where('nisn', $data['nisn'])->exists()) {
            return ['data' => $data, 'error' => 'NISN sudah dipakai siswa lain.'];
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

        // Track NIS/NISN already claimed by an earlier row in this same
        // upload so within-file duplicates are caught here too — parse()
        // only sees one row at a time and can't detect this on its own.
        // Only the first occurrence of a given NIS/NISN is kept as valid;
        // every later row that repeats it is flagged invalid, since
        // committing duplicates within the batch would still collide.
        $seenNis = [];
        $seenNisn = [];

        foreach ($rows as $row) {
            $result = self::parse($row->toArray(), $lembagaId);

            if ($result['error'] === null) {
                $nis = $result['data']['nis'];
                $nisn = $result['data']['nisn'];

                if (isset($seenNis[$nis])) {
                    $result['error'] = 'NIS ini duplikat dengan baris lain di file yang sama.';
                } elseif ($nisn !== null && isset($seenNisn[$nisn])) {
                    $result['error'] = 'NISN ini duplikat dengan baris lain di file yang sama.';
                }
            }

            if ($result['error'] === null) {
                $seenNis[$result['data']['nis']] = true;

                if ($result['data']['nisn'] !== null) {
                    $seenNisn[$result['data']['nisn']] = true;
                }

                $rowValid = $result['data'];
                $lembaga = Lembaga::withoutGlobalScopes()->find($lembagaId);
                if ($lembaga) {
                    $generator = app(AkunSiswaGenerator::class);
                    $rowValid['predicted_username'] = $generator->usernameUntuk($lembaga, $rowValid['nis']);
                    $rowValid['predicted_password'] = $rowValid['nis'];
                }

                $valid[] = $rowValid;
            } else {
                $invalid[] = [...$result['data'], 'error' => $result['error']];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
