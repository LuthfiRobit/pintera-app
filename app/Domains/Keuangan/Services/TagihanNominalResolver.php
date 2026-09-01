<?php

// app/Domains/Keuangan/Services/TagihanNominalResolver.php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\NominalTagihanSiswa;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Models\Siswa;

class TagihanNominalResolver
{
    public function __construct(private readonly JenisTagihanSasaranMatcher $matcher) {}

    /**
     * @return array{nominal: float, discount_amount: float, discount_type: ?string}
     */
    public function resolve(Siswa $siswa, JenisTagihan $jenisTagihan): array
    {
        $nominal = $this->resolveNominal($siswa, $jenisTagihan);
        [$discountAmount, $discountType] = $this->resolveDiscount($siswa, $jenisTagihan, $nominal);

        return [
            'nominal' => $nominal,
            'discount_amount' => $discountAmount,
            'discount_type' => $discountType,
        ];
    }

    private function resolveNominal(Siswa $siswa, JenisTagihan $jenisTagihan): float
    {
        $override = NominalTagihanSiswa::where('jenis_tagihan_id', $jenisTagihan->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($override) {
            return (float) $override->nominal;
        }

        $tarifGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('priority')->get();

        foreach ($tarifGrups as $grup) {
            if ($this->matcher->siswaMatchesGrup($siswa, $grup)) {
                return (float) $grup->nominal;
            }
        }

        return (float) ($jenisTagihan->default_amount ?? 0);
    }

    /**
     * @return array{0: float, 1: ?string}
     */
    private function resolveDiscount(Siswa $siswa, JenisTagihan $jenisTagihan, float $nominal): array
    {
        $today = now()->toDateString();

        $kategoriIds = SiswaKeringanan::where('siswa_id', $siswa->id)
            ->where('berlaku_dari', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today);
            })
            ->pluck('kategori_keringanan_id');

        if ($kategoriIds->isEmpty()) {
            return [0.0, null];
        }

        $rules = JenisTagihanKeringanan::where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereIn('kategori_keringanan_id', $kategoriIds)
            ->with('kategoriKeringanan')
            ->get();

        $bestNonCombinable = 0.0;
        $bestType = null;
        $totalCombinable = 0.0;

        foreach ($rules as $rule) {
            $amount = $rule->tipe_potongan === 'persen'
                ? round($nominal * ((float) $rule->nilai) / 100, 2)
                : (float) $rule->nilai;

            if ($rule->kategoriKeringanan->bisa_digabung) {
                $totalCombinable += $amount;

                continue;
            }

            if ($amount > $bestNonCombinable) {
                $bestNonCombinable = $amount;
                $bestType = $rule->tipe_potongan;
            }
        }

        $totalDiscount = min($nominal, $bestNonCombinable + $totalCombinable);
        $discountType = $bestType ?? ($totalCombinable > 0 ? 'gabungan' : null);

        return [$totalDiscount, $discountType];
    }
}
