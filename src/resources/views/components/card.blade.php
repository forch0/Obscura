@props(['padding' => 'p-4', 'hover' => true])

<div {{ $attributes->merge(['class' => 'card ' . $padding . ($hover ? '' : ' card-subtle')]) }}>
    {{ $slot }}
</div>
