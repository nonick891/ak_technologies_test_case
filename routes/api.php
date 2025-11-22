<?php

use App\Http\Controllers\HoldController;
use App\Http\Controllers\SlotAvailabilityController;
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
*/

// GET /slots/availability
Route::get('/slots/availability', [SlotAvailabilityController::class, 'index'])
    ->name('slots.availability.index');

// POST /slots/{slot}/hold
Route::post('/slots/{slot}/hold', [HoldController::class, 'store'])
    ->name('slots.holds.store');

// POST /holds/{hold}/confirm
Route::post('/holds/{hold}/confirm', [HoldController::class, 'confirm'])
    ->name('holds.confirm');

// DELETE /holds/{hold}
Route::delete('/holds/{hold}', [HoldController::class, 'cancel'])
    ->name('holds.cancel');

