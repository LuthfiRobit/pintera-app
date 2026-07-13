<?php

namespace App\Services;

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;

class SpmbKonfigurasiDuplikasi
{
    /**
     * @return array{gelombang: int, jalur: int, formulir_field: int, dokumen_syarat: int, seleksi: int}
     */
    public function duplikasi(TahunAjaran $sumber, TahunAjaran $tujuan): array
    {
        return DB::transaction(function () use ($sumber, $tujuan) {
            $jumlah = ['gelombang' => 0, 'jalur' => 0, 'formulir_field' => 0, 'dokumen_syarat' => 0, 'seleksi' => 0];

            $pemetaanGelombang = [];
            foreach (GelombangPpdb::where('tahun_ajaran_id', $sumber->id)->get() as $gelombangLama) {
                $gelombangBaru = GelombangPpdb::create([
                    'lembaga_id' => $tujuan->lembaga_id,
                    'tahun_ajaran_id' => $tujuan->id,
                    'nama' => $gelombangLama->nama,
                    'tanggal_buka' => $gelombangLama->tanggal_buka->copy()->addYear(),
                    'tanggal_tutup' => $gelombangLama->tanggal_tutup->copy()->addYear(),
                    'kuota' => $gelombangLama->kuota,
                ]);
                $pemetaanGelombang[$gelombangLama->id] = $gelombangBaru->id;
                $jumlah['gelombang']++;
            }

            foreach (JalurPpdb::where('tahun_ajaran_id', $sumber->id)->get() as $jalurLama) {
                $jalurBaru = JalurPpdb::create([
                    'lembaga_id' => $tujuan->lembaga_id,
                    'tahun_ajaran_id' => $tujuan->id,
                    'nama' => $jalurLama->nama,
                    'deskripsi' => $jalurLama->deskripsi,
                    'status_aktif' => $jalurLama->status_aktif,
                ]);
                $jumlah['jalur']++;

                foreach ($jalurLama->formulirField as $field) {
                    FormulirField::create([
                        'jalur_ppdb_id' => $jalurBaru->id,
                        'label' => $field->label,
                        'field_type' => $field->field_type,
                        'options' => $field->options,
                        'is_required' => $field->is_required,
                        'urutan' => $field->urutan,
                    ]);
                    $jumlah['formulir_field']++;
                }

                foreach ($jalurLama->dokumenSyarat as $dokumen) {
                    DokumenSyaratPpdb::create([
                        'jalur_ppdb_id' => $jalurBaru->id,
                        'nama_dokumen' => $dokumen->nama_dokumen,
                        'wajib' => $dokumen->wajib,
                        'urutan' => $dokumen->urutan,
                    ]);
                    $jumlah['dokumen_syarat']++;
                }

                foreach ($jalurLama->seleksi as $seleksi) {
                    if (! isset($pemetaanGelombang[$seleksi->gelombang_ppdb_id])) {
                        continue;
                    }

                    SeleksiPpdb::create([
                        'jalur_ppdb_id' => $jalurBaru->id,
                        'gelombang_ppdb_id' => $pemetaanGelombang[$seleksi->gelombang_ppdb_id],
                        'jenis_tes_master_id' => $seleksi->jenis_tes_master_id,
                        'jadwal' => $seleksi->jadwal->copy()->addYear(),
                        'kriteria_kelulusan' => $seleksi->kriteria_kelulusan,
                        'bobot' => $seleksi->bobot,
                    ]);
                    $jumlah['seleksi']++;
                }
            }

            return $jumlah;
        });
    }
}
