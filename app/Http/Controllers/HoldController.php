<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\CreateHoldRequest;
use App\SlotHoldApp\Services\SlotService;
use Symfony\Component\HttpFoundation\Response;

class HoldController extends Controller
{
    public function __construct(
        protected SlotService $slotService
    ) {
    }

    /**
     * POST /slots/{slot}/hold
     */
    public function store(CreateHoldRequest $request, Slot $slot): JsonResponse
    {
        $idempotencyKey = $request->getIdempotencyKey();

        // 1. Check if a hold with this idempotency key already exists
        $existingHold = Hold::where('idempotency_key', $idempotencyKey)->first();

        if ($existingHold) {
            return $this->holdResponse($existingHold, Response::HTTP_OK);
        }

        // 2. Create the new hold within a transaction, with row-level lock
        $hold = $this->slotService->createHold($slot, $idempotencyKey);

        return $this->holdResponse($hold, Response::HTTP_CREATED);
    }

    /**
     * POST /holds/{hold}/confirm
     */
    public function confirm(Hold $hold): JsonResponse
    {
        // Reload hold with relation for safety
        $hold->load('slot');

        if ($hold->isCancelled()) {
            return $this->conflictError('Hold is already cancelled.');
        }

        if ($hold->isExpired()) {
            return $this->conflictError('Hold has expired.');
        }

        if ($hold->isConfirmed()) {
            // Treat as idempotent confirm
            return $this->holdResponse($hold, Response::HTTP_OK);
        }

        $this->slotService->confirmHold($hold);

        // Invalidate cached availability after state change
        $this->slotService->invalidateAvailabilityCache();

        return $this->holdResponse($hold->fresh('slot'), Response::HTTP_OK);
    }

    /**
     * DELETE /holds/{hold}
     */
    public function cancel(Hold $hold): JsonResponse
    {
        $hold->load('slot');

        // Idempotent: if already cancelled, just return current state
        if ($hold->isCancelled()) {
            return $this->holdResponse($hold, Response::HTTP_OK);
        }

        $this->slotService->cancelHold($hold);

        // Invalidate cached availability after state change
        $this->slotService->invalidateAvailabilityCache();

        return $this->holdResponse($hold->fresh('slot'), Response::HTTP_OK);
    }

    /**
     * Helper: format hold JSON response.
     */
    protected function holdResponse(Hold $hold, int $status): JsonResponse
    {
        return response()->json([
            'id'         => $hold->id,
            'slot_id'    => $hold->slot_id,
            'status'     => $hold->status,
            'expires_at' => optional($hold->expires_at)->toIso8601String(),
        ], $status);
    }

    /**
     * Helper: unified 409 JSON error.
     */
    protected function conflictError(string $message, array $details = []): JsonResponse
    {
        return $this->errorResponse(
            'conflict',
            $message,
            $details,
            Response::HTTP_CONFLICT
        );
    }
}
