<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Service;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Services', 'The public service catalog.', weight: 1)]
class ServiceController extends Controller
{
    /**
     * List services.
     *
     * The public service catalog. Only active services are returned.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        $services = Service::active()->with('govEntity')->orderBy('id')->get();

        return ServiceResource::collection($services);
    }

    /**
     * Show a service.
     *
     * @unauthenticated
     */
    public function show(Service $service): ServiceResource
    {
        abort_unless($service->is_active, 404);

        return ServiceResource::make($service->load('govEntity'));
    }
}
