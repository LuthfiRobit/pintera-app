<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Pratinjau Import Siswa</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.siswa.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Siswa</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pratinjau Import</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Baris Valid ({{ count($validRows) }})</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($validRows as $row)
                    <li class="px-5 py-3 text-sm text-gray-700">
                        <span class="font-semibold text-gray-900">{{ $row['nama_lengkap'] }}</span>
                        <span class="text-gray-500">&middot; NIS {{ $row['nis'] }}</span>
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-gray-500">Tidak ada baris valid.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Baris Bermasalah ({{ count($invalidRows) }})</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($invalidRows as $row)
                    <li class="px-5 py-3 text-sm">
                        <span class="font-semibold text-gray-900">{{ $row['nama_lengkap'] ?: '(tanpa nama)' }}</span>
                        <span class="text-error-600">&mdash; {{ $row['error'] }}</span>
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-gray-500">Tidak ada baris bermasalah.</li>
                @endforelse
            </ul>
        </div>

        @if (count($validRows) > 0)
            <form method="POST" action="{{ route('admin.siswa.import.confirm') }}">
                @csrf
                <x-primary-button type="submit">Import {{ count($validRows) }} Siswa Valid</x-primary-button>
            </form>
        @endif
    </div>
</x-app-layout>
