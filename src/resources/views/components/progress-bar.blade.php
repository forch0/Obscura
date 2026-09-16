@props(['percent' => 0, 'id' => null])

<div class="progress-bar" @if($id) id="{{ $id }}" @endif {{ $attributes }}>
    <div class="fill" style="width:{{ $percent }}%"></div>
</div>
