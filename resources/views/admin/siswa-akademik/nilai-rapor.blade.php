<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 pt-2">
        <h1 class="text-xl font-bold">Nilai & Rapor</h1>

        <form method="GET">
            <label class="text-sm font-medium">Semester</label>
            <select name="semester_id" onchange="this.form.submit()" class="block rounded border-gray-300 text-sm">
                @foreach ($semesterList as $semesterOpsi)
                    <option value="{{ $semesterOpsi->id }}" @selected($semesterId == $semesterOpsi->id)>{{ $semesterOpsi->nama }}</option>
                @endforeach
            </select>
        </form>

        @if ($pengajuanRapor)
            <a href="{{ route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semesterId]) }}" target="_blank" class="inline-block rounded bg-blue-600 px-3 py-2 text-sm text-white">Unduh Rapor</a>
        @endif

        <table class="w-full border text-sm">
            <thead>
                <tr><th class="border p-2 text-left">Mata Pelajaran</th><th class="border p-2 text-left">Nilai</th></tr>
            </thead>
            <tbody>
                @forelse ($nilaiList as $nilai)
                    <tr>
                        <td class="border p-2">{{ $nilai->komponenPenilaian?->subjek?->nama ?? $nilai->asesmen?->subjek?->nama ?? '-' }}</td>
                        <td class="border p-2">{{ $nilai->nilai_angka }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="border p-2 text-center text-gray-500">Belum ada nilai untuk semester ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
