@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-3 text-sm text-gray-500">
        {{-- Mobile View (< sm) --}}
        <div class="flex flex-col gap-2.5 sm:hidden">
            <div class="flex items-center justify-between gap-2">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-400 select-none" aria-disabled="true">
                        <x-icon name="expand_more" class="h-3.5 w-3.5 rotate-90" />
                        <span>Sebelumnya</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                        <x-icon name="expand_more" class="h-3.5 w-3.5 rotate-90" />
                        <span>Sebelumnya</span>
                    </a>
                @endif

                <span class="text-xs font-medium text-gray-600">
                    Hal <span class="font-semibold text-gray-900">{{ $paginator->currentPage() }}</span> / {{ $paginator->lastPage() }}
                </span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                        <span>Berikutnya</span>
                        <x-icon name="expand_more" class="h-3.5 w-3.5 -rotate-90" />
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-400 select-none" aria-disabled="true">
                        <span>Berikutnya</span>
                        <x-icon name="expand_more" class="h-3.5 w-3.5 -rotate-90" />
                    </span>
                @endif
            </div>

            <p class="text-center text-xs text-gray-500">
                Menampilkan <span class="font-medium text-gray-700">{{ $paginator->firstItem() ?? 0 }}</span>&ndash;<span class="font-medium text-gray-700">{{ $paginator->lastItem() ?? 0 }}</span> dari <span class="font-medium text-gray-700">{{ $paginator->total() }}</span> entri
            </p>
        </div>

        {{-- Desktop View (>= sm) --}}
        <div class="hidden sm:flex sm:items-center sm:justify-between gap-3">
            <span>
                Menampilkan <span class="font-medium text-gray-700">{{ $paginator->firstItem() ?? 0 }}</span>&ndash;<span class="font-medium text-gray-700">{{ $paginator->lastItem() ?? 0 }}</span> dari <span class="font-medium text-gray-700">{{ $paginator->total() }}</span> entri
            </span>

            <div class="flex items-center gap-1.5 flex-wrap">
                @if ($paginator->onFirstPage())
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-300" aria-disabled="true">
                        <x-icon name="expand_more" class="h-3.5 w-3.5 rotate-90" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50">
                        <x-icon name="expand_more" class="h-3.5 w-3.5 rotate-90" />
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-1 text-gray-400" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 font-semibold text-white shadow-sm" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition hover:bg-gray-50">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50">
                        <x-icon name="expand_more" class="h-3.5 w-3.5 -rotate-90" />
                    </a>
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-300" aria-disabled="true">
                        <x-icon name="expand_more" class="h-3.5 w-3.5 -rotate-90" />
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
