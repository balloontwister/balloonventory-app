<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('app.supported_locales')))],
        ]);

        $locale = $validated['locale'];

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        } else {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);

        return redirect()->back();
    }
}
