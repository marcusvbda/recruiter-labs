<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Realtime Driver — Component Demo' }}</title>
    @livewireStyles
</head>
<body style="font-family: sans-serif; padding: 2rem;">
    {{ $slot }}

    @livewireScripts
</body>
</html>
