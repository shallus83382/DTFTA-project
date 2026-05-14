@if($asset)
        <img 
            src="{{ $asset['url'] }}" 
            alt="{{ $asset['name'] }}"
            style="width:{{$dimension}}; height:{{$dimension}}; object-fit:cover; border-radius:6px;"
        >
@else
    <p>No asset selected</p>
@endif