{{-- Record count per status for the current filters. Expects $summary (status => count) and $filters (date_from, date_to). --}}
@foreach (\App\AttendanceStatus::cases() as $status)
    <article class="kpi-card" data-status="{{ $status->value }}">
        <div class="kpi-top"><div class="kpi-identity"><div class="kpi-label">{{ $status->label() }}</div></div></div>
        <div class="kpi-value">{{ number_format($summary[$status->value], 0, ',', '.') }}</div>
        <div class="kpi-compare">
            {{ $filters['date_from']->format('d/m/Y') }}{{ $filters['date_from']->equalTo($filters['date_to']) ? '' : ' – '.$filters['date_to']->format('d/m/Y') }}
        </div>
    </article>
@endforeach
