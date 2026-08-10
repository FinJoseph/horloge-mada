<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $locales = ['fr', 'mg', 'en', 'hi', 'zh'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (! is_string($locale) || ! in_array($locale, $this->locales, true)) {
            $locale = (string) $request->query('lang', config('app.locale'));
        }

        if (! in_array($locale, $this->locales, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
