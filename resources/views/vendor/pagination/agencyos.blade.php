{{--
    Laravel's built-in pagination views ("tailwind", "bootstrap-*") assume their
    respective CSS framework is loaded on the page. This app ships its own design
    system (agencyos.css) with neither, so those views' utility classes did nothing and
    their inline SVG arrow icons rendered at native, unconstrained size. This view uses
    only the app's own classes.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pager-info small muted">
            {{ __('Showing :first to :last of :total results', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </div>
        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline is-disabled" aria-disabled="true">{!! __('pagination.previous') !!}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm btn-outline" rel="prev">{!! __('pagination.previous') !!}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pager-dots">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pager-num is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pager-num">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm btn-outline" rel="next">{!! __('pagination.next') !!}</a>
            @else
                <span class="btn btn-sm btn-outline is-disabled" aria-disabled="true">{!! __('pagination.next') !!}</span>
            @endif
        </div>
    </nav>
@endif
