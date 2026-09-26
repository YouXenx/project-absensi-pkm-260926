{{-- <option>s for the "Pindah Kelas" modal. Expects $classes (other classes, with students_count of active students). --}}
<option value="">Pilih kelas tujuan</option>
@foreach ($classes as $schoolClass)
    <option value="{{ $schoolClass->class_id }}">{{ $schoolClass->class_name }} ({{ $schoolClass->students_count }} siswa aktif)</option>
@endforeach
