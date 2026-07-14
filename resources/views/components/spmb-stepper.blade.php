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

<nav aria-label="Tahapan pendaftaran" class="mb-6 rounded-2xl border border-slate/10 bg-white p-4 shadow-card">
    <ol class="flex items-center">
        @foreach ($daftarLangkah as $nomor => $label)
            <li class="flex items-center {{ $nomor < $total ? 'flex-1' : '' }}">
                <span
                    @class([
                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-4',
                        'bg-signal-green text-white ring-signal-green/15' => $nomor < $langkah,
                        'bg-spmb-primary text-white ring-spmb-accent/20' => $nomor === $langkah,
                        'bg-spmb-bg text-slate ring-transparent' => $nomor > $langkah,
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
                    <span @class(['mx-2 h-0.5 flex-1 rounded-full', 'bg-signal-green' => $nomor < $langkah, 'bg-spmb-bg' => $nomor >= $langkah])></span>
                @endif
            </li>
        @endforeach
    </ol>
    <p class="mt-3 text-center text-xs font-semibold text-spmb-primary">
        Tahap {{ $langkah }}: {{ $daftarLangkah[$langkah] }} <span class="text-slate">— {{ $persen }}%</span>
    </p>
</nav>
