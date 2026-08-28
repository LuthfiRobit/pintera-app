<?php

// database/seeders/KurikulumAssignmentSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class KurikulumAssignmentSeeder extends Seeder
{
    /**
     * Tanpa seeder ini, KurikulumAssignmentResolver melempar
     * KurikulumAssignmentNotFoundException begitu admin mencoba membuat Kelas baru
     * lewat CreateKelasAction (setiap kombinasi tahun_ajaran+bentuk_pendidikan wajib
     * punya assignment kurikulum, tidak ada default hardcode di kode).
     */
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                KurikulumAssignment::firstOrCreate(
                    [
                        'lembaga_id' => $lembaga->id,
                        'tahun_ajaran_id' => $tahunAjaran->id,
                        'bentuk_pendidikan' => $lembaga->bentuk_pendidikan,
                        'tingkat' => null,
                    ],
                    [
                        'kurikulum' => KurikulumFramework::Merdeka->value,
                    ]
                );
            }
        }
    }
}
