<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kelas;

use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Services\FaseDefaultResolver;
use App\Domains\Akademik\Services\KurikulumAssignmentResolver;
use App\Models\Kelas;
use App\Models\Lembaga;
use Illuminate\Support\Facades\DB;

final class ResyncKurikulumFaseKelasAction
{
    public function __construct(
        private readonly KurikulumAssignmentResolver $kurikulumResolver,
        private readonly FaseDefaultResolver $faseResolver,
    ) {}

    /**
     * @return array<int, array{kelas: Kelas, kurikulumLama: ?string, kurikulumBaru: ?string, faseLamaId: ?int, faseBaruId: ?int, faseBaruNama: ?string}>
     */
    public function hitungDiff(int $lembagaId, int $tahunAjaranId): array
    {
        $lembaga = Lembaga::findOrFail($lembagaId);
        $kelasList = Kelas::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaranId)->get();

        $diff = [];

        foreach ($kelasList as $kelas) {
            try {
                $kurikulumBaru = $this->kurikulumResolver->resolve(
                    tahunAjaranId: $tahunAjaranId,
                    bentukPendidikan: $lembaga->bentuk_pendidikan,
                    tingkat: $kelas->tingkat,
                    lembagaId: $lembagaId,
                );
            } catch (KurikulumAssignmentNotFoundException) {
                continue;
            }

            $faseBaru = $this->faseResolver->resolve(
                bentukPendidikan: $lembaga->bentuk_pendidikan,
                tingkat: $kelas->tingkat,
                lembagaId: $lembagaId,
            );

            $kurikulumLamaValue = $kelas->kurikulum?->value;
            $kurikulumBaruValue = $kurikulumBaru->value;
            $faseLamaId = $kelas->fase_id;
            $faseBaruId = $faseBaru?->id;

            if ($kurikulumLamaValue === $kurikulumBaruValue && $faseLamaId === $faseBaruId) {
                continue;
            }

            $diff[] = [
                'kelas' => $kelas,
                'kurikulumLama' => $kurikulumLamaValue,
                'kurikulumBaru' => $kurikulumBaruValue,
                'faseLamaId' => $faseLamaId,
                'faseBaruId' => $faseBaruId,
                'faseBaruNama' => $faseBaru?->nama,
            ];
        }

        return $diff;
    }

    /**
     * @param  array<int, int>  $kelasIds
     */
    public function terapkan(array $kelasIds): void
    {
        DB::transaction(function () use ($kelasIds) {
            foreach (Kelas::whereIn('id', $kelasIds)->get() as $kelas) {
                $lembaga = Lembaga::findOrFail($kelas->lembaga_id);

                try {
                    $kurikulumBaru = $this->kurikulumResolver->resolve(
                        tahunAjaranId: $kelas->tahun_ajaran_id,
                        bentukPendidikan: $lembaga->bentuk_pendidikan,
                        tingkat: $kelas->tingkat,
                        lembagaId: $kelas->lembaga_id,
                    );
                } catch (KurikulumAssignmentNotFoundException) {
                    continue;
                }

                $faseBaru = $this->faseResolver->resolve(
                    bentukPendidikan: $lembaga->bentuk_pendidikan,
                    tingkat: $kelas->tingkat,
                    lembagaId: $kelas->lembaga_id,
                );

                $kelas->update([
                    'kurikulum' => $kurikulumBaru,
                    'fase_id' => $faseBaru?->id,
                ]);
            }
        });
    }
}
