<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Data Guru</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-4 bg-signal-green/10 text-signal-green rounded">{{ session('status') }}</div>
        @endif

        <a href="{{ route('admin.guru.create') }}" class="inline-block mb-4 px-4 py-2 bg-ink text-white rounded">
            Tambah Data Guru
        </a>

        <table class="w-full bg-white shadow rounded">
            <thead>
                <tr class="text-left border-b border-slate/20">
                    <th class="p-3 text-ink">Nama</th>
                    <th class="p-3 text-ink">Jenis PTK</th>
                    <th class="p-3 text-ink">Status Kepegawaian</th>
                    <th class="p-3 text-ink">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($guru as $item)
                    <tr class="border-b border-slate/20">
                        <td class="p-3 text-ink">{{ $item->nama }}</td>
                        <td class="p-3 text-slate">{{ $item->jenis_ptk }}</td>
                        <td class="p-3">
                            @if (in_array($item->status_kepegawaian, ['PNS', 'PPPK']))
                                <span class="px-2 py-0.5 rounded text-xs bg-brass/10 text-brass">{{ $item->status_kepegawaian }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-slate/10 text-slate">{{ $item->status_kepegawaian }}</span>
                            @endif
                        </td>
                        <td class="p-3"><a href="{{ route('admin.guru.edit', $item) }}" class="text-ink underline">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
