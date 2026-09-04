<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 pt-2">
        <h1 class="text-xl font-bold">Jadwal Pelajaran</h1>

        <form method="GET">
            <label class="text-sm font-medium">Semester</label>
            <select name="semester_id" onchange="this.form.submit()" class="block rounded border-gray-300 text-sm">
                @foreach ($semesterList as $semesterOpsi)
                    <option value="{{ $semesterOpsi->id }}" @selected($semesterId == $semesterOpsi->id)>{{ $semesterOpsi->nama }}</option>
                @endforeach
            </select>
        </form>

        @forelse ($jadwalList as $hari => $jadwalHari)
            <div>
                <h3 class="font-semibold text-sm uppercase">{{ $hari }}</h3>
                <table class="w-full border text-sm">
                    <tbody>
                        @foreach ($jadwalHari as $jadwal)
                            <tr>
                                <td class="border p-2">{{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}</td>
                                <td class="border p-2">{{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}</td>
                                <td class="border p-2">{{ $jadwal->guru?->nama }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-500">Belum ada jadwal pelajaran untuk semester ini.</p>
        @endforelse
    </div>
</x-app-layout>
