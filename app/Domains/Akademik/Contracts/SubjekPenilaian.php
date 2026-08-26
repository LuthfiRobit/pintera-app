<?php

namespace App\Domains\Akademik\Contracts;

/**
 * Marker interface: menandai model yang boleh jadi target morph `subjek`
 * pada KomponenPenilaian/Asesmen (MataPelajaran, ElemenCp). Sengaja tanpa
 * method -- kontrak "punya properti nama" tidak bisa dideklarasikan sbg
 * interface method tanpa memaksa accessor eksplisit yang saat ini tidak
 * dibutuhkan caller manapun. Tambah method hanya kalau ada caller nyata
 * yang butuh kontrak method (bukan properti Eloquent biasa).
 */
interface SubjekPenilaian
{
}
