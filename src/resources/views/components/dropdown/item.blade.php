@props([
    'href' => null,
    'danger' => false,
    'type' => 'button',
])

@if($href)
    <a href="{{ $href }}" class="dropdown-item {{ $danger ? 'danger' : '' }}" role="menuitem" {{ $attributes }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" class="dropdown-item {{ $danger ? 'danger' : '' }}" role="menuitem" {{ $attributes }}>{{ $slot }}</button>
@endif
