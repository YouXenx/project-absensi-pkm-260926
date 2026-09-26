{{-- Warning + acknowledgement in the new-year modal when the active year's flow is unfinished. Expects $current and $progress. --}}
@if ($current && ! $progress['complete'])
    <div class="alert warning">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
        <div class="body">
            <div class="title">Proses tahun ajaran {{ $current->year_name }} belum selesai</div>
            <ul style="margin:6px 0 0;padding-left:18px">
                @foreach ($progress['steps'] as $step)
                    @unless ($step['done'])
                        <li>{{ $step['label'] }}: {{ $step['detail'] }}</li>
                    @endunless
                @endforeach
            </ul>
        </div>
    </div>
    <label class="check" style="margin-top:10px">
        <input type="checkbox" name="acknowledge_incomplete" value="1">
        <span class="box"></span> Saya mengerti dan tetap ingin membuat tahun ajaran baru
    </label>
@endif
