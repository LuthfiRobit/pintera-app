@props(['langkah'])

@php
    $daftarLangkah = [
        1 => 'Verifikasi Email',
        2 => 'Data Diri',
        3 => 'Formulir Tambahan',
        4 => 'Upload Dokumen',
        5 => 'Review',
        6 => 'Selesai',
    ];
    $total = count($daftarLangkah);
    $persen = (int) round(($langkah / $total) * 100);
@endphp

<nav aria-label="Tahapan pendaftaran" class="mb-6">
    <ol class="flex items-center">
        @foreach ($daftarLangkah as $nomor => $label)
            <li class="flex items-center {{ $nomor < $total ? 'flex-1' : '' }}">
                <span
                    @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-signal-green text-white' => $nomor < $langkah,
                        'bg-brass text-white' => $nomor === $langkah,
                        'bg-slate/20 text-slate' => $nomor > $langkah,
                    ])
                    title="{{ $label }}"
                >
                    @if ($nomor < $langkah)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    @else
                        {{ $nomor }}
                    @endif
                </span>
                @if ($nomor < $total)
                    <span @class(['mx-2 h-0.5 flex-1', 'bg-signal-green' => $nomor < $langkah, 'bg-slate/20' => $nomor >= $langkah])></span>
                @endif
            </li>
        @endforeach
    </ol>
    <p class="mt-2 text-center text-xs font-medium text-slate">
        Tahap {{ $langkah }}: {{ $daftarLangkah[$langkah] }} — {{ $persen }}%
    </p>
</nav>
