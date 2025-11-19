## 1. Database & Models — Detailed Breakdown

### 1.1. Design the `slots` table
- Fields:
    - `id` – primary key.
    - `capacity` – total seats for the slot.
    - `remaining` – current available seats (<= `capacity`).
    - Timestamps (`created_at`, `updated_at`).
- Notes:
    - `remaining` should be kept in sync with `capacity` on creation (usually set `remaining = capacity`).
    - Use an unsigned integer type for `capacity` and `remaining`.

### 1.2. Design the `holds` table
- Fields:
    - `id` – primary key.
    - `slot_id` – foreign key referencing `slots.id`.
    - `status` – enum-like string: `held`, `confirmed`, or `cancelled`.
    - `idempotency_key` – UUID string, unique, used to ensure `POST /slots/{id}/hold` is idempotent.
    - `expires_at` – datetime when the hold should be considered expired (created + 5 minutes).
    - Timestamps (`created_at`, `updated_at`).
- Constraints:
    - Foreign key on `slot_id` with cascading behavior on delete if appropriate.
    - Unique index on `idempotency_key`.

### 1.3. Create the `Slot` model
- Map to the `slots` table.
- Define fillable or guarded attributes to allow mass assignment of `capacity` and `remaining`.
- Add relationship:
    - `holds()` – one-to-many relationship to `Hold`.

### 1.4. Create the `Hold` model
- Map to the `holds` table.
- Define fillable or guarded attributes for:
    - `slot_id`, `status`, `idempotency_key`, `expires_at`.
- Casts:
    - Cast `expires_at` to a datetime type.
- Relationships:
    - `slot()` – many-to-one relationship to `Slot`.
- Scopes (optional but useful):
    - `active()` – returns holds with `status = 'held'` and `expires_at` in the future (or null).

### 1.5. Seed data for development/testing
- Create seeders or factories for:
    - `Slot` – a few slots with different `capacity` / `remaining` values.
    - `Hold` – optional initial data to test different `status` and expiration cases.
- Use them in local/dev environments to quickly test the API behavior.
