{{-- Class checkboxes for a new subject. Expects $classes (with has_homeroom for the active year). --}}
@foreach ($classes as $schoolClass)
    <label class="check" @if (! $schoolClass->has_homeroom) title="Belum ada wali kelas" style="opacity:.5" @endif>
        <input type="checkbox" name="class_ids[]" value="{{ $schoolClass->class_id }}" @disabled(! $schoolClass->has_homeroom)>
        <span class="box"></span> {{ $schoolClass->class_name }}
    </label>
@endforeach
