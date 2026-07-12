<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Data Lembaga</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-4 bg-signal-green/10 text-signal-green rounded">{{ session('status') }}</div>
        @endif

        @if (auth()->user()->widestScopeLevel() === 'yayasan')
            <a href="{{ route('admin.lembaga.create') }}" class="inline-block mb-4 px-4 py-2 bg-ink text-white rounded">
                Tambah Lembaga
            </a>
        @endif

        <table class="w-full bg-white shadow rounded">
            <thead>
                <tr class="text-left border-b border-slate/20">
                    <th class="p-3 text-ink">NPSN</th>
                    <th class="p-3 text-ink">Nama</th>
                    <th class="p-3 text-ink">Bentuk</th>
                    <th class="p-3 text-ink">Status</th>
                    <th class="p-3 text-ink">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lembaga as $item)
                    <tr class="border-b border-slate/20">
                        <td class="p-3 font-mono text-slate">{{ $item->npsn }}</td>
                        <td class="p-3 text-ink">{{ $item->nama }}</td>
                        <td class="p-3 text-slate">{{ $item->bentuk_pendidikan }}</td>
                        <td class="p-3">
                            @if ($item->status_sekolah === 'negeri')
                                <span class="px-2 py-0.5 rounded text-xs bg-brass/10 text-brass">Negeri</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-slate/10 text-slate">Swasta</span>
                            @endif
                        </td>
                        <td class="p-3"><a href="{{ route('admin.lembaga.edit', $item) }}" class="text-ink underline">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
