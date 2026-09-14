<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoIndex;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            NoIndex::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Toplu fotoğraf / Excel yüklemede PHP limitleri (post_max_size vb.)
        // aşıldığında teknik hata yerine anlaşılır Türkçe mesaj göster.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'Yüklediğiniz dosya(lar) çok büyük. Lütfen daha az dosya seçip tekrar deneyin veya ZIP kullanın. Sorun sürerse sunucudaki PHP limitlerini (post_max_size, upload_max_filesize) yükseltin.';

            if ($request->wantsJson() || $request->expectsJson() || $request->header('X-Batch-Upload')) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['photos' => [$message]],
                ], 413);
            }

            return back()->withErrors(['photos' => $message]);
        });
    })->create();
