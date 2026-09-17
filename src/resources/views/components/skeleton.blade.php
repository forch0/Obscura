@props(['width' => '100%', 'height' => '48px'])

<div {{ $attributes->merge(['class' => 'skeleton']) }} style="width:{{ $width }};height:{{ $height }}"></div>
