<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Pratinjau Import Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display text-lg font-semibold text-ink">Baris Valid ({{ count($validRows) }})</h3>
            </div>
            <ul class="divide-y divide-ink/10">
                @forelse ($validRows as $row)
                    <li class="px-6 py-3 text-sm text-ink">{{ $row['nama_lengkap'] }} &middot; NIS {{ $row['nis'] }}</li>
                @empty
                    <li class="px-6 py-4 text-sm text-ink/60">Tidak ada baris valid.</li>
                @endforelse
            </ul>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display text-lg font-semibold text-ink">Baris Bermasalah ({{ count($invalidRows) }})</h3>
            </div>
            <ul class="divide-y divide-ink/10">
                @forelse ($invalidRows as $row)
                    <li class="px-6 py-3 text-sm">
                        <span class="text-ink">{{ $row['nama_lengkap'] ?: '(tanpa nama)' }}</span>
                        <span class="text-signal-red">— {{ $row['error'] }}</span>
                    </li>
                @empty
                    <li class="px-6 py-4 text-sm text-ink/60">Tidak ada baris bermasalah.</li>
                @endforelse
            </ul>
        </x-panel>

        @if (count($validRows) > 0)
            <form method="POST" action="{{ route('admin.siswa.import.confirm') }}">
                @csrf
                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">
                    Import {{ count($validRows) }} Siswa Valid
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
