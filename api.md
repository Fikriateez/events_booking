# Shared backend API: Postman guide

This guide covers **16 operations across six public PHP endpoint files** in the
root `backend/`. It describes the current source implementation. The examples
have not been sent to a live XAMPP/MySQL server from this workspace; native
runtime verification remains pending.

## 1. Prepare the local application

Complete the [root XAMPP setup](README.md#local-xampp-setup) and
[guarded database initialization](docs/database-initialization.md) first:

- Start your configured Apache and MySQL/MariaDB services.
- Install the locked Composer dependencies at the repository root.
- Configure private `backend/config.local.php` with the correct database settings
  and a new `JWT_SECRET` of at least 32 bytes.
- Have an existing hashed-password Administrator account and an available venue.
  The schema contains no demo accounts or venues. Follow
  [first local accounts and venues](docs/database-initialization.md#first-local-accounts-and-venues)
  if needed. Note the real venue ID and capacity.

Use a deliberately prepared local test database for this walkthrough: POST,
PUT and DELETE requests change its records. You do not need SendGrid to test
booking persistence; an unconfigured mail service returns `email_sent:false`.

## 2. Set up Postman once

### Create and select an environment

Create a Postman environment named **Shared Backend Local**, add the variables
below, and select it as the active environment. Keep passwords/tokens in local
values rather than sharing or exporting them. See the official
[Postman environment instructions](https://learning.postman.com/docs/use/send-requests/variables/managing-environments).

| Variable | Value to enter |
|---|---|
| `base_url` | `http://localhost/web-api-project/backend` — no trailing slash |
| `login_email` | Email of the existing Administrator initially; change when logging in as another account |
| `login_password` | That account's private password |
| `test_password` | A private password you choose for the new walkthrough accounts; use 12–72 bytes |
| `admin_token` | Leave empty until Administrator login succeeds |
| `organiser_token` | Leave empty until Organiser login succeeds |
| `staff_token` | Leave empty until Staff login succeeds |
| `customer_token` | Leave empty until Customer login succeeds |
| `venue_id` | An actual available venue ID with capacity of at least 10 |
| `user_id` | Save the Organiser's returned `user_id` here |
| `event_id` | Save the main event's returned `data.event_id` here |
| `booking_id` | Save the returned `booking_id` here |
| `cleanup_user_id` | Save a separate throwaway user's ID here for the deletion example |
| `cleanup_event_id` | Save a separate unbooked event's ID here for the deletion example |

`{{base_url}}` means “use the value of the `base_url` environment variable.”
For example, `{{base_url}}/login.php` becomes
`http://localhost/web-api-project/backend/login.php`.

Replace the host, port or folder to match your installation. If Apache uses port
8080, an example base is `http://localhost:8080/web-api-project/backend`.
From a different PC, use the XAMPP server's hostname/IP, for example
`http://192.168.1.20/web-api-project/backend`; that address is illustrative.
Keep any nested project path. `localhost` refers to the PC sending the request.
Do not use the removed per-app backend directories.

### Send a request

1. Create an HTTP request and choose its method, such as **GET** or **POST**.
2. Paste the URL from the reference below. Copy only the URL into the URL field,
   not the method word shown in the request line.
3. For login/registration, choose **Authorization → No Auth**. For protected
   requests, choose **Authorization → Bearer Token** and enter the indicated
   variable, such as `{{admin_token}}`, in the Token field. Enter only the token
   variable; Postman adds `Bearer` and the Authorization header. Do not add a
   second Authorization header manually. See
   [Postman's Bearer Token instructions](https://learning.postman.com/docs/use/send-requests/authorization/authorization-types/).
4. For POST/PUT requests, choose **Body → raw → JSON** and paste the example.
   Confirm `Content-Type: application/json` in Headers. GET/DELETE examples
   have no request body. See
   [Postman's body and parameter instructions](https://learning.postman.com/docs/use/send-requests/create-requests/parameters/).
5. Set every variable used by the request, then select **Send**. Read both the
   HTTP status and the JSON response. Save the request if you want to reuse it.

Numeric ID placeholders in JSON, such as `{{event_id}}`, are intentionally
unquoted. Set their variables to positive digits, such as `7`, before sending.
The expanded body must be a JSON object. Passwords stay quoted as shown.
Example emails below are placeholders for local test accounts, not supplied
accounts or working inboxes. Use a new email if an earlier walkthrough created it.

## 3. All request URLs at a glance

“Manager” means Administrator, Organiser or Staff. Organiser/Staff may update or
delete only their own events; Administrator may manage any event. All protected
operations check the account's current database role.

| No. | Method | URL | Access | Success |
|---|---|---|---|---|
| 1 | POST | `{{base_url}}/login.php` | Public login, any role | 200 |
| 2 | POST | `{{base_url}}/register.php` | Public Customer registration | 201 |
| 3 | GET | `{{base_url}}/test_auth.php` | Any authenticated role | 200 |
| 4 | GET | `{{base_url}}/users.php` | Administrator | 200 |
| 5 | GET | `{{base_url}}/users.php?id={{user_id}}` | Administrator | 200 |
| 6 | POST | `{{base_url}}/users.php` | Administrator | 201 |
| 7 | PUT | `{{base_url}}/users.php?id={{user_id}}` | Administrator | 200 |
| 8 | DELETE | `{{base_url}}/users.php?id={{cleanup_user_id}}` | Administrator | 200 |
| 9 | GET | `{{base_url}}/events.php` | Any authenticated role | 200 |
| 10 | GET | `{{base_url}}/events.php?id={{event_id}}` | Any authenticated role | 200 |
| 11 | POST | `{{base_url}}/events.php` | Manager | 201 |
| 12 | PUT | `{{base_url}}/events.php?id={{event_id}}` | Manager; ownership applies | 200 |
| 13 | DELETE | `{{base_url}}/events.php?id={{cleanup_event_id}}` | Manager; ownership applies | 200 |
| 14 | GET | `{{base_url}}/bookings.php` | Customer | 200 |
| 15 | POST | `{{base_url}}/bookings.php` | Customer | 200 |
| 16 | PUT | `{{base_url}}/bookings.php` | Customer, own booking | 200 |

Protected requests can return `401` for a missing/invalid/expired token and
`403` for an unauthorized role. Other request-specific errors are listed below;
the [troubleshooting table](#7-troubleshooting) covers common input/server errors.
IDs in responses may be numbers or digit strings; save the actual value without
JSON quotes in the corresponding Postman ID variable.

## 4. Request reference

### Request 1: Log in

```http
POST {{base_url}}/login.php
```

**Authorization:** No Auth. **Body:** raw JSON.

```json
{
  "email": "{{login_email}}",
  "password": "{{login_password}}"
}
```

Initially use your existing Administrator's email/password. A successful
**200** response contains `success`, `message`, `token` and `user`:

```json
{
  "success": true,
  "message": "Login successful",
  "token": "<returned JWT token>",
  "user": {
    "user_id": 1,
    "name": "Local Administrator",
    "email": "your-admin@example.test",
    "role": "Administrator"
  }
}
```

This response is illustrative. Copy the real `token` value, without surrounding
quotes, into `admin_token`. Do not copy `<returned JWT token>`.

Repeat this same request after changing `login_email` and `login_password` to
another account. Save its token in the matching variable:

| Returned `user.role` | Save `token` into |
|---|---|
| `Administrator` | `admin_token` |
| `Organiser` | `organiser_token` |
| `Staff` | `staff_token` |
| `Customer` | `customer_token` |

Tokens last **one hour**. Sign in again to replace an expired token. Deleted
accounts lose access, and role changes take effect on the next request. There
is no refresh-token endpoint. Common errors: **400** for invalid/missing input;
**401** for an incorrect email/password or unsupported stored role.

### Request 2: Register a Customer

```http
POST {{base_url}}/register.php
```

**Authorization:** No Auth. **Body:** raw JSON.

```json
{
  "name": "Postman Customer",
  "email": "customer.postman@example.test",
  "password": "{{test_password}}",
  "role": "Customer"
}
```

**Success: 201**, with `success`, `message` and top-level `user_id`. Save that ID
if you want to inspect the account later. Registration does not return a token;
log in using Request 1 and save the token in `customer_token`.

`name`, `email` and `password` are required. Name/email are limited to 100
characters; email must be valid. Passwords must be nonempty, at most 72 bytes,
and contain no null byte. `role` is optional and defaults to `Customer`.
Public registration cannot create privileged accounts: another role returns
**400**. A duplicate email returns **409**.

### Request 3: Check the current token and account

```http
GET {{base_url}}/test_auth.php
```

**Authorization:** Bearer Token, for example `{{admin_token}}`. **Body:** none.
No query parameters are needed.

**Success: 200**, with `success:true`, `message:"Token is valid"` and `user`
containing `user_id`, `name`, `email`, `role`. No password/hash is returned.
Repeat with each role's token to confirm the account. Missing/expired/invalid
tokens and deleted accounts return **401**.

### Request 4: List all users

```http
GET {{base_url}}/users.php
```

**Authorization:** `{{admin_token}}`. **Body:** none. No query parameters needed.

**Success: 200**, with `success:true` and `data` as an array of users, ordered by
`user_id` ascending. Rows include `user_id`, `name`, `email`, `role`; passwords
are excluded. User listing has no implemented search/filter/pagination options.
A non-Administrator token returns **403**.

### Request 5: Get one user

```http
GET {{base_url}}/users.php?id={{user_id}}
```

**Authorization:** `{{admin_token}}`. **Body:** none. Query `id` is the positive
ID of the user to inspect.

**Success: 200**, with `success:true` and `data` as one user object containing
`user_id`, `name`, `email`, `role`. An invalid ID returns **400**, a missing user
**404**, and a non-Administrator **403**.

### Request 6: Create a user as Administrator

```http
POST {{base_url}}/users.php
```

**Authorization:** `{{admin_token}}`. **Body:** raw JSON.

```json
{
  "name": "Postman Organiser",
  "email": "organiser.postman@example.test",
  "password": "{{test_password}}",
  "role": "Organiser"
}
```

**Success: 201**, with `success`, `message` and top-level `user_id`. Save this
Organiser's ID in `user_id`. Repeat to create Staff, changing name/email and
`role` to `Staff`; do not overwrite the Organiser's saved `user_id`.

`name`, `email`, `password` are required and use the registration validation
rules. `role` may be `Administrator`, `Organiser`, `Staff` or `Customer`, with
exact casing; omitting it defaults to `Customer`. Only an Administrator can
create privileged accounts. Invalid fields/role return **400**, a wrong caller
role **403**, and an existing email **409**. No token is returned; log in next.

### Request 7: Update a user

```http
PUT {{base_url}}/users.php?id={{user_id}}
```

**Authorization:** `{{admin_token}}`. **Body:** raw JSON.

```json
{
  "name": "Postman Organiser Updated",
  "email": "organiser.postman@example.test",
  "role": "Organiser"
}
```

Send **all three fields** (`name`, `email`, `role`), even when changing only one.
The ID is in the query, not the body. **Success: 200**, with `success:true` and
`message:"User updated successfully"`; it does not return the updated user.
Use Request 5 to read it. This operation **preserves the password** and does not
provide a password-change feature.

Missing/invalid ID or fields return **400**, wrong caller role **403**, missing
user **404**, and conflicting email **409**. Demoting a user changes what their
already-issued token can access on the next request.

### Request 8: Delete an unreferenced test user

```http
DELETE {{base_url}}/users.php?id={{cleanup_user_id}}
```

**Authorization:** `{{admin_token}}`. **Body:** none. Query `id` is required.

First use Request 6 to create a separate Customer named `Postman Cleanup User`
with email `cleanup.postman@example.test`. Save its returned ID in
`cleanup_user_id`. Do not create events/bookings for that account. Do not use
your Administrator, main Customer or Organiser ID for this example.

**Success: 200**, with `success:true` and `message:"User deleted successfully"`.
Missing/invalid ID returns **400**, wrong caller role **403**, missing user
**404**, and any dependent event/booking record **409**. Referenced records are
preserved, including references from cancelled bookings.

### Request 9: List, filter and sort events

```http
GET {{base_url}}/events.php
```

**Authorization:** `{{organiser_token}}`, `{{admin_token}}`, `{{staff_token}}`
or `{{customer_token}}`. **Body:** none.

Customers see only `published` events. Other roles can browse all statuses;
Organiser/Staff browsing is not limited to their own events. Add any supported
query parameters using Postman's **Params** tab:

| Parameter | Accepted value / behavior |
|---|---|
| `search` | Text matched against title or description |
| `status` | `draft`, `published`, `cancelled`, `completed` — lowercase |
| `venue_id` | Positive venue ID to filter by; an unknown ID produces an empty list |
| `date_from` | Valid date/time, such as `2099-01-01`; includes events on/after it |
| `date_to` | Valid date/time; a date alone includes that entire day |
| `min_price` | Number at least 0, inclusive lower price bound |
| `max_price` | Number at least 0, inclusive upper price bound |
| `sort` | `event_date` (default), `ticket_price` or `title` |
| `order` | `asc` (default) or `desc`; case-insensitive |
| `page` | Integer at least 1; default 1 |
| `limit` | Integer 1–100; default 10 |

Example URL:

```text
{{base_url}}/events.php?search=Postman&status=published&venue_id={{venue_id}}&date_from=2099-01-01&date_to=2099-12-31&min_price=0&max_price=100&sort=ticket_price&order=asc&page=1&limit=10
```

**Success: 200**, with `success`, `message`, `data` (event array) and
`pagination` containing `page`, `limit`, `total`, `totalPages`. Event rows include
IDs, title/description, venue/name, organiser ID, date, ticket price, total/
available tickets and status. No matches is a successful empty list. Customers
filtering for an unpublished status receive an empty list. Invalid parameters
return **400**, commonly with `errors:[{"field":"...","message":"..."}]`.
Event status values are case-sensitive: `Published` is invalid.

### Request 10: Get one event

```http
GET {{base_url}}/events.php?id={{event_id}}
```

**Authorization:** any authenticated role's token. **Body:** none. Query `id`
is the positive event ID. Use either `id` for detail or list filters from Request
9; detail retrieval does not apply the list filters.

**Success: 200**, with `success`, `message` and `data` as one event object,
including venue name/location and `available_tickets`. An invalid ID returns
**400**. A missing event returns **404**; a Customer also gets **404** for an
event that is not published.

### Request 11: Create a published event

```http
POST {{base_url}}/events.php
```

**Authorization:** `{{organiser_token}}` for the walkthrough; Administrator and
Staff can also create events. **Body:** raw JSON.

```json
{
  "title": "Postman Workshop",
  "description": "Local API walkthrough event",
  "venue_id": {{venue_id}},
  "event_date": "2099-12-01 10:00:00",
  "ticket_price": "12.34",
  "total_tickets": 10,
  "status": "published"
}
```

Choose a date/time in the future, using `YYYY-MM-DD HH:MM:SS` and the application's
configured timezone (default `Asia/Kuala_Lumpur`). The sample date is illustrative.

| Field | Required? | Rule |
|---|---|---|
| `title` | Yes | 3–150 characters after trimming |
| `description` | No | Use text if supplied |
| `venue_id` | Yes | Positive ID of an existing, available venue |
| `event_date` | Yes | Valid future date/time |
| `ticket_price` | Yes | 0–99999999.99, at most two decimal places; decimal string is accepted |
| `total_tickets` | Yes | Integer 1–2147483647, no greater than venue capacity |
| `status` | No | `draft` (default), `published`, `cancelled`, `completed` |

The server sets `organiser_id` from the signed-in account and initializes
`available_tickets` from `total_tickets`; do not supply those fields.

**Success: 201**, with `success:true`, `message:"Event created"` and the event
object in `data`. Save **`data.event_id`** in `event_id`; there is no top-level
`event_id`. Invalid fields/capacity return **400**, Customer access **403**,
missing venue **404**, and an unavailable venue **409**.

### Request 12: Update an event

```http
PUT {{base_url}}/events.php?id={{event_id}}
```

**Authorization:** `{{organiser_token}}` that created the event, or
`{{admin_token}}`. Staff can update their own events too. **Body:** raw JSON.

```json
{
  "description": "Updated through Postman"
}
```

Unlike user updates, send only the fields you want to change. Supported fields
are `title`, `description`, `venue_id`, `event_date`, `ticket_price`,
`total_tickets`, `status`, with the rules described in Request 11. Send at least
one supported field. The server coordinates availability when capacity changes;
do not send `available_tickets` or `organiser_id`.

**Success: 200**, with `success`, `message` and the updated event in `data`.
Missing/invalid ID or fields, no supported fields, and excess venue capacity
return **400**. Wrong role/ownership returns **403**; missing event/venue **404**;
capacity below active booked quantity, an unavailable replacement venue or
inconsistent inventory **409**. Changing event status does not automatically
cancel bookings or issue refunds. Editing price does not rewrite old booking totals.

### Request 13: Delete a separate unbooked event

```http
DELETE {{base_url}}/events.php?id={{cleanup_event_id}}
```

**Authorization:** the manager who created that event, or `{{admin_token}}`.
**Body:** none. Query `id` is required.

First repeat Request 11 to create another event, changing the title to
`Postman Cleanup Event` and status to `draft`. Save its returned `data.event_id`
in **`cleanup_event_id`**, leaving the main `event_id` unchanged. Do not book it.

**Success: 200**, with `success:true` and `message:"Event deleted"`.
Missing/invalid ID returns **400**, wrong role/ownership **403**, missing event
**404**, and any booking reference or inconsistent inventory **409**.
**Cancelled bookings still prevent event deletion.** Setting an event's status
to `cancelled` is available when deletion is blocked; it does not cancel its bookings.

### Request 14: View your booking history

```http
GET {{base_url}}/bookings.php
```

**Authorization:** `{{customer_token}}`. **Body:** none.

| Parameter | Accepted value / behavior |
|---|---|
| `page` | Integer 1–2147483647; default 1 |
| `limit` | Integer 1–50; default 10 |
| `search` | Event title or booking number |
| `status` | `Pending`, `Confirmed`, `Cancelled` — capitalized; omit/empty for all |

Booking status values are case-sensitive: `confirmed` is invalid. Booking
history's maximum page size is 50, compared with 100 for the event list.

Example URL:

```text
{{base_url}}/bookings.php?page=1&limit=10&search=Postman&status=Confirmed
```

**Success: 200**, with `success`, `data` (booking array), `user` (current
Customer) and `pagination` containing `page`, `limit`, `total`, `totalPages`,
`search`, `status`. Rows include `booking_id`, `user_id`, `event_id`, `quantity`,
`total_price`, `booking_date`, `booking_status`, `event_name`, `event_date` and
email. Results are newest booking ID first. No matches returns `data:[]`.

You can see only your own bookings, regardless of query values; there is no
implemented `user_id` or detail `id` filter here. Search by booking number to
find a particular booking. Invalid pagination/status returns **400**, and any
non-Customer token returns **403**.

### Request 15: Create a booking

```http
POST {{base_url}}/bookings.php
```

**Authorization:** `{{customer_token}}`. **Body:** raw JSON.

```json
{
  "event_id": {{event_id}},
  "quantity": 2
}
```

`event_id` must be a positive ID. `quantity` must be a whole number **1–10**.
The event must be published and have enough stock. The server gets `user_id`
from the account, calculates the total from the stored price, and creates a
`Confirmed` booking. Do not send `user_id`, `total_price` or a booking status.

**Success: 200**, with `success`, `message`, top-level `booking_id`, numeric
`total_price`, `email_type`, `email_subject`, `email_sent` and `email_message`.
Save **`booking_id`** in the environment. With two tickets at RM 12.34,
`total_price` is the JSON number `24.68`.

Booking/stock changes commit together. Read Request 10 afterward: the sample
event's availability becomes 8 if no other requests changed it. Use Request 14
to inspect persisted quantities/totals; history may return decimal values as
strings, which does not change their monetary value.

Invalid ID/quantity or an overflowing total returns **400**, non-Customer access
**403**, and a missing/unpublished event, insufficient stock or inconsistent
inventory **409**. A missing event on booking creation uses **409**, even though
event-detail retrieval would use 404. Email failure does not undo booking success.

### Request 16: Cancel your booking

```http
PUT {{base_url}}/bookings.php
```

**Authorization:** the same `{{customer_token}}` that owns the booking.
**Body:** raw JSON. The booking ID goes in the body, not `?id=`.

```json
{
  "booking_id": {{booking_id}},
  "booking_status": "Cancelled"
}
```

`booking_id` must be positive. The only supported mutation status is exactly
`Cancelled`; omitting `booking_status` also defaults to cancellation.

**Success: 200**, with `success`, `message`, `booking_id`, `email_type`,
`email_subject`, `email_sent`, `email_message`. Cancellation does not include a
`total_price` field; retrieve the persisted amount through Request 14. The exact
booking becomes `Cancelled` and its ticket quantity is restored once. For the
sample, Request 10 shows availability 10 again, assuming no other changes.

Invalid ID/status returns **400**, a non-Customer **403**, missing/foreign booking
**404**, and an already-cancelled booking or inconsistent inventory **409**.
Repeating this PUT does not restore stock again. Cancellation is not deletion
and does not process a payment refund.

## 5. Suggested walkthrough order

The reference numbers above group endpoints. For a first run, follow this order:

1. **Administrator login:** Request 1 with your existing account; save
   `admin_token`. Confirm its role using Request 3.
2. **Create managers:** Request 6 once for Organiser and again for Staff. Keep
   the Organiser's returned ID in `user_id`. Try Requests 4, 5 and 7 as Admin.
3. **Register Customer:** Request 2. Use unique test emails if rerunning.
4. **Log in as each new account:** Request 1 using each created email and the
   chosen `test_password` as `login_password`. Save the respective Organiser,
   Staff and Customer tokens. Request 3 confirms each role. Changing login
   variables does not change the other tokens you already saved.
5. **Publish:** Request 11 with `organiser_token` and the actual `venue_id`.
   Save `data.event_id` in `event_id`.
6. **Browse/update:** Requests 9 and 10 with Customer and Organiser tokens;
   Request 12 with the event owner's token. Keep the event published for booking.
7. **Book:** Request 15 with Customer token; save `booking_id`. Read Requests
   10 and 14 to check stock and persisted booking details.
8. **Cancel:** Request 16 with the same Customer token. Check Request 14 with
   `status=Cancelled` and Request 10 for restored availability. A repeated
   cancellation should return 409.
9. **Test deletion separately:** Create the throwaway user and unbooked event
   described in Requests 8 and 13, save their separate cleanup IDs, then delete
   those records. Deleting the main booked event returns 409 even after cancellation.

## 6. OPTIONS and URLs that are not resource endpoints

Every public endpoint supports **OPTIONS** for preflight. For example:

```http
OPTIONS {{base_url}}/events.php
```

Use **No Auth** and no body. With valid server configuration and no Origin
header, expect **204 No Content**, an empty body and allowed-method/header
information. To simulate browser preflight, also set an allowed `Origin` and
`Access-Control-Request-Method: POST`. The methods listed in the overview are
the application operations; OPTIONS is additional preflight support.

Unsupported methods return **405**, such as `PATCH users.php`, `GET login.php`
or `DELETE bookings.php`. The optional `Origin` header is unnecessary for normal
Postman requests; sending an unapproved origin can produce **403**.

`auth.php`, `db.php`, configuration files and `backend/lib/` are internal helpers,
not public API resources; Apache denies direct access. There is no `venues.php`
CRUD API, booking DELETE/detail endpoint, payment API, logout endpoint or token
refresh endpoint. Logout in a client removes its saved token; it does not revoke
an otherwise valid JWT server-side. Use the manual local setup for initial venues.
The original per-app backend URL directories remain disabled.

## 7. Troubleshooting

| Result | What to check |
|---|---|
| Cannot connect / HTML 404 | Apache running, correct host/port/project mount, URL ends with the actual `.php` endpoint; inspect Postman's response body |
| `400` | Required fields, JSON object syntax, positive IDs, supported values and correct status casing; event errors may include an `errors` array |
| `401` | Correct account credentials or token; use the right saved token without quotes or an extra `Bearer`; sign in again after expiry/key rotation; a deleted account must be replaced with an existing one |
| `403` | Required current database role and event ownership; do not use Administrator tokens for Customer bookings; remove an unnecessary Origin header |
| `404` JSON | Requested user/event is missing; unpublished event is hidden from Customer; cancellation ID is missing or owned by another Customer |
| `405` | Use the method in the endpoint table; no PATCH, booking DELETE or GET login |
| `409` | Duplicate email, unavailable venue/event/stock, dependent records, repeated cancellation, capacity conflict or inconsistent inventory; read `message` |
| `413` | JSON request body exceeds the 1 MiB limit |
| `415` | Select raw JSON and send `Content-Type: application/json`, not form-data or URL-encoded data |
| `500` — `Backend configuration is invalid` | Private PHP configuration returns an array; values are strings/integers; DB_PORT is 1–65535; APP_TIMEZONE is valid, such as Asia/Kuala_Lumpur; check overriding Apache/process environment values |
| `500` — `Backend authentication is not configured` | Set a private JWT_SECRET of at least 32 bytes; this is a different error from invalid configuration |
| `500` — dependency/database errors | Install root Composer dependencies; verify PHP extensions, MySQL service, database existence and private connection settings; do not reset an existing database |
| `200`, `success:true`, `email_sent:false` | Booking/cancellation succeeded; investigate mail settings separately rather than resending the mutation |

`.env` files are not automatically loaded. Environment values override
`backend/config.local.php`, including empty values. Restart Apache after changing
its environment settings. Backend diagnostics in PHP/Apache logs use sanitized error categories;
look for `Unable to load backend configuration (...)` for the configuration error.
The application does not expose raw SQL, passwords, JWT secrets or stack traces
in API errors.

For successful booking/cancellation, `email_sent:true` means SendGrid accepted
the request, not confirmed inbox delivery. The example `.test` addresses are
not controlled inboxes. If validating actual delivery, deliberately use your
controlled recipient and configured verified sender. See
[notification behavior](docs/bookings.md) and
[native runtime/readiness procedure](docs/regression.md).

Use [finalization evidence](docs/finalization.md) for the existing isolated
regression coverage. This documentation adds no live Postman, MySQL or SendGrid
test result and does not change backend behavior.
