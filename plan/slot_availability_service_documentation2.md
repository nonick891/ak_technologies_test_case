## Plan: Availability Service & Caching

### 2.1. Define configuration & constants
- Decide on:
  - Cache key name (e.g. `slots.availability`).
  - Cache TTL (e.g. 10 seconds, within the 5–15s range).
  - Lock key name (e.g. `slots.availability.lock`).
  - Lock timeout (e.g. 5 seconds).

### 2.2. Create `SlotAvailabilityService` class
- Location: `app/Services/SlotAvailabilityService.php`.
- Inject dependencies (e.g. `Slot` model, cache/lock interfaces) via constructor.

### 2.3. Implement method to list availability
- Method: `getAvailability(): array`.
- Responsibilities:
  - Try to read availability from cache using the configured key.
  - If cached value exists, return it.
  - If not cached:
    - Acquire a lock to avoid cache stampede.
    - While holding the lock:
      - Re-check cache in case another process populated it.
      - If still missing:
        - Query `slots` table.
        - Transform result into array of `{slot_id, capacity, remaining}`.
        - Store in cache with configured TTL.
    - Release the lock.
  - Return the final availability array.

### 2.4. Implement cache stampede protection
- Use a locking mechanism provided by Laravel’s cache/lock system.
- Ensure:
  - Lock acquire timeout is reasonable (e.g. a few seconds).
  - If lock cannot be acquired:
    - Fall back to stale cache if available, or
    - As a last resort, query directly without caching to avoid failure.

### 2.5. Implement cache invalidation method
- Method: `clearAvailabilityCache(): void`.
- Responsibilities:
  - Remove the availability cache entry (using the same key).
- This will be called from write operations that affect availability.

### 2.6. Integrate with write operations
- Identify all state-changing operations that affect `remaining`:
  - Hold creation (when slot is locked and `remaining` is checked).
  - Hold confirmation (when `remaining` is decremented).
  - Hold cancellation (when `remaining` might be incremented).
- After each successful state change:
  - Call `SlotAvailabilityService::clearAvailabilityCache()`.

### 2.7. Add tests for the service
- Create tests that verify:
  - `getAvailability()` hits the database on first call and caches the result.
  - Subsequent calls within TTL use the cache.
  - Locking mechanism prevents multiple concurrent cache repopulations.
  - `clearAvailabilityCache()` causes `getAvailability()` to repopulate from the database.
  - Availability reflects updates to `remaining` after state-changing operations.
