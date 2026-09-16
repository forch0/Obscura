@props(['variant' => null])

<span {{ $attributes->merge(['class' => 'badge' . ($variant ? ' badge-' . $variant : '')]) }}>
    {{ $slot }}
</span>
