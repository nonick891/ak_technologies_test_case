<?php

namespace App\Http\Controllers;

use App\SlotHoldApp\Services\SlotService;
use Illuminate\Http\JsonResponse;

class SlotAvailabilityController extends Controller
{
    protected SlotService $slotService;

    public function __construct() {
        $this->slotService = new SlotService(
            config('slot_availability_cache')
        );
    }

    /**
     * GET /slots/availability
     */
    public function index(): JsonResponse
    {
        // Ask the service for current availability (possibly cached)
        $slots = $this->slotService->getAvailability();

        return response()->json($slots);
    }
}
