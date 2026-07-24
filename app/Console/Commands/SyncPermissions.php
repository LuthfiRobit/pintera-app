<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Scan app/Http/Controllers for $this->authorize() calls and sync them into the permissions table';

    public function handle(): int
    {
        $found = $this->scanControllers();

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
            $this->warn('Permissions in database but not referenced by any controller (not deleted automatically):');
            foreach ($stale as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($createdCount === 0 && $stale->isEmpty()) {
            $this->info('Permissions already in sync.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function scanControllers(): array
    {
        $files = File::allFiles(app_path('Http/Controllers'));
        $names = [];

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all('/\$this->authorize\(\s*[\'"]([a-z0-9\-]+\.[a-z0-9\-]+)[\'"]\s*\)/', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $names[$match] = $match;
                }
            }
        }

        return array_values($names);
    }
}
