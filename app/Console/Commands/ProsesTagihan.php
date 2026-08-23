<?php
// app/Console/Commands/ProsesTagihan.php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Services\TagihanBillingGenerator;
use Illuminate\Console\Command;

class ProsesTagihan extends Command
{
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    protected $signature = 'billing:proses {jenis_tagihan_id}';

    protected $description = 'Generate tagihan manually for one jenis_tagihan (admin "Proses Tagihan" button, or backfill/testing)';

    public function handle(TagihanBillingGenerator $generator): int
    {
        $jenisTagihan = JenisTagihan::find($this->argument('jenis_tagihan_id'));

        if (! $jenisTagihan) {
            $this->error('Jenis tagihan tidak ditemukan.');

            return self::FAILURE;
        }

        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            $this->error("Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.");

            return self::FAILURE;
        }

        $log = $generator->generate($jenisTagihan, 'manual');

        $this->info("{$log->bills_generated} tagihan dibuat. Status: {$log->status}.");

        return self::SUCCESS;
    }
}
