<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestNotificationController;

Route::get('/', function () {
    $frontend = env('FRONTEND_URL', 'http://127.0.0.1:8080');
    $laravel = app()->version();
    $php = PHP_VERSION;
    $env = env('APP_ENV', 'local');
    $debug = env('APP_DEBUG', true) ? 'true' : 'false';

    $html = <<<HTML
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Dolphin Backend — Development</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#222; padding:40px }
            .box { max-width:900px; margin:0 auto; }
            h1{margin-bottom:0}
            p.lead{color:#555}
            table{border-collapse:collapse;margin-top:20px}
            td,th{padding:8px 12px;border:1px solid #eee}
            a.button{display:inline-block;margin-top:16px;padding:8px 12px;background:#0b87d0;color:#fff;text-decoration:none;border-radius:6px}
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Dolphin — Backend (Development)</h1>
            <p class="lead">This is the backend server. For the frontend app open <strong><a href="{$frontend}">{$frontend}</a></strong></p>
            <table>
                <tr><th>Laravel</th><td>{$laravel}</td></tr>
                <tr><th>PHP</th><td>{$php}</td></tr>
                <tr><th>Environment</th><td>{$env}</td></tr>
                <tr><th>Debug</th><td>{$debug}</td></tr>
            </table>
            <a class="button" href="{$frontend}">Open Frontend</a>
        </div>
    </body>
</html>
HTML;

    return response($html, 200)->header('Content-Type', 'text/html');
});

Route::get('/debug/send-receipt', [TestNotificationController::class, 'sendReceipt']);

Route::get('password/reset/{token}', function ($token) {
    $email = request()->query('email');
    $frontend = env('FRONTEND_URL');
    $url = $frontend . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email);
    return redirect($url);
})->name('password.reset');

Route::get('/login', function () {
    $frontend = env('FRONTEND_URL');
    return redirect($frontend . '/login');
})->name('login');
