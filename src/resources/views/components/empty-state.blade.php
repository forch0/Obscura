@props([
    'title' => 'Nothing here yet',
    'message' => '',
    'action' => null,
    'actionLabel' => null,
])

<div class="empty-state">
    <h3>{{ $title }}</h3>
    @if($message)<p>{{ $message }}</p>@endif
    @if($action && $actionLabel)
        <a href="{{ $action }}" class="btn btn-primary btn-pill">{{ $actionLabel }}</a>
    @endif
    {{ $slot }}
</div>
