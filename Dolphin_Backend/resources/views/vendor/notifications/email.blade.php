<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Notification' }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #333; }
        .container { max-width: 680px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 10px 16px; background:#3880ff; color:#fff; text-decoration:none; border-radius:4px }
    </style>
</head>
<body>
    <div class="container">
        @if(!empty($logoUrl))
            <p><img src="{{ $logoUrl }}" alt="logo" style="max-height:40px"></p>
        @endif

        @if(!empty($greeting))
            <p>{{ $greeting }}</p>
        @endif

        @if(!empty($introLines) && is_array($introLines))
            @foreach($introLines as $line)
                <p>{{ $line }}</p>
            @endforeach
        @endif

        @if(!empty($actionText) && !empty($actionUrl))
            <p><a href="{{ $actionUrl }}" class="button">{{ $actionText }}</a></p>
            <p style="font-size:90%">If the button above does not work, copy and paste this URL into your browser:</p>
            <p style="font-size:90%"><a href="{{ $displayableActionUrl ?? $actionUrl }}">{{ $displayableActionUrl ?? $actionUrl }}</a></p>
        @endif

        @if(!empty($outroLines) && is_array($outroLines))
            @foreach($outroLines as $line)
                <p>{{ $line }}</p>
            @endforeach
        @endif

        @if(!empty($salutation))
            <p>{{ $salutation }}</p>
        @endif

        <hr>
        <p style="font-size:90%; color:#666">This is an automated message from {{ config('app.name') }}.</p>
    </div>
</body>
</html>
