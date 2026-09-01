<?php

// app/Services/TagihanGenerator.php

namespace App\Services;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use App\Models\Pendaftaran;
use Illuminate\Support\Facades\DB;

class TagihanGenerator
{
    /**
     * The single source of truth for invoice creation. Idempotent per
     * (pendaftaran_id, kategori) — a second call for the same pair creates
     * nothing and returns null, backed by the tagihan table's own unique
     * constraint as well as this explicit check. Never creates a header with
     * zero qualifying line items: a genuinely-free tagihan (every configured
     * item is Rp 0) and an unconfigured one (no items configured at all) must
     * never be indistinguishable — the former is created and marked lunas,
     * the latter creates nothing at all.
     */
    public function generate(Pendaftaran $pendaftaran, string $kategori): ?Tagihan
    {
        if (Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', $kategori)->exists()) {
            return null;
        }

        $jenisTagihanList = JenisTagihan::where('lembaga_id', $pendaftaran->lembaga_id)
            ->where('kategori', $kategori)
            ->get();

        $items = [];

        foreach ($jenisTagihanList as $jenisTagihan) {
            $nominal = NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)
                ->where('jalur_ppdb_id', $pendaftaran->jalur_ppdb_id)
                ->first();

            if (! $nominal) {
                continue;
            }

            $items[] = ['jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => $nominal->nominal];
        }

        if (empty($items)) {
            return null;
        }

        $total = array_sum(array_column($items, 'jumlah'));

        return DB::transaction(function () use ($pendaftaran, $kategori, $items, $total) {
            $personId = $pendaftaran->calonMurid?->person_id
                ?? throw new \RuntimeException("Tidak bisa membuat tagihan: Pendaftaran #{$pendaftaran->id} tidak punya CalonMurid dengan person_id yang valid — data kemungkinan cacat.");

            $tagihan = Tagihan::create([
                'pendaftaran_id' => $pendaftaran->id,
                'tagihable_type' => Pendaftaran::class,
                'tagihable_id' => $pendaftaran->id,
                'person_id' => $personId,
                'kategori' => $kategori,
                'total_tagihan' => $total,
                'net_amount' => $total,
                'status' => $total == 0 ? 'lunas' : 'belum_bayar',
            ]);

            foreach ($items as $item) {
                TagihanItem::create(array_merge(['tagihan_id' => $tagihan->id], $item));
            }

            return $tagihan;
        });
    }
}
