<?php

namespace App\Http\Controllers;

use App\SlotHoldApp\Services\SlotAvailabilityService;
use Illuminate\Http\JsonResponse;

class SlotAvailabilityController extends Controller
{
    protected SlotAvailabilityService $availabilityService;

    public function __construct() {
        $this->availabilityService = new SlotAvailabilityService(
            config('slot_availability_cache')
        );
    }

    /**
     * GET /slots/availability
     */
    public function index(): JsonResponse
    {
        // Ask the service for current availability (possibly cached)
        $slots = $this->availabilityService->getAvailability();

        return response()->json($slots);
    }
}
