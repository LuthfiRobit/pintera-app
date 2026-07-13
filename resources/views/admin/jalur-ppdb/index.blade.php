<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">
                    Jalur PPDB
                    @if ($tahunAjaranAktif)
                        <span class="text-base font-normal text-slate">— {{ $tahunAjaranAktif->nama }}</span>
                    @endif
                </h2>
            </div>
            @if ($tahunAjaranAktif)
                <x-link-button href="{{ route('admin.jalur-ppdb.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Jalur
                </x-link-button>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        @if (! $tahunAjaranAktif)
            <x-panel class="p-6 text-center text-sm text-slate">
                Aktifkan tahun ajaran terlebih dahulu di menu
                <a href="{{ route('admin.tahun-ajaran.index') }}" class="font-medium text-ink underline">Tahun Ajaran</a>
                sebelum mengatur jalur PPDB.
            </x-panel>
        @elseif ($jalurList->isEmpty())
            <x-panel class="p-6">
                <p class="text-sm text-slate">Belum ada konfigurasi SPMB untuk {{ $tahunAjaranAktif->nama }}.</p>
                @if ($tahunAjaranSebelumnya)
                    <form method="POST" action="{{ route('admin.spmb-konfigurasi.duplikasi') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="tahun_ajaran_sumber_id" value="{{ $tahunAjaranSebelumnya->id }}">
                        <button type="submit" class="rounded-xl bg-brass/10 px-4 py-2 text-sm font-bold text-brass transition hover:bg-brass/20">
                            Salin dari {{ $tahunAjaranSebelumnya->nama }}
                        </button>
                    </form>
                @endif
            </x-panel>
        @else
            <x-panel>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-5 py-3 font-display font-semibold">Nama</th>
                            <th class="px-5 py-3 font-display font-semibold">Status</th>
                            <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($jalurList as $jalur)
                            <tr class="transition hover:bg-paper/50">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $jalur->nama }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($jalur->status_aktif)
                                        <x-badge tone="brass">Aktif</x-badge>
                                    @else
                                        <x-badge tone="slate">Nonaktif</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.jalur-ppdb.edit', $jalur) }}" class="font-medium text-ink hover:text-brass">Kelola</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel>
        @endif
    </div>
</x-app-layout>
