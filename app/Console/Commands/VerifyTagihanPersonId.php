<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Console\Command;

/**
 * Kept as deployment tooling for the same reason as BackfillTagihanPersonId:
 * a fresh environment restoring an older dump must confirm this exits 0
 * before running the NOT NULL migration (2026_09_01_000002). See that
 * command's class comment.
 */
class VerifyTagihanPersonId extends Command
{
    protected $signature = 'keuangan:verify-tagihan-person-id';

    protected $description = 'Verify that no tagihan row has a null person_id (must exit 0 before the NOT NULL migration runs).';

    public function handle(): int
    {
        $nullIds = Tagihan::whereNull('person_id')->pluck('id');

        if ($nullIds->isEmpty()) {
            $this->info('Semua baris tagihan sudah punya person_id.');

            return self::SUCCESS;
        }

        $this->error("{$nullIds->count()} baris tagihan masih person_id NULL: ".$nullIds->join(', '));

        return self::FAILURE;
    }
}
