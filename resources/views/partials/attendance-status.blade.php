{{-- Attendance status pill. Expects $status (App\AttendanceStatus). --}}
@php($tone = match ($status) {
    \App\AttendanceStatus::Present => 'success',
    \App\AttendanceStatus::Permission => 'info',
    \App\AttendanceStatus::Sick => 'warning',
    \App\AttendanceStatus::Absent => 'danger',
})
<span class="badge {{ $tone }} dot">{{ $status->label() }}</span>
