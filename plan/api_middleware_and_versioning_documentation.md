# Slot Holds API – Routes Plan

## 3. Routes

This document describes the HTTP routes for the Slot Holds API, including purpose, parameters, behaviors, and response conventions.

---

### 3.1. Route Organization

- All routes are exposed via the API layer (e.g., in the API routes file).
- All routes:
    - Use the `api` middleware group (JSON-centric behavior, optional rate limiting).
    - Return JSON responses only.
- Optional conventions:
    - Common prefix like `/api` or `/api/v1` (see versioning below).
    - Consistent route names for easier reference in controllers and tests.

---

### 3.2. Route Definitions

#### 3.2.1. GET `/slots/availability`

**Purpose**  
Return the list of all slots with their availability data.

**Behavior**

- Reads from the availability service (which may serve cached data).
- Intended to be a fast, read-only endpoint.

**Request**

- Method: `GET`
- Path: `/slots/availability`
- Headers: none required beyond typical API headers (e.g., `Accept: application/json`).

**Response**

- `200 OK`
    - Body: JSON array of slot availability objects, e.g.:

      ```json
      [
        {
          "id": "<slot-id>",
          "capacity": <integer>,
          "remaining": <integer>
        }
      ]
      ```

- `500 Internal Server Error`
    - On unexpected server-side failures.
    - Body includes an error structure (see section 3.4).

---

#### 3.2.2. POST `/slots/{slot}/hold`

**Purpose**  
Create a temporary hold for a specific slot.

**Behavior**

- Ensures idempotency via the `Idempotency-Key` header:
    - If a hold has already been created for the same key, the previous result is returned.
- Delegates business logic to the “Create Hold” process.

**Request**

- Method: `POST`
- Path: `/slots/{slot}/hold`
    - Path parameter:
        - `slot`: identifier of the slot (e.g., numeric ID).
- Headers:
    - `Idempotency-Key`: required, UUID format.
    - `Content-Type: application/json` (by convention, even if body is empty).
- Body:
    - Currently no required fields in the body.
    - Designed to allow future extensions (e.g., metadata).

**Responses**

- `201 Created`
    - Body: JSON object describing the created (or previously created) hold:

      ```json
      {
        "id": "<hold-id>",
        "slot_id": "<slot-id>",
        "status": "held",
        "expires_at": "<ISO-8601-UTC-timestamp>"
      }
      ```

- `400 Bad Request`
    - When `Idempotency-Key` header is missing or invalid.
    - Body contains an error structure.

- `404 Not Found`
    - When the referenced slot does not exist.

- `409 Conflict`
    - When the slot has no remaining capacity at the moment of hold creation.

- `422 Unprocessable Entity`
    - Reserved for additional validation errors if/when the request body is extended.

- `500 Internal Server Error`
    - On unexpected server-side failures.

---

#### 3.2.3. POST `/holds/{hold}/confirm`

**Purpose**  
Confirm an existing hold and convert it into a final reservation (decrementing the slot’s `remaining`).

**Behavior**

- Loads the hold and associated slot.
- Enforces:
    - Hold must exist.
    - Hold must not be cancelled or expired.
- In a transaction:
    - Locks the slot row.
    - Ensures `remaining > 0` to avoid overselling.
    - Confirms the hold and decrements `remaining`.
- Invalidates the availability cache after successful confirmation.

**Request**

- Method: `POST`
- Path: `/holds/{hold}/confirm`
    - Path parameter:
        - `hold`: identifier of the hold (e.g., numeric ID).
- Headers:
    - `Content-Type: application/json` (even for empty body).
- Body:
    - None required; reserved for future extensions.

**Responses**

- `200 OK`
    - Body: JSON object describing the updated hold:

      ```json
      {
        "id": "<hold-id>",
        "slot_id": "<slot-id>",
        "status": "confirmed",
        "expires_at": "<ISO-8601-UTC-timestamp>"
      }
      ```

- `404 Not Found`
    - When the hold does not exist.

- `409 Conflict`
    - When:
        - The hold has expired.
        - The hold is already in a terminal state that cannot be confirmed (e.g., `cancelled`).
        - The slot has zero remaining capacity at confirmation time.

- `422 Unprocessable Entity`
    - Reserved for validation errors if/when a body is introduced.

- `500 Internal Server Error`
    - On unexpected server-side failures.

---

#### 3.2.4. DELETE `/holds/{hold}`

**Purpose**  
Cancel an existing hold.

**Behavior**

- Loads the hold and its associated slot.
- In a transaction:
    - Locks the slot row.
    - If hold is `confirmed`, increments `remaining`.
    - Updates hold status to `cancelled`.
- Idempotent:
    - Multiple calls for the same hold return the current final state, not an error.
- Invalidates the availability cache after changes that affect remaining capacity.

**Request**

- Method: `DELETE`
- Path: `/holds/{hold}`
    - Path parameter:
        - `hold`: identifier of the hold.
- Headers:
    - `Content-Type: application/json` (convention).
- Body:
    - None required.

**Responses**

- `200 OK`
    - Body: JSON object with the current state of the hold:

      ```json
      {
        "id": "<hold-id>",
        "slot_id": "<slot-id>",
        "status": "cancelled" // or current terminal status
      }
      ```

- `404 Not Found`
    - When the hold does not exist.

- `422 Unprocessable Entity`
    - Reserved for any future validation rules (if additional input is introduced).

- `500 Internal Server Error`
    - On unexpected server-side failures.

---

### 3.3. Route Naming Conventions

To simplify controller references and testing, assign route names:

- `GET /slots/availability` → `slots.availability.index`
- `POST /slots/{slot}/hold` → `slots.holds.store`
- `POST /holds/{hold}/confirm` → `holds.confirm`
- `DELETE /holds/{hold}` → `holds.cancel`

These names should be used in tests and any internal linking to avoid hardcoding URLs.

---

### 3.4. Error Response Structure

All non-2xx responses should follow a consistent JSON error schema:
Examples:

- For missing `Idempotency-Key`:

  ```json
  {
    "error": "missing_idempotency_key",
    "message": "The Idempotency-Key header is required.",
    "details": {}
  }
  ```

- For full slot on hold creation:

  ```json
  {
    "error": "slot_full",
    "message": "No remaining capacity for this slot.",
    "details": {
      "slot_id": "<slot-id>"
    }
  }
  ```

---

### 3.5. Middleware & Cross-Cutting Concerns

- **Middleware**
  - All routes pass through the standard API middleware group.
  - Optional: introduce rate limiting, particularly for write endpoints:
    - `POST /slots/{slot}/hold`
    - `POST /holds/{hold}/confirm`
    - `DELETE /holds/{hold}`

- **Content Negotiation**
  - All endpoints return JSON.
  - Requests should send `Accept: application/json`.

- **Timestamps**
  - All timestamps in responses (e.g., `expires_at`) are ISO 8601 formatted in UTC.

---

### 3.6. Versioning (Optional)

If API versioning is required:

- Prefix paths with a version segment, such as:

  - `GET /api/v1/slots/availability`
  - `POST /api/v1/slots/{slot}/hold`
  - `POST /api/v1/holds/{hold}/confirm`
  - `DELETE /api/v1/holds/{hold}`

- Keep this document as the specification for `v1`, and allow future documents (e.g., `Routes Plan v2`) to describe breaking or additive changes without impacting existing clients.
