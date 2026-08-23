<?php
// database/seeders/TagihanItemSeeder.php

namespace Database\Seeders;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use Illuminate\Database\Seeder;

class TagihanItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Biaya Pendaftaran')->first();
            $jenisDaftarUlang = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Uang Pangkal')->first();

            if (! $jenisPendaftaran || ! $jenisDaftarUlang) {
                continue;
            }

            $tagihanList = Tagihan::whereHas('pendaftaran', fn ($q) => $q->where('lembaga_id', $lembaga->id))->get();

            foreach ($tagihanList as $tagihan) {
                $jenisTagihan = $tagihan->kategori === 'pendaftaran' ? $jenisPendaftaran : $jenisDaftarUlang;

                TagihanItem::firstOrCreate(
                    ['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id],
                    ['jumlah' => $tagihan->total_tagihan]
                );
            }
        }
    }
}
