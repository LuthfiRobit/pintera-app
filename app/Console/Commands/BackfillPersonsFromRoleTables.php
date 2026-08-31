<?php

namespace App\Console\Commands;

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\OrangTua;
use App\Models\Siswa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class BackfillPersonsFromRoleTables extends Command
{
    protected $signature = 'identity:backfill-persons';

    protected $description = 'Backfill the persons master table from guru/karyawan/orang_tua/siswa/calon_murid rows.';

    /** @var array<int, array{table: string, row_id: int, nik_hash: ?string, yayasan_id: int}> */
    private array $seenThisRun = [];

    public function handle(): int
    {
        DB::transaction(function () {
            $this->backfillGuru();
            $this->backfillKaryawan();
            $this->backfillOrangTua();
            $this->backfillSiswa();
            $this->backfillCalonMurid();
        });

        $this->reportCollisions();

        return self::SUCCESS;
    }

    private function backfillGuru(): void
    {
        Guru::withoutGlobalScopes()->whereNull('person_id')->with('lembaga')->each(function (Guru $guru) {
            $person = $this->findOrCreatePerson(
                yayasanId: $guru->lembaga->yayasan_id,
                nik: $this->decryptRawAttribute($guru, 'nik'),
                namaLengkap: $guru->getRawOriginal('nama'),
                extra: [
                    'jenis_kelamin' => $guru->getRawOriginal('jenis_kelamin'),
                    'tempat_lahir' => $guru->getRawOriginal('tempat_lahir'),
                    'tanggal_lahir' => $guru->getRawOriginal('tanggal_lahir'),
                    'agama' => $guru->getRawOriginal('agama'),
                    'kewarganegaraan' => $guru->getRawOriginal('kewarganegaraan'),
                    'no_hp' => $guru->getRawOriginal('no_hp'),
                    'email' => $guru->getRawOriginal('email'),
                    'alamat_jalan' => $guru->getRawOriginal('alamat_jalan'),
                    'rt' => $guru->getRawOriginal('rt'),
                    'rw' => $guru->getRawOriginal('rw'),
                    'desa_kelurahan' => $guru->getRawOriginal('desa_kelurahan'),
                    'kecamatan' => $guru->getRawOriginal('kecamatan'),
                    'kabupaten_kota' => $guru->getRawOriginal('kabupaten_kota'),
                    'provinsi' => $guru->getRawOriginal('provinsi'),
                    'kode_pos' => $guru->getRawOriginal('kode_pos'),
                ],
                sourceTable: 'guru',
                sourceId: $guru->id,
            );

            $guru->newQueryWithoutScopes()->whereKey($guru->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillKaryawan(): void
    {
        Karyawan::withoutGlobalScopes()->whereNull('person_id')->get()->each(function (Karyawan $karyawan) {
            $yayasanId = $karyawan->lembaga_id !== null
                ? $karyawan->lembaga->yayasan_id
                : $karyawan->yayasan_id;

            $person = $this->findOrCreatePerson(
                yayasanId: $yayasanId,
                nik: $karyawan->getRawOriginal('nik'),
                namaLengkap: $karyawan->getRawOriginal('nama'),
                extra: ['no_hp' => $karyawan->getRawOriginal('no_hp'), 'email' => $karyawan->getRawOriginal('email')],
                sourceTable: 'karyawan',
                sourceId: $karyawan->id,
            );

            $karyawan->newQueryWithoutScopes()->whereKey($karyawan->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillOrangTua(): void
    {
        OrangTua::withoutGlobalScopes()->whereNull('person_id')->with('siswa.lembaga')->get()->each(function (OrangTua $ortu) {
            $siswa = $ortu->siswa->first();

            if ($siswa === null) {
                $this->warn("OrangTua id={$ortu->id} has no linked siswa -- cannot derive yayasan_id, skipped. Resolve manually.");

                return;
            }

            $person = $this->findOrCreatePerson(
                yayasanId: $siswa->lembaga->yayasan_id,
                nik: $ortu->getRawOriginal('nik'),
                namaLengkap: $ortu->getRawOriginal('nama_lengkap'),
                extra: ['no_hp' => $ortu->getRawOriginal('no_hp'), 'email' => $ortu->getRawOriginal('email'), 'alamat_jalan' => $ortu->getRawOriginal('alamat')],
                sourceTable: 'orang_tua',
                sourceId: $ortu->id,
            );

            $ortu->newQueryWithoutScopes()->whereKey($ortu->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillSiswa(): void
    {
        Siswa::withoutGlobalScopes()->whereNull('person_id')->with('lembaga')->get()->each(function (Siswa $siswa) {
            $person = $this->findOrCreatePerson(
                yayasanId: $siswa->lembaga->yayasan_id,
                nik: null,
                namaLengkap: $siswa->getRawOriginal('nama_lengkap'),
                extra: [
                    'jenis_kelamin' => $siswa->getRawOriginal('jenis_kelamin'),
                    'tempat_lahir' => $siswa->getRawOriginal('tempat_lahir'),
                    'tanggal_lahir' => $siswa->getRawOriginal('tanggal_lahir'),
                    'agama' => $siswa->getRawOriginal('agama'),
                ],
                sourceTable: 'siswa',
                sourceId: $siswa->id,
            );

            $siswa->newQueryWithoutScopes()->whereKey($siswa->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillCalonMurid(): void
    {
        CalonMurid::withoutGlobalScopes()->whereNull('person_id')->get()->each(function (CalonMurid $calon) {
            $person = $this->findOrCreatePerson(
                yayasanId: $calon->yayasan_id,
                nik: $this->decryptRawAttribute($calon, 'nik'),
                namaLengkap: $calon->getRawOriginal('nama_lengkap'),
                extra: [
                    'jenis_kelamin' => $calon->getRawOriginal('jenis_kelamin'),
                    'tempat_lahir' => $calon->getRawOriginal('tempat_lahir'),
                    'tanggal_lahir' => $calon->getRawOriginal('tanggal_lahir'),
                    'agama' => $calon->getRawOriginal('agama'),
                    'no_hp' => $calon->getRawOriginal('no_telepon'),
                    'email' => $calon->getRawOriginal('email_kontak'),
                ],
                sourceTable: 'calon_murid',
                sourceId: $calon->id,
            );

            $calon->newQueryWithoutScopes()->whereKey($calon->id)->update(['person_id' => $person->id]);
        });
    }

    /**
     * Read an `encrypted`-cast attribute's raw column value and decrypt it, bypassing the
     * role model's `person`-proxying accessor (which returns null here since `person_id`
     * is still null for the row being backfilled).
     */
    private function decryptRawAttribute(Guru|CalonMurid $model, string $key): ?string
    {
        $raw = $model->getRawOriginal($key);

        if ($raw === null) {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    /** @param array<string, mixed> $extra */
    private function findOrCreatePerson(int $yayasanId, ?string $nik, string $namaLengkap, array $extra, string $sourceTable, int $sourceId): Person
    {
        $nikHash = $nik ? hash('sha256', $nik) : null;

        if ($nikHash !== null) {
            $existing = Person::withoutGlobalScopes()
                ->where('yayasan_id', $yayasanId)
                ->where('nik_hash', $nikHash)
                ->first();

            if ($existing !== null) {
                foreach ($this->tablesLinkedToPerson($existing->id) as $linkedRow) {
                    $this->seenThisRun[] = ['table' => $linkedRow['table'], 'row_id' => $linkedRow['id'], 'nik_hash' => $nikHash, 'yayasan_id' => $yayasanId];
                }

                $this->seenThisRun[] = ['table' => $sourceTable, 'row_id' => $sourceId, 'nik_hash' => $nikHash, 'yayasan_id' => $yayasanId];

                return $existing;
            }
        }

        return Person::withoutGlobalScopes()->create(array_merge($extra, [
            'yayasan_id' => $yayasanId,
            'nik' => $nik,
            'nama_lengkap' => $namaLengkap,
        ]));
    }

    /**
     * Determine which rows, across the five role tables, already point their person_id at the given Person.
     * Only called on an actual NIK collision, so the extra per-table lookups are cheap in practice.
     *
     * @return array<int, array{table: string, id: int}>
     */
    private function tablesLinkedToPerson(int $personId): array
    {
        $tables = [
            'guru' => Guru::class,
            'karyawan' => Karyawan::class,
            'orang_tua' => OrangTua::class,
            'siswa' => Siswa::class,
            'calon_murid' => CalonMurid::class,
        ];

        $linked = [];

        foreach ($tables as $tableName => $modelClass) {
            foreach ($modelClass::withoutGlobalScopes()->where('person_id', $personId)->pluck('id') as $rowId) {
                $linked[] = ['table' => $tableName, 'id' => $rowId];
            }
        }

        return $linked;
    }

    private function reportCollisions(): void
    {
        $byHash = collect($this->seenThisRun)->groupBy(fn (array $row) => $row['yayasan_id'].'|'.$row['nik_hash']);

        foreach ($byHash as $group) {
            // Key on (table, row_id) rather than table name alone: two distinct rows from the
            // *same* table (e.g. two orang_tua records) sharing a NIK is still a real collision
            // when that table has no DB-level unique constraint on the NIK column.
            $distinctRows = $group->unique(fn (array $row) => $row['table'].'|'.$row['row_id']);

            if ($distinctRows->count() < 2) {
                continue;
            }

            $tables = $distinctRows->pluck('table')->unique()->values();

            $this->warn("NIK collision within yayasan_id={$group->first()['yayasan_id']}: same NIK shared across [{$tables->implode(', ')}]. Person rows were reused, not merged -- review manually.");
        }
    }
}
