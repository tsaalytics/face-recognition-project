<?php

namespace App\Providers;

use App\Contracts\FaceRecognitionService;
use App\Services\DisabledFaceRecognitionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FaceRecognitionService::class, DisabledFaceRecognitionService::class);
    }

    public function boot(): void {}
}
