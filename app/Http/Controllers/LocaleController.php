<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const SUPPORTED_LOCALES = ['en', 'pt_BR', 'es'];

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED_LOCALES, true), 404);

        $request->user()->update(['locale' => $locale]);

        return back();
    }
}
