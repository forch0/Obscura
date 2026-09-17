@props([
    'variant' => 'primary',   // primary | secondary | danger | ghost
    'size' => 'md',            // sm | md | lg
    'pill' => false,
    'type' => 'button',
    'href' => null,
])

@php
    $classes = 'btn btn-' . $variant . ' btn-' . $size . ($pill ? ' btn-pill' : '');
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
