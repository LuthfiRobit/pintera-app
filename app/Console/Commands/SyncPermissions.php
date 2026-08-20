<?php

namespace App\Console\Commands;

use App\Services\PermissionUsageScanner;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Scan app/Http/Controllers, app/Http/Requests, app/Policies, and resources/views for authorize()/can()/canAny()/@can/@canany permission usage, and sync them into the permissions table';

    public function handle(PermissionUsageScanner $scanner): int
    {
        $found = array_keys($scanner->scanCodeUsage([
            'app/Http/Controllers',
            'app/Http/Requests',
            'app/Policies',
            'resources/views',
        ]));

        $createdCount = 0;
        foreach ($found as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permission->wasRecentlyCreated) {
                $this->info("Created permission: {$name}");
                $createdCount++;
            }
        }

        $stale = Permission::where('guard_name', 'web')
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, $found, true))
            ->values();

        if ($stale->isNotEmpty()) {
            $this->warn('Permissions in database but not referenced by any controller/request/policy/view (not deleted automatically):');
            foreach ($stale as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($createdCount === 0 && $stale->isEmpty()) {
            $this->info('Permissions already in sync.');
        }

        return self::SUCCESS;
    }
}
