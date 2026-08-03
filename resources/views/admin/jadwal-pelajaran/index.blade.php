<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola penomoran slot belajar, mata pelajaran, dan pengampu untuk tiap kelas.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="jadwalPelajaranFilter({
                tahunAjaranId: @js($tahunAjaranId),
                kelasId: @js($kelasId),
                semesterId: @js($semesterId),
                opsiUrl: @js(route('admin.jadwal-pelajaran.opsi')),
                indexUrlBase: @js(route('admin.jadwal-pelajaran.index')),
                createUrlBase: @js(route('admin.jadwal-pelajaran.create')),
                storeUrlBase: @js(route('admin.jadwal-pelajaran.store')),
            })"
        >
            {{-- 1. Card Filter: Parameter Jadwal --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                    <div>
                        <h2 class="font-display text-base font-bold text-gray-900">Filter Jadwal Pelajaran</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Pilih parameter tahun ajaran, semester, dan kelas untuk menampilkan data.</p>
                    </div>
                    <template x-if="kelasId && semesterId">
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="openDuplicateModal()"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
                            >
                                <x-icon name="content_copy" class="h-4 w-4 text-gray-500" />
                                <span>Salin dari Kelas Lain</span>
                            </button>
                            <x-link-button href="#" x-bind:href="tambahSlotUrl()" @click.prevent="openCreateModal()" class="shrink-0 justify-center">
                                <span class="text-base leading-none mr-1.5">+</span> Tambah Slot Jadwal
                            </x-link-button>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Tahun Ajaran" />
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Semester" />
                        <select x-ref="semesterSelect" x-model="semesterId" @change="muatUlangDaftar()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Kelas" />
                        <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
                            <option value="">— Pilih Kelas —</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- 2. Daftar Jadwal Pelajaran per Hari --}}
            <div x-ref="daftarJadwal">
                @include('admin.jadwal-pelajaran._daftar')
            </div>
        </div>
    </div>
</x-app-layout>

