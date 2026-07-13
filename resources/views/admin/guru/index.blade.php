<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Data Guru</h2>
            </div>
            <x-link-button href="{{ route('admin.guru.create') }}">
                <span class="text-base leading-none">+</span> Tambah Data Guru
            </x-link-button>
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
                        <th class="px-5 py-3 font-display font-semibold">Nama</th>
                        <th class="px-5 py-3 font-display font-semibold">Jenis PTK</th>
                        <th class="px-5 py-3 font-display font-semibold">Status Kepegawaian</th>
                        <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ($guru as $item)
                        <tr class="transition hover:bg-paper/50">
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $item->nama }}</td>
                            <td class="px-5 py-3.5 text-slate">{{ $item->jenis_ptk }}</td>
                            <td class="px-5 py-3.5">
                                @if (in_array($item->status_kepegawaian, ['PNS', 'PPPK']))
                                    <x-badge tone="brass">{{ $item->status_kepegawaian }}</x-badge>
                                @else
                                    <x-badge tone="slate">{{ $item->status_kepegawaian }}</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.guru.edit', $item) }}" class="font-medium text-ink hover:text-brass">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    </div>
</x-app-layout>
