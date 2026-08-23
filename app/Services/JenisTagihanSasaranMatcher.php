<?php
// app/Services/JenisTagihanSasaranMatcher.php

namespace App\Services;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JenisTagihanSasaranMatcher
{
    /**
     * @return Collection<int, Siswa>
     */
    public function resolveTargetSiswa(JenisTagihan $jenisTagihan): Collection
    {
        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        $query = Siswa::withoutGlobalScope(TenantScope::class)
            ->with('kelas')
            ->where('lembaga_id', $jenisTagihan->lembaga_id);

        if ($sasaranGrups->isNotEmpty()) {
            $query->where(function (Builder $outer) use ($sasaranGrups) {
                foreach ($sasaranGrups as $grup) {
                    $outer->orWhere(function (Builder $inner) use ($grup) {
                        foreach ($grup->kriteria as $kriteria) {
                            $this->applyKriteriaToQuery($inner, $kriteria);
                        }
                    });
                }
            });
        }

        return $query->get();
    }

    public function countTotalSiswaPool(JenisTagihan $jenisTagihan): int
    {
        return Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $jenisTagihan->lembaga_id)
            ->count();
    }

    public function siswaMatchesGrup(Siswa $siswa, JenisTagihanSasaranGrup $grup): bool
    {
        foreach ($grup->kriteria as $kriteria) {
            if (! $this->siswaMatchesKriteria($siswa, $kriteria)) {
                return false;
            }
        }

        return true;
    }

    public function siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool
    {
        if ($siswa->lembaga_id !== $jenisTagihan->lembaga_id) {
            return false;
        }

        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        if ($sasaranGrups->isEmpty()) {
            return true;
        }

        foreach ($sasaranGrups as $grup) {
            if ($this->siswaMatchesGrup($siswa, $grup)) {
                return true;
            }
        }

        return false;
    }

    private function applyKriteriaToQuery(Builder $query, JenisTagihanSasaranKriteria $kriteria): void
    {
        $values = $kriteria->value;
        $isIn = $kriteria->operator === 'in';

        switch ($kriteria->field) {
            case 'lembaga':
                $isIn ? $query->whereIn('lembaga_id', $values) : $query->whereNotIn('lembaga_id', $values);
                break;
            case 'kelas':
                if ($isIn) {
                    $query->whereIn('kelas_id', $values);
                } else {
                    // A siswa with no kelas assigned does not have any of the
                    // excluded kelas, so it must match `not_in` — mirroring
                    // siswaMatchesKriteria()'s PHP-side null handling. Grouped
                    // in a nested where() so this stays AND-scoped to the
                    // enclosing grup regardless of outer OR nesting.
                    $query->where(function (Builder $q) use ($values) {
                        $q->whereNotIn('kelas_id', $values)->orWhereNull('kelas_id');
                    });
                }
                break;
            case 'jenis_kelamin':
                $isIn ? $query->whereIn('jenis_kelamin', $values) : $query->whereNotIn('jenis_kelamin', $values);
                break;
            case 'status_siswa':
                $isIn ? $query->whereIn('status', $values) : $query->whereNotIn('status', $values);
                break;
            case 'tahun_ajaran':
                $isIn
                    ? $query->whereHas('kelas', fn (Builder $k) => $k->whereIn('tahun_ajaran_id', $values))
                    : $query->whereDoesntHave('kelas', fn (Builder $k) => $k->whereIn('tahun_ajaran_id', $values));
                break;
            case 'tingkat':
                $isIn
                    ? $query->whereHas('kelas', fn (Builder $k) => $k->whereIn('tingkat', $values))
                    : $query->whereDoesntHave('kelas', fn (Builder $k) => $k->whereIn('tingkat', $values));
                break;
        }
    }

    private function siswaMatchesKriteria(Siswa $siswa, JenisTagihanSasaranKriteria $kriteria): bool
    {
        $actual = match ($kriteria->field) {
            'lembaga' => $siswa->lembaga_id,
            'kelas' => $siswa->kelas_id,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'status_siswa' => $siswa->status->value,
            'tahun_ajaran' => $siswa->kelas?->tahun_ajaran_id,
            'tingkat' => $siswa->kelas?->tingkat,
        };

        $inList = in_array($actual, $kriteria->value);

        return $kriteria->operator === 'in' ? $inList : ! $inList;
    }
}
