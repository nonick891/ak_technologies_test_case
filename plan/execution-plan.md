# Plan: Slot Holds API (Laravel 12)

## 1. Database & Models
- Create `slots` table: `id`, `capacity`, `remaining`, timestamps.
- Create `holds` table: `id`, `slot_id`, `status` (`held`, `confirmed`, `cancelled`), `idempotency_key` (unique), `expires_at`, timestamps.
- Create `Slot` and `Hold` Eloquent models with proper relationships.

## 2. Availability Service & Caching
- Implement a `SlotAvailabilityService`:
    - Method to return all slots `{slot_id, capacity, remaining}`.
    - Cache result for 5–15 seconds.
    - Use a locking mechanism to prevent cache stampede.
    - Method to clear the cache after any state-changing operation.

## 3. Routes
- Define API routes:
    - `GET /slots/availability` → list slot availability.
    - `POST /slots/{slot}/hold` → create hold.
    - `POST /holds/{hold}/confirm` → confirm hold.
    - `DELETE /holds/{hold}` → cancel hold.

## 4. Controllers & Validation
- Create controllers (or a single controller with multiple actions).
- Use Form Request classes for:
    - Validating `Idempotency-Key` header (UUID, required).
    - Any other input if later extended.

## 5. Create Hold (`POST /slots/{id}/hold`)
- Read `Idempotency-Key` from header.
- If hold with this key exists, return its previous response (idempotency).
- In a DB transaction:
    - Lock the slot row.
    - Check `remaining > 0`, otherwise `409 Conflict`.
    - Create `held` hold with `expires_at = now() + 5 minutes`.
- Return hold data (e.g., `id`, `slot_id`, `status`, `expires_at`).

## 6. Confirm Hold (`POST /holds/{id}/confirm`)
- Load hold and its slot.
- Validate:
    - `hold` exists.
    - Not cancelled, not expired.
- In a DB transaction:
    - Lock slot row.
    - Ensure `remaining > 0`, otherwise `409 Conflict`.
    - Decrement `remaining` by 1.
    - Update hold status to `confirmed`.
- Invalidate availability cache.

## 7. Cancel Hold (`DELETE /holds/{id}`)
- Load hold and its slot.
- In a DB transaction:
    - Lock slot row.
    - If hold is `confirmed`, increment slot `remaining`.
    - Set hold status to `cancelled`.
- Treat repeated cancel as idempotent (just return current state).
- Invalidate availability cache.

## 8. Expiration Handling
- Treat expired holds as invalid for confirmation (return `409`).
- Optionally add a console command / scheduled job to mark expired `held` records as `cancelled` for cleanliness.

## 9. Testing
- Add feature tests to cover:
    - Availability caching and invalidation.
    - Hold creation (happy path, full slot, idempotency).
    - Hold confirmation (oversell protection, expired holds).
    - Hold cancellation (from `held` and `confirmed`, idempotency).
