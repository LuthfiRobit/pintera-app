<?php

namespace Tests\Feature\Admin;

use App\Imports\SiswaImportRow;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_all_generates_predicted_account_credentials(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMP1']);
        Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '7A']);

        $rows = collect([
            collect([
                'nama_lengkap' => 'Ahmad Faisal',
                'nis' => '1001',
                'nisn' => '0051234567',
                'jenis_kelamin' => 'L',
                'kelas' => '7A',
            ]),
            collect([
                'nama_lengkap' => 'Budi Santoso',
                'nis' => '1002',
                'nisn' => '',
                'jenis_kelamin' => 'L',
                'kelas' => '7A',
            ])
        ]);

        $results = SiswaImportRow::parseAll($rows, $lembaga->id);

        $this->assertCount(2, $results['valid']);
        
        $this->assertArrayHasKey('predicted_username', $results['valid'][0]);
        $this->assertArrayHasKey('predicted_password', $results['valid'][0]);
        $this->assertEquals('SMP1-1001', $results['valid'][0]['predicted_username']);
        $this->assertEquals('1001', $results['valid'][0]['predicted_password']);
        
        $this->assertEquals('SMP1-1002', $results['valid'][1]['predicted_username']);
        $this->assertEquals('1002', $results['valid'][1]['predicted_password']);
    }
}
