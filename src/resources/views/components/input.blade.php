@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'error' => null,
    'helper' => null,
    'value' => null,
])

@php
    $errorMessage = $error ?? ($name ? $errors->first($name) : null);
@endphp

<div class="form-group">
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        class="form-input {{ $errorMessage ? 'is-invalid' : '' }}"
        placeholder="{{ $placeholder }}"
        @if($value !== null) value="{{ $value }}" @endif
        @if($required) required @endif
        {{ $attributes }}
    >
    @if($errorMessage)
        <p class="error-text">{{ $errorMessage }}</p>
    @elseif($helper)
        <p class="text-caption text-muted" style="margin-top:4px">{{ $helper }}</p>
    @endif
</div>
