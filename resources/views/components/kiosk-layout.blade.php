@props(['title' => 'FaceTrack AI Kiosk'])
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><meta name="robots" content="noindex, nofollow"><title>{{ $title }} / FaceTrack AI</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="standalone-kiosk"><main id="main-content">{{ $slot }}</main></body>
</html>
