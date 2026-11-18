<?php

namespace App\Http\Controllers\Kiosk;

use Illuminate\Http\Request;

class AttendanceController extends \App\Http\Controllers\Hr\FaceAttendanceController
{
    public function show(Request $request) { return $this->display($request); }

    protected function homeRoute(): string { return 'kiosk.home'; }

    protected function actor(Request $request) { return $request->attributes->get('kiosk_device'); }

    protected function requiresConsent(): bool { return false; }

    protected function lockKey(Request $request): string { return 'face-kiosk-device:'.$this->actor($request)->id; }
}
