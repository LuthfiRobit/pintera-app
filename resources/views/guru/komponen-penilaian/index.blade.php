<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Komponen Penilaian</b>
            </p>
        </div>

        {{-- Filter Bar (only shown when the guru teaches more than one mata pelajaran) --}}
        @if ($mataPelajaranList->count() > 1)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Mata Pelajaran" />
                        <select name="mata_pelajaran_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Mata Pelajaran</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected($mataPelajaranId == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        @endif

        <div x-ref="daftarKomponen">
            @include('guru.komponen-penilaian._daftar')
        </div>
    </div>
</x-app-layout>
