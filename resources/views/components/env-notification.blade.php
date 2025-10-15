{{-- resources/views/components/env-notification.blade.php --}}
@php
    $saasEnv = env('SAAS_ENV');
    $shouldShow = in_array($saasEnv, ['test', 'staging']);
@endphp

@if($shouldShow)
<div class="env-notification env-{{ $saasEnv }}">
    <div class="env-notification-content">
        <i class="fa fa-triangle-exclamation env-icon"></i>
        <span class="env-text">
            @if($saasEnv === 'test')
                <strong>TEST ENVIRONMENT</strong>
            @elseif($saasEnv === 'staging')
                <strong>STAGING ENVIRONMENT</strong>
            @endif
        </span>
    </div>
</div>
@endif