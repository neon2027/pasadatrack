<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OnboardingController extends Controller
{
    public function store(Request $request): Response
    {
        $request->user()->newQuery()
            ->whereKey($request->user()->getKey())
            ->whereNull('onboarding_dismissed_at')
            ->update(['onboarding_dismissed_at' => now()]);

        return response()->noContent();
    }
}
