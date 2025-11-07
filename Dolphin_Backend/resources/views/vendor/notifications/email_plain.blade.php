@php
    // Normalize variables used by the HTML template for a plain-text version
    $greeting = $greeting ?? null;
    $introLines = $introLines ?? [];
    $outroLines = $outroLines ?? [];
    $salutation = $salutation ?? null;
    $actionText = $actionText ?? null;
    $actionUrl = $actionUrl ?? null;
    $displayableActionUrl = $displayableActionUrl ?? $actionUrl;
@endphp

@if (!empty($greeting))
    {{ $greeting }}
@else
    Hello!
@endif


@foreach ($introLines as $line)
    {{ trim(strip_tags($line)) }}
@endforeach

@if (!empty($actionText) && !empty($displayableActionUrl))
    {{ $actionText }}: {{ $displayableActionUrl }}
@endif

@foreach ($outroLines as $line)
    {{ trim(strip_tags($line)) }}
@endforeach

@if (!empty($salutation))
    {{ $salutation }}
@else
    Regards,
    Dolphin Admin
@endif

© {{ date('Y') }} Dolphin. All rights reserved.
