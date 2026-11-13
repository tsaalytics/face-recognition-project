<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><title>@yield('code') / FaceTrack AI</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#F7F3F8;color:#334155;font:16px/1.6 'Segoe UI',sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px}
main{width:100%;max-width:560px;background:white;border:1px solid #ede5ef;border-radius:20px;padding:clamp(24px,6vw,48px)}
.brand{font-weight:700;color:#4B075D}.code{color:#E5007D;font-size:48px;font-weight:700;margin:24px 0 0}
h1{color:#4B075D;font-size:26px;margin:8px 0}p{overflow-wrap:anywhere}a{display:inline-block;background:#E5007D;color:white;text-decoration:none;border-radius:12px;padding:12px 20px;margin-top:12px;font-weight:600}
a:focus-visible{outline:3px solid #4B075D;outline-offset:4px}.note{font-size:13px;color:#64748b;margin-top:24px}
</style></head><body><main><div class="brand">FaceTrack AI</div><p class="code">@yield('code')</p><h1>@yield('title')</h1><p>@yield('message')</p><a href="{{ url('/') }}">Kembali ke beranda</a><p class="note">Jika kendala berulang, hubungi Admin atau HR. Jangan kirim password atau tautan aktivasi.</p></main></body></html>
