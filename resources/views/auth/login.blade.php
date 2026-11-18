<!DOCTYPE html>
<html lang="id">
<head><x-favicon/>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Masuk / FaceTrack AI</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
<a href="#main-content" class="skip-link">Langsung ke konten utama</a>
<main id="main-content" class="login-shell" tabindex="-1">
<img class="login-logo" src="{{ asset('images/facetrack-ai-logo.png') }}" alt="FaceTrack AI" width="1051" height="249">
<section class="login-card" aria-labelledby="login-title">
<h1 id="login-title">Welcome Back</h1>
<p class="login-subtitle">Let’s get started with your account</p>
@if(session('warning'))<div role="alert" class="login-notice">{{ session('warning') }}</div>@endif
@if(session('success'))<div role="status" class="login-notice">{{ session('success') }}</div>@endif
@if($errors->any())<div id="login-error" role="alert" class="login-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}" class="login-form" data-submit-form>
@csrf
<div>
<label for="email" class="sr-only">Email</label>
<input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Email" required autocomplete="username" class="login-input" @error('email') aria-invalid="true" aria-describedby="login-error" @enderror>
</div>
<div>
<label for="password" class="sr-only">Password</label>
<input id="password" name="password" type="password" placeholder="Password" required autocomplete="current-password" class="login-input" @error('password') aria-invalid="true" aria-describedby="login-error" @enderror>
</div>
<label class="login-remember" for="remember"><input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Keep me logged in</span></label>
<button type="submit" data-submit-button class="login-button">Login</button>
</form>
</section>
</main>
</body>
</html>
