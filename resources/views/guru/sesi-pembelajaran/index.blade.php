<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Sesi Pembelajaran Hari Ini</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($sesiList as $sesi)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $sesi->kelas->nama }} &middot; {{ $sesi->mataPelajaran?->nama ?? '(tanpa mapel)' }}</p>
                            <p class="text-xs text-ink/60">{{ $sesi->jam_mulai }}–{{ $sesi->jam_selesai }}</p>
                        </div>
                        <a href="{{ route('guru.sesi.show', $sesi) }}" class="text-sm font-medium text-ink hover:text-brass">Isi Jurnal &amp; Presensi</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Tidak ada sesi untuk hari ini.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
