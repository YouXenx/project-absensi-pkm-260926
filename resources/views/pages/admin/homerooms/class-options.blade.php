{{-- Class <option>s for the homeroom modal. Expects $classes (with homeroomTeachers of the active year) and $homeroom (null when creating). --}}
<option value="">Pilih kelas</option>
@foreach ($classes as $schoolClass)
    @php($assigned = $schoolClass->homeroomTeachers->first())
    @php($isOwn = $assigned && $homeroom && $assigned->is($homeroom))
    <option value="{{ $schoolClass->class_id }}" @disabled($assigned && ! $isOwn)>
        {{ $schoolClass->class_name }}{{ $assigned && ! $isOwn ? ' — sudah ada wali: '.$assigned->teacher->name : '' }}
    </option>
@endforeach
