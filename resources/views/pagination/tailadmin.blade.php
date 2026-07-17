@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
        <span>
            Menampilkan {{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} entri
        </span>

        <div class="flex items-center gap-1.5">
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
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 font-semibold text-white" aria-current="page">{{ $page }}</span>
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
    </nav>
@endif
