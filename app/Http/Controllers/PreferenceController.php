<?php
namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['locale' => ['sometimes','in:id,en'], 'theme' => ['sometimes','in:dark,light']]));
        return back();
    }
}
