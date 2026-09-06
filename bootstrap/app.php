<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['web', 'auth', 'account.active']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'account.active' => EnsureAccountActive::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);

        // SubstituteBindings resolves route-model params (e.g. {conversation}) and 404s
        // if the row is gone. It ships in the base `web` group, ahead of every
        // route-level middleware below — so without this, a stale/deleted-resource URL
        // 404s before these gates ever run, instead of redirecting (forced password
        // change, disabled account, wrong role) the way it does for URLs whose resource
        // still exists.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureAccountActive::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsurePasswordChanged::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureRole::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale form submitted after the session's CSRF token has rotated (mostly
        // just SESSION_LIFETIME idle timeout — 2 hours here) otherwise shows Laravel's
        // bare, unbranded "419 | Page Expired" page, which reads as a crash rather than
        // "sign in again and retry." Back to wherever they were instead, with a plain
        // explanation — same UX as every other validation bounce-back in this app.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
                ->with('status', __('agencyos.common.session_expired'));
        });
    })->create();
