<?php
// app/Services/PermissionAuditService.php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class PermissionAuditService
{
    private const PATTERN = '/\b(?:authorize|can)\(\s*\'([a-z0-9\-\.]+)\'/';

    private array $scanDirectories;

    public function __construct(?array $scanDirectories = null)
    {
        $this->scanDirectories = $scanDirectories ?? [
            app_path('Http/Controllers'),
            resource_path('views'),
        ];
    }

    public function audit(): array
    {
        $usedInCode = $this->scanCodeForPermissionNames();
        $inDatabase = Permission::pluck('name')->all();

        $missingFromDatabase = array_values(array_diff($usedInCode, $inDatabase));
        $unusedInCode = array_values(array_diff($inDatabase, $usedInCode));

        foreach ($missingFromDatabase as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        sort($missingFromDatabase);
        sort($unusedInCode);

        return [
            'missingFromDatabase' => $missingFromDatabase,
            'unusedInCode' => $unusedInCode,
        ];
    }

    private function scanCodeForPermissionNames(): array
    {
        $names = [];

        foreach ($this->filesToScan() as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all(self::PATTERN, $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    private function filesToScan(): iterable
    {
        $files = collect();

        foreach ($this->scanDirectories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            $files = $files->concat(
                collect(File::allFiles($directory))->filter(fn ($file) => $file->getExtension() === 'php')
            );
        }

        return $files;
    }
}
