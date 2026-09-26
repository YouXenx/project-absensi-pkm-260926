{{-- Student status pill. Expects $status (App\StudentStatus). --}}
<span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
