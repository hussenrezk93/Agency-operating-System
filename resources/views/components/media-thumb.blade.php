@props(['media', 'emoji' => '🔗', 'linkClass' => ''])
@if($media->is_upload)
    @if($media->isVideo())
        <video controls preload="metadata" title="{{ $media->label ?: __('agencyos.tasks.show.uploaded_image') }}"
               style="width:140px;height:78px;border-radius:8px;border:1px solid var(--dx-line);object-fit:cover;display:inline-block;margin:0 6px 6px 0;vertical-align:top">
            <source src="{{ $media->displayUrl() }}">
        </video>
    @else
        <a href="{{ $media->displayUrl() }}" target="_blank" rel="noopener" title="{{ $media->label ?: __('agencyos.tasks.show.uploaded_image') }}" style="display:inline-block;margin:0 6px 6px 0">
            <img src="{{ $media->displayUrl() }}" alt="{{ $media->label }}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--dx-line)">
        </a>
    @endif
@else
    <a class="btn btn-sm btn-outline {{ $linkClass }}" style="margin:0 6px 6px 0" href="{{ $media->url }}" target="_blank" rel="noopener" title="{{ $media->url }}">{{ $emoji }} {{ $media->label ?: $media->url }}</a>
@endif
