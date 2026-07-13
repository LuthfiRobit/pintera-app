<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Data Lembaga</h2>
            </div>
            @if (auth()->user()->widestScopeLevel() === 'yayasan')
                <x-link-button href="{{ route('admin.lembaga.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Lembaga
                </x-link-button>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">NPSN</th>
                        <th class="px-5 py-3 font-display font-semibold">Nama</th>
                        <th class="px-5 py-3 font-display font-semibold">Bentuk</th>
                        <th class="px-5 py-3 font-display font-semibold">Status</th>
                        <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ($lembaga as $item)
                        <tr class="transition hover:bg-paper/50">
                            <td class="px-5 py-3.5 font-mono text-slate">{{ $item->npsn }}</td>
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $item->nama }}</td>
                            <td class="px-5 py-3.5 text-slate">{{ $item->bentuk_pendidikan }}</td>
                            <td class="px-5 py-3.5">
                                @if ($item->status_sekolah === 'negeri')
                                    <x-badge tone="brass">Negeri</x-badge>
                                @else
                                    <x-badge tone="slate">Swasta</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.lembaga.edit', $item) }}" class="font-medium text-ink hover:text-brass">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    </div>
</x-app-layout>
