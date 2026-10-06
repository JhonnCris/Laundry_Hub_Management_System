<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /** Save the signed-in user's preferred language. */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['en', 'fil'])]]);

        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return response()->json(['locale' => $data['locale']]);
    }
}
