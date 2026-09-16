@props(['code' => ''])

<div class="code-display" {{ $attributes }}>
    {{ $code ?: $slot }}
</div>
