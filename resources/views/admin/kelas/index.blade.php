<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Kelas</h2>
            </div>
            <x-link-button href="{{ route('admin.kelas.create') }}">
                <span class="text-base leading-none">+</span> Tambah Kelas
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($kelasList as $kelas)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $kelas->nama }}</p>
                            <p class="text-xs text-ink/60">
                                {{ $kelas->tahunAjaran->nama }}
                                @if ($kelas->waliKelas)
                                    &middot; Wali Kelas: {{ $kelas->waliKelas->nama }}
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('admin.kelas.edit', $kelas) }}" class="text-sm font-medium text-ink hover:text-brass">Ubah</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Belum ada kelas.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
