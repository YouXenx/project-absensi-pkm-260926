{{-- <option>s for a promotion decision. Expects $fromClass, $classes, $selected (string|null). --}}
@php($decision = \App\Http\Requests\Admin\PromotionRequest::class)

<option value="{{ $decision::SKIP }}" @selected($selected === $decision::SKIP)>Lewati (belum diproses)</option>
<optgroup label="Naik ke kelas">
    @foreach ($classes as $targetClass)
        @continue($targetClass->class_id === $fromClass->class_id)
        <option value="{{ $targetClass->class_id }}" @selected((string) $selected === (string) $targetClass->class_id)>Naik ke {{ $targetClass->class_name }}</option>
    @endforeach
</optgroup>
<option value="{{ $decision::STAY }}" @selected($selected === $decision::STAY)>Tinggal kelas ({{ $fromClass->class_name }})</option>
<option value="{{ $decision::GRADUATE }}" @selected($selected === $decision::GRADUATE)>Tandai lulus</option>
