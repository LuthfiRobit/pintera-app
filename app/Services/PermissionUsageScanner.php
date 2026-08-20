<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

final class PermissionUsageScanner
{
    private const PATTERN_SINGLE = '/(?:->authorize\(|->can\()\s*[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]\s*\)/';
    private const PATTERN_CAN_ANY = '/->canAny\(\s*\[(.*?)\]\s*\)/s';
    private const PATTERN_BLADE_CAN = '/@can\(\s*[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]\s*\)/';
    private const PATTERN_BLADE_CAN_ANY = '/@canany\(\s*\[(.*?)\]\s*\)/s';
    private const PATTERN_ITEM = '/[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]/';

    /**
     * Pindai penggunaan permission (authorize()/can()/canAny() di PHP, @can/@canany di blade)
     * di seluruh direktori yang diberikan.
     *
     * @param  array<int, string>  $directories  path relatif dari base_path(), mis. 'app/Http/Controllers'
     * @return array<string, array<int, string>> nama permission => daftar file path yang memakainya
     */
    public function scanCodeUsage(array $directories): array
    {
        $found = [];

        foreach ($directories as $directory) {
            $absolute = base_path($directory);
            if (! File::isDirectory($absolute)) {
                continue;
            }

            foreach (File::allFiles($absolute) as $file) {
                $extension = $file->getExtension();
                if ($extension !== 'php') {
                    continue;
                }

                $contents = File::get($file->getPathname());
                $isBlade = str_ends_with($file->getFilename(), '.blade.php');

                $singlePattern = $isBlade ? self::PATTERN_BLADE_CAN : self::PATTERN_SINGLE;
                $anyPattern = $isBlade ? self::PATTERN_BLADE_CAN_ANY : self::PATTERN_CAN_ANY;

                $this->collectSingle($contents, $singlePattern, $file->getPathname(), $found);
                $this->collectFromArrayCalls($contents, $anyPattern, $file->getPathname(), $found);
            }
        }

        return $found;
    }

    /**
     * Pindai permission yang terdaftar di file seeder (mis. Permission::firstOrCreate(['name' => $name, ...])
     * di dalam sebuah loop atas array literal) - mengekstrak SEMUA string bertitik dalam file itu,
     * bukan cuma yang langsung jadi argumen firstOrCreate(), karena permission biasanya didaftarkan
     * lewat variabel array, bukan literal langsung di pemanggilan firstOrCreate().
     *
     * @param  array<int, string>  $seederFiles  path relatif dari base_path() ke file seeder spesifik
     * @return array<string, array<int, string>>
     */
    public function scanSeederRegistrations(array $seederFiles): array
    {
        $found = [];

        foreach ($seederFiles as $relativePath) {
            $absolute = base_path($relativePath);
            if (! File::exists($absolute)) {
                continue;
            }

            $contents = File::get($absolute);
            if (preg_match_all(self::PATTERN_ITEM, $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    $found[$name][] = $absolute;
                }
            }
        }

        return $found;
    }

    private function collectSingle(string $contents, string $pattern, string $path, array &$found): void
    {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $name) {
                $found[$name][] = $path;
            }
        }
    }

    private function collectFromArrayCalls(string $contents, string $pattern, string $path, array &$found): void
    {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $arrayContent) {
                if (preg_match_all(self::PATTERN_ITEM, $arrayContent, $itemMatches)) {
                    foreach ($itemMatches[1] as $name) {
                        $found[$name][] = $path;
                    }
                }
            }
        }
    }
}
