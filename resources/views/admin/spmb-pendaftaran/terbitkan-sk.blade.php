<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Terbitkan SK Penetapan Hasil</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                Pilih lembaga aktif melalui pengalih lembaga untuk menerbitkan SK.
            </div>
        @endif

        <x-panel class="p-6">
            <form method="GET" action="{{ route('admin.sk-ppdb.create') }}" class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Pilih Gelombang" />
                    <select name="gelombang_ppdb_id" onchange="this.form.submit()" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">Pilih...</option>
                        @foreach ($gelombangList as $gelombang)
                            <option value="{{ $gelombang->id }}" @selected($gelombangTerpilih?->id === $gelombang->id)>{{ $gelombang->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </x-panel>

        @if ($gelombangTerpilih)
            <x-panel class="p-6">
                @if ($ringkasan['belum_final'] > 0)
                    <div class="mb-4 rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                        {{ $ringkasan['belum_final'] }} dari {{ $ringkasan['total'] }} pendaftaran belum punya keputusan final dan tidak akan tercantum di SK ini. Anda tetap bisa menerbitkan SK untuk yang sudah final, lalu menerbitkan SK susulan nanti.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.sk-ppdb.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="gelombang_ppdb_id" value="{{ $gelombangTerpilih->id }}">
                    <div>
                        <x-input-label value="Nomor SK" />
                        <x-text-input type="text" name="nomor_sk" value="{{ old('nomor_sk') }}" class="mt-1.5" placeholder="421.3/SK-PPDB.001/2026" required />
                        <x-input-error :messages="$errors->get('nomor_sk')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Terbit" />
                        <x-text-input type="date" name="tanggal_terbit" value="{{ old('tanggal_terbit', now()->toDateString()) }}" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('tanggal_terbit')" class="mt-1.5" />
                    </div>
                    <p class="text-sm text-slate">{{ $ringkasan['final'] }} pendaftaran akan tercantum di SK ini.</p>
                    <x-primary-button>Terbitkan SK</x-primary-button>
                </form>
            </x-panel>
        @endif
    </div>
</x-app-layout>
