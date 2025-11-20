<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Slot Holds API Routes
|--------------------------------------------------------------------------
| Defined according to plan without versioned prefixes.
| All responses are JSON. Placeholder handlers return 501 until implemented.
*/
Route::middleware('api')->group(function () {
    // GET /slots/availability
    Route::get('/slots/availability', function () {
        return response()->json([
            'error' => 'not_implemented',
            'message' => 'Endpoint not implemented yet.'
        ], 501);
    })->name('slots.availability.index');

    // POST /slots/{slot}/hold
    Route::post('/slots/{slot}/hold', function (Request $request, $slot) {
        return response()->json([
            'error' => 'not_implemented',
            'message' => 'Endpoint not implemented yet.',
            'details' => [
                'slot' => $slot,
            ],
        ], 501);
    })->name('slots.holds.store');

    // POST /holds/{hold}/confirm
    Route::post('/holds/{hold}/confirm', function ($hold) {
        return response()->json([
            'error' => 'not_implemented',
            'message' => 'Endpoint not implemented yet.',
            'details' => [
                'hold' => $hold,
            ],
        ], 501);
    })->name('holds.confirm');

    // DELETE /holds/{hold}
    Route::delete('/holds/{hold}', function ($hold) {
        return response()->json([
            'error' => 'not_implemented',
            'message' => 'Endpoint not implemented yet.',
            'details' => [
                'hold' => $hold,
            ],
        ], 501);
    })->name('holds.cancel');
});
