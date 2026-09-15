<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThemeRequest;
use Illuminate\Http\RedirectResponse;

class ThemeController extends Controller
{
    public function __invoke(UpdateThemeRequest $request): RedirectResponse
    {
        return back()->cookie('theme', $request->validated('theme'), 60 * 24 * 365);
    }
}
