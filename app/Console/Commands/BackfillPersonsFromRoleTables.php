<?php

namespace App\Console\Commands;

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\OrangTua;
use App\Models\Siswa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPersonsFromRoleTables extends Command
{
    protected $signature = 'identity:backfill-persons';

    protected $description = 'Backfill the persons master table from guru/karyawan/orang_tua/siswa/calon_murid rows.';

    /** @var array<int, array{table: string, nik_hash: ?string, yayasan_id: int}> */
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
                nik: $guru->nik,
                namaLengkap: $guru->nama,
                extra: [
                    'jenis_kelamin' => $guru->jenis_kelamin,
                    'tempat_lahir' => $guru->tempat_lahir,
                    'tanggal_lahir' => $guru->tanggal_lahir,
                    'agama' => $guru->agama,
                    'no_hp' => $guru->no_hp,
                    'email' => $guru->email,
                ],
                sourceTable: 'guru',
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
                nik: $karyawan->nik,
                namaLengkap: $karyawan->nama,
                extra: ['no_hp' => $karyawan->no_hp, 'email' => $karyawan->email],
                sourceTable: 'karyawan',
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
                nik: $ortu->nik,
                namaLengkap: $ortu->nama_lengkap,
                extra: ['no_hp' => $ortu->no_hp, 'email' => $ortu->email, 'alamat_jalan' => $ortu->alamat],
                sourceTable: 'orang_tua',
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
                namaLengkap: $siswa->nama_lengkap,
                extra: [
                    'jenis_kelamin' => $siswa->jenis_kelamin,
                    'tempat_lahir' => $siswa->tempat_lahir,
                    'tanggal_lahir' => $siswa->tanggal_lahir,
                ],
                sourceTable: 'siswa',
            );

            $siswa->newQueryWithoutScopes()->whereKey($siswa->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillCalonMurid(): void
    {
        CalonMurid::withoutGlobalScopes()->whereNull('person_id')->get()->each(function (CalonMurid $calon) {
            $person = $this->findOrCreatePerson(
                yayasanId: $calon->yayasan_id,
                nik: $calon->nik,
                namaLengkap: $calon->nama_lengkap,
                extra: [
                    'jenis_kelamin' => $calon->jenis_kelamin,
                    'tempat_lahir' => $calon->tempat_lahir,
                    'tanggal_lahir' => $calon->tanggal_lahir,
                    'agama' => $calon->agama,
                    'no_hp' => $calon->no_telepon,
                    'email' => $calon->email_kontak,
                ],
                sourceTable: 'calon_murid',
            );

            $calon->newQueryWithoutScopes()->whereKey($calon->id)->update(['person_id' => $person->id]);
        });
    }

    /** @param array<string, mixed> $extra */
    private function findOrCreatePerson(int $yayasanId, ?string $nik, string $namaLengkap, array $extra, string $sourceTable): Person
    {
        $nikHash = $nik ? hash('sha256', $nik) : null;

        if ($nikHash !== null) {
            $existing = Person::withoutGlobalScopes()
                ->where('yayasan_id', $yayasanId)
                ->where('nik_hash', $nikHash)
                ->first();

            if ($existing !== null) {
                $this->seenThisRun[] = ['table' => $sourceTable, 'nik_hash' => $nikHash, 'yayasan_id' => $yayasanId];

                return $existing;
            }
        }

        return Person::withoutGlobalScopes()->create(array_merge($extra, [
            'yayasan_id' => $yayasanId,
            'nik' => $nik,
            'nama_lengkap' => $namaLengkap,
        ]));
    }

    private function reportCollisions(): void
    {
        $byHash = collect($this->seenThisRun)->groupBy(fn (array $row) => $row['yayasan_id'].'|'.$row['nik_hash']);

        foreach ($byHash as $group) {
            if ($group->count() < 1) {
                continue;
            }

            $tables = $group->pluck('table')->unique()->implode(', ');
            $this->warn("NIK collision within yayasan_id={$group->first()['yayasan_id']}: same NIK shared across [{$tables}]. Person rows were reused, not merged -- review manually.");
        }
    }
}
