<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Service;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $featuredService = Service::active()
            ->where('code', Service::GOOD_CONDUCT)
            ->with('govEntity')
            ->first();

        return Inertia::render('landing', [
            // Round-trip through JSON so nested resources serialize exactly as the API returns them.
            'featuredService' => $featuredService ? json_decode(ServiceResource::make($featuredService)->toJson(), true) : null,
        ]);
    }
}
