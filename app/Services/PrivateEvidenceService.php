<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PrivateEvidenceService
{
    public function response($request, ?string $path, bool $image)
    {
        Gate::authorize('view', $request);
        abort_unless($path && preg_match('#^(corrections|leaves|demo)/[a-zA-Z0-9/_\-.]+$#', $path) && ! str_contains($path, '..'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        $allowed = $image ? ['image/jpeg', 'image/png', 'image/webp'] : ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if ($request instanceof AttendanceCorrectionRequest && $request->is_demo) {
            $allowed[] = 'text/plain';
        }
        abort_unless(in_array($mime, $allowed, true), 404);

        return $disk->response($path, 'evidence.'.pathinfo($path, PATHINFO_EXTENSION), ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], $image && $mime !== 'text/plain' ? 'inline' : 'attachment');
    }
}
