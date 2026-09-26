{{-- Account status pill. Expects $isActive. --}}
@if ($isActive)
    <span class="badge success dot">Aktif</span>
@else
    <span class="badge danger dot">Nonaktif</span>
@endif
