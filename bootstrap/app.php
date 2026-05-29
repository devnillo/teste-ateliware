<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error('Dados inválidos.', 422, $e->errors());
            }

            if ($e instanceof NotFoundHttpException) {
                return ApiResponse::error('Recurso não encontrado.', 404);
            }

            if ($e instanceof HttpException) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'Erro na requisição.',
                    $e->getStatusCode(),
                );
            }

            return ApiResponse::error(
                app()->hasDebugModeEnabled() && $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Erro interno do servidor.',
                500,
            );
        });
    })->create();
