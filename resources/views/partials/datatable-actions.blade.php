{{--
    Row buttons for a DataTables row, handled by resources/js/modal.js.
    Expects $modal (id of the page's <x-modal-form>), $editUrl (JSON payload endpoint), $deleteUrl, $deleteMessage.
    Optional $extraActions: list of ['modal' => id, 'url' => payload endpoint, 'label' => ..., 'icon' => '<path .../>'] shown first.
--}}
<div class="data-cell-actions">
    @foreach ($extraActions ?? [] as $extraAction)
        <button type="button" class="btn--icon" data-modal-open="{{ $extraAction['modal'] }}" data-modal-url="{{ $extraAction['url'] }}" data-modal-mode="edit"
            aria-label="{{ $extraAction['label'] }}" title="{{ $extraAction['label'] }}">
            <svg viewBox="0 0 24 24">{!! $extraAction['icon'] !!}</svg>
        </button>
    @endforeach
    <button type="button" class="btn--icon" data-modal-open="{{ $modal }}" data-modal-url="{{ $editUrl }}" aria-label="Edit" title="Edit">
        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
    </button>
    @if ($deleteUrl ?? null)
        <form method="POST" action="{{ $deleteUrl }}" data-ajax data-confirm="{{ $deleteMessage }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn--icon is-danger" aria-label="Hapus" title="Hapus">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
        </form>
    @endif
</div>
