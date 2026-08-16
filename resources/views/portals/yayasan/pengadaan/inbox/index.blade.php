<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Inbox Verifikasi & Persetujuan Pengadaan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar usulan belanja sarana & prasarana dari seluruh unit sekolah yang memerlukan keputusan yayasan.</p>
            </div>
            <p class="text-sm text-gray-500">
                Yayasan <span class="mx-1 text-gray-300">&rsaquo;</span> Pengadaan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Inbox Approval</b>
            </p>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    search: @js(request('search', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.pengadaan.inbox.index'))
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pengajuan Masuk</label>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                            <input
                                type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                                placeholder="Nomor proposal, unit sekolah, judul..."
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div id="wadah-daftar-tabel" class="relative">
                @include('portals.yayasan.pengadaan.inbox._daftar', ['proposals' => $proposals])
            </div>
        </div>
    </div>
</x-app-layout>
