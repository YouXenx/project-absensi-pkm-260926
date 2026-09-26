{{-- Class <option>s when editing a subject. Expects $classes (with has_homeroom for the active year). --}}
@foreach ($classes as $schoolClass)
    <option value="{{ $schoolClass->class_id }}">{{ $schoolClass->class_name }}{{ $schoolClass->has_homeroom ? '' : ' (belum ada wali kelas)' }}</option>
@endforeach
