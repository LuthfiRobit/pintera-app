<?php

namespace App\Support;

use Throwable;

class ErrorPageMessage
{
    /**
     * Pesan default framework yang secara teknis "tidak kosong" tapi bocorkan detail internal
     * (nama class model) — tidak boleh ditampilkan apa adanya ke user (dari ModelNotFoundException
     * yang dikonversi Laravel jadi NotFoundHttpException, mis. route-model-binding gagal).
     */
    private const LEAKY_PREFIXES = [
        'No query results for model',
        'The route',
    ];

    public static function resolve(?Throwable $exception, string $fallback, bool $allowDynamic = true): string
    {
        if (! $allowDynamic || $exception === null) {
            return $fallback;
        }

        $message = trim($exception->getMessage());

        if ($message === '') {
            return $fallback;
        }

        foreach (self::LEAKY_PREFIXES as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return $fallback;
            }
        }

        return $message;
    }
}
