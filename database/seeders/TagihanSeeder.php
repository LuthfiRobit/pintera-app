<?php
// database/seeders/TagihanSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class TagihanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Reguler')
                ->first();

            if (! $jalur) {
                continue;
            }

            $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Biaya Pendaftaran')->first();
            $jenisDaftarUlang = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Uang Pangkal')->first();

            if (! $jenisPendaftaran || ! $jenisDaftarUlang) {
                continue;
            }

            $nominalPendaftaran = NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)->where('jalur_ppdb_id', $jalur->id)->first();
            $nominalDaftarUlang = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlang->id)->where('jalur_ppdb_id', $jalur->id)->first();

            if (! $nominalPendaftaran || ! $nominalDaftarUlang) {
                continue;
            }

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();

            if ($diterima) {
                Tagihan::firstOrCreate(
                    ['pendaftaran_id' => $diterima->id, 'kategori' => 'pendaftaran'],
                    [
                        'tagihable_type' => Pendaftaran::class,
                        'tagihable_id' => $diterima->id,
                        'total_tagihan' => $nominalPendaftaran->nominal,
                        'net_amount' => $nominalPendaftaran->nominal,
                        'status' => 'belum_bayar',
                        'person_id' => $diterima->calonMurid->person_id,
                    ]
                );
                Tagihan::firstOrCreate(
                    ['pendaftaran_id' => $diterima->id, 'kategori' => 'daftar_ulang'],
                    [
                        'tagihable_type' => Pendaftaran::class,
                        'tagihable_id' => $diterima->id,
                        'total_tagihan' => $nominalDaftarUlang->nominal,
                        'net_amount' => $nominalDaftarUlang->nominal,
                        'status' => 'belum_bayar',
                        'person_id' => $diterima->calonMurid->person_id,
                    ]
                );
            }

            if ($cicilanDemo) {
                Tagihan::firstOrCreate(
                    ['pendaftaran_id' => $cicilanDemo->id, 'kategori' => 'daftar_ulang'],
                    [
                        'tagihable_type' => Pendaftaran::class,
                        'tagihable_id' => $cicilanDemo->id,
                        'total_tagihan' => $nominalDaftarUlang->nominal,
                        'net_amount' => $nominalDaftarUlang->nominal,
                        'status' => 'belum_bayar',
                        'person_id' => $cicilanDemo->calonMurid->person_id,
                    ]
                );
            }
        }
    }
}
