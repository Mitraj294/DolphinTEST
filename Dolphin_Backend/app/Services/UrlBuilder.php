<?php

namespace App\Services;

class UrlBuilder
{
    public static function base(): string
    {
        $base = config('app.frontend_url')
            ?? env('FRONTEND_URL')
            ?? config('app.url')
            ?? 'http://localhost:8080';
        return rtrim($base, '/');
    }
    public static function join(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return self::base() . $path;
    }
    public static function assessmentsPath(?int $id = null): string
    {
        return '/assessments' . ($id ? ('/' . $id) : '');
    }
    public static function assessmentsUrl(?int $id = null): string
    {
        return self::join(self::assessmentsPath($id));
    }
    public static function subscriptionsSuccessUrl(): string
    {
        return self::join('/subscriptions/success?session_id={CHECKOUT_SESSION_ID}');
    }
    public static function subscriptionsCancelledUrl(): string
    {
        return self::join('/subscriptions/cancelled');
    }
    public static function registerUrl(): string
    {
        return self::join('/register');
    }
}
return self::join(self::assessmentsPath($id));
