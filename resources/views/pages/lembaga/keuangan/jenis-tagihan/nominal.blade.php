<x-app-layout>
    <div class="mx-auto max-w-xl space-y-4">
        <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-brand-600">
            &larr; Kembali ke Jenis Tagihan
        </a>

        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Nominal &mdash; {{ $jenisTagihan->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jenis-tagihan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jenis Tagihan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Nominal</b>
            </p>
        </div>

        @if (! $tahunAjaranAktif)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-sm text-gray-500">Tidak ada tahun ajaran aktif untuk lembaga ini.</div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <form method="POST" action="{{ route('admin.jenis-tagihan.nominal.store', $jenisTagihan) }}" class="space-y-4">
                    @csrf
                    @foreach ($jalurList as $jalur)
                        <div>
                            <x-input-label :value="$jalur->nama" />
                            <x-text-input type="number" step="0.01" min="0" name="nominal[{{ $jalur->id }}]" class="mt-1.5" :value="old('nominal.'.$jalur->id, $nominalMap[$jalur->id] ?? '')" placeholder="0 untuk gratis" />
                        </div>
                    @endforeach
                    <x-primary-button>Simpan Semua Nominal</x-primary-button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
