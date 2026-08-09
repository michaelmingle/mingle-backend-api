# mingle-backend-api

Backend REST API for **Mingle**, a premium professional networking app. Mingle is
built for meeting people at conferences, meetups and coworking spaces — swapping
context and contact details with the right person in the room. It is a
*networking* product, not a dating one: everything below (discoverability,
"looking for", contact sharing) is framed around professional intent.

Built on Laravel 11 with Sanctum bearer-token auth for the mobile clients.

---

## Requirements

- PHP 8.2+ (developed against 8.4)
- Composer 2
- MySQL 8 (production) — the test suite runs on SQLite, see [Tests](#tests)

## Setup

```bash
composer install

cp .env.example .env
php artisan key:generate

# point DB_* at your MySQL database, then:
php artisan migrate
php artisan db:seed          # skills, interests and plans only

# local/QA demo data: an admin, six members around one venue, two events
php artisan db:seed --class=DemoSeeder

# avatars are stored on the "public" disk by default
php artisan storage:link

php artisan serve
```

Demo accounts all use the password `password`; the admin is `admin@mingle.test`.

### Sanctum

The API uses Sanctum's **token** guard, not the SPA cookie flow — there is no
CSRF/cookie dance to perform. `POST /api/auth/register` and `POST /api/auth/login`
return a plain-text token; send it on every subsequent request as:

```
Authorization: Bearer <token>
```

`php artisan install:api` has already been run, so `personal_access_tokens` is
part of the migration set and no further Sanctum configuration is needed for
mobile clients.

### Environment knobs

Beyond the standard Laravel keys, `config/mingle.php` reads:

| Key | Meaning |
| --- | --- |
| `MINGLE_UPLOAD_DISK` | Disk for avatars and banners (`public`, `local`, `s3`) |
| `MINGLE_UPLOAD_MAX_KB` | Upload size cap in kilobytes |
| `MINGLE_DEFAULT_DISCOVERY_RADIUS` | Radius (metres) given to new profiles |
| `MINGLE_MAX_DISCOVERY_RADIUS` | Hard cap a client may request |
| `MINGLE_QR_DEEP_LINK_BASE` | Prefix for personal QR deep links |
| `FCM_ENABLED` / `FCM_PROJECT_ID` / `FCM_CREDENTIALS_PATH` / `FCM_CREDENTIALS_JSON` | Push config — see *Push notifications* below |
| `PAYMENTS_DRIVER` | Payment config — see *Not implemented* below |

## Tests

```bash
php artisan test
```

`phpunit.xml` pins `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`, so the suite
needs no database server. `config/database.php` still defaults to `mysql`, so
production configuration is unaffected. All migrations are written to be portable
between the two — no MySQL-only column types, and the haversine SQL uses only
`sin`/`cos`/`asin`/`sqrt`, which both engines provide.

105 feature and unit tests cover registration and login, profile updates and the
completion percentage, the discoverability toggle, nearby inclusion/exclusion
rules (radius, discoverability flag, blocks, suspended accounts), the
request → accept → connections-list flow, event creation/join/attendee lists and
their `is_networking_enabled` gate, block-prevents-connect, QR issue/rotate/scan,
search, notifications, the admin surface, device registration, the FCM driver
(against a faked HTTP client and a stubbed token provider, so no real Google
credentials or network access are needed to run the suite), the
enabled+configured push binding logic, and the two scheduled commands.

---

## Response shape

Every JSON response uses one envelope:

```jsonc
{ "success": true, "data": { }, "message": "Profile updated." }
```

Failures keep the same shape and add `errors` for validation:

```jsonc
{ "success": false, "data": null, "message": "The given data was invalid.",
  "errors": { "email": ["The email has already been taken."] } }
```

401, 403, 404, 405, 422 and 500 all render through this envelope
(`bootstrap/app.php`). Paginated endpoints return `data.items` plus a
`data.meta` block (`current_page`, `per_page`, `total`, `last_page`, `has_more`).

## Endpoints

All routes are prefixed `/api`. Everything except register and login requires
`auth:sanctum`; the `active` middleware additionally rejects suspended and
banned accounts that still hold a valid token.

**Auth** — `POST auth/register`, `POST auth/login`, `POST auth/logout`, `GET auth/me`

**Profile** — `GET|PUT profile`, `POST profile/photo`, `GET profile/completion`,
`PUT profile/skills`, `PUT profile/interests`, `PUT profile/privacy`,
plus `GET skills` and `GET interests` for onboarding pickers

**Nearby** — `PUT nearby/discoverability`, `GET nearby?lat=&lng=&filter=&sort=`
(`closest|relevant|shared_interests|profession`)

**Users** — `GET users/{id}`, `POST users/{id}/connect`, `POST|DELETE users/{id}/block`,
`POST users/{id}/report`

**Connections** — `GET connections/requests?direction=`,
`POST connections/requests/{id}/accept|decline`,
`GET connections?tab=all|recent|events|favorites&search=`,
`GET|DELETE connections/{id}`, `PUT connections/{id}/favorite`,
`PUT connections/{id}/note`

**Events** — `GET|POST events`, `GET|PUT|DELETE events/{id}`,
`POST events/{id}/join|leave`, `GET events/{id}/attendees`,
`GET events/{id}/networking?looking_for=&profession=&industry=&skill=`

**QR** — `GET qr/me`, `POST qr/rotate`, `POST qr/scan`

**Search** — `GET search?q=&type=people|events|skills|all`

**Notifications** — `GET notifications`, `PUT notifications/{id}/read`,
`PUT notifications/read-all`

**Devices** — `POST devices` (register/reassign a push token),
`POST devices/unregister`

**Account** — `DELETE account`

**Admin** (`is_admin`, via the `access-admin` gate) — `GET admin/users`,
`PUT admin/users/{id}/verify|suspend|ban`, `GET admin/reports`,
`PUT admin/reports/{id}`, `GET admin/analytics/summary`

---

## Design notes

A few decisions worth knowing before reading the code.

**Connections are canonical.** A connection row always stores the smaller user id
in `user_one_id`, with a composite unique index on the pair, so a relationship can
never be duplicated by two people accepting at once. `Connection::canonicalPair()`
is the single place that ordering is decided.

**`event_connections` was deliberately skipped.** The schema sketch offered it as
optional. A connection is made at exactly one place and time, so
`connections.event_id` already answers "where did we meet" — a join table would
add a second source of truth for a one-to-one fact without buying anything.

**Enum columns are plain strings backed by PHP enums.** `ALTER TABLE` on a real
MySQL enum is a migration hazard, and enum types do not exist in SQLite. Every
status/visibility column is a `string` with an index, cast to a backed enum in the
model, and validated through `Rule::in(SomeEnum::values())` in the form requests.

**Contact details are gated in two layers.** `contact_sharing_preferences` decides
who *may* see a phone/email/WhatsApp number (`hidden` / `connections` /
`everyone`), and list surfaces drop the contact block entirely regardless. So
`/nearby`, event attendee lists and search never carry contact details even for a
user who shares them with everyone — they are only revealed on a deliberate
profile view (`GET /api/users/{id}` or a QR scan). Blocking is symmetric: a block
in either direction hides both parties from each other's discovery, search,
profile view and connect endpoints.

**QR codes resolve through opaque tokens.** `qr_tokens.token` is a 40-character
random string, so a printed or shared code never exposes an enumerable user id,
and `POST /api/qr/rotate` invalidates a code that has leaked.

**Event networking is its own switch.** `event_attendees.is_networking_enabled`
is the per-event discoverability flag and is what gates
`GET /events/{id}/attendees` and `/networking`. It is intentionally independent of
the global `profiles.is_discoverable` flag: being invisible on the city-wide
proximity feed should not stop you being open to meeting people in the room.

**Networking sessions are opened by the discoverability toggle.** Turning
discoverability on opens a `networking_sessions` row and turning it off closes it,
with `discovered_count` and `connections_count` accumulating along the way. That
is what backs a recap like *"Tech Summit 2026: 23 people discovered, 8 connections
made."*

**Distance maths.** `Profile::distanceExpression()` emits haversine in raw SQL
using the `asin`/`sqrt` formulation rather than the more common `acos` one. The
argument to `sqrt` is naturally bounded to `[0,1]`, so two users at identical
coordinates cannot push the input out of domain and yield `NaN`. Radius bounds are
inlined as numeric literals rather than bound parameters — PDO sends floats to
SQLite as strings, and SQLite's affinity rules then compare `REAL` against `TEXT`,
where every number sorts before every string, silently matching every row.

---

## Push notifications (FCM)

Every `MingleNotification` subclass writes to the `notifications` table (so the
in-app bell always works) and, in the same call, hands off to
`App\Contracts\PushNotifier`. That interface has two implementations:

- **`NullPushNotifier`** — the default. Logs the intent at debug level and
  returns `false`. Always bound unless push is fully configured (below), so a
  half-set-up environment can never throw mid-request over a push failure.
- **`FcmPushNotifier`** — real delivery via FCM's **HTTP v1 API** (not the
  deprecated legacy server-key API), authenticated as a Firebase service
  account rather than a static key.

`AppServiceProvider` binds `FcmPushNotifier` only when **all** of the
following are true; anything short of that falls back to the null driver:

1. `FCM_ENABLED=true`
2. `FCM_PROJECT_ID` is set
3. Either `FCM_CREDENTIALS_JSON` (the service account JSON inline — handy
   where there's no persistent disk) or `FCM_CREDENTIALS_PATH` (a path to the
   JSON file, default `storage/app/firebase-service-account.json`) resolves to
   real credentials. `credentials_json` wins if both are set.

### Turning it on

1. In the Firebase console: Project settings → Service accounts → Generate
   new private key. That downloads the JSON file.
2. Either drop it at `storage/app/firebase-service-account.json`, or set
   `FCM_CREDENTIALS_JSON` to its contents, and set `FCM_PROJECT_ID` to the
   Firebase project id and `FCM_ENABLED=true`.
3. The mobile client registers its token via `POST /api/devices
   {token, platform}` after login (and ideally on token-refresh), and should
   call `POST /api/devices/unregister {token}` on logout so a shared device
   doesn't keep receiving another account's pushes.
4. `GoogleAccessTokenProvider` mints and caches an OAuth2 access token from
   the service account (cached ~50 minutes; real tokens last 60) and
   `FcmPushNotifier` posts to
   `https://fcm.googleapis.com/v1/projects/{project}/messages:send` once per
   registered device. A token FCM reports as `UNREGISTERED` is deleted from
   `device_tokens`; any other failure is logged and skipped without failing
   the request that triggered the notification.

### Scheduled notifications

Two commands, registered in `routes/console.php`, need a real scheduler tick
to fire — `php artisan schedule:work` in development, or a single
`* * * * * php artisan schedule:run` cron entry in production:

- **`events:send-reminders`** (hourly) — notifies attendees with
  `is_networking_enabled = true` for events starting in the next ~23–25
  hours. `events.reminder_sent_at` makes each event eligible for exactly one
  reminder regardless of how many times the hourly tick lands inside that
  window.
- **`networking:send-suggestions`** (daily at 09:00) — for each discoverable
  user, finds the nearest other discoverable person inside their own
  discovery radius who shares a skill or interest and isn't already
  connected, pending, or blocked, and sends one `NetworkingSuggestion`. A
  7-day dedupe window (checked against the `notifications` table) keeps the
  same pair from being re-suggested on every run. This is one nearby query
  per discoverable user — fine at MVP scale, and the first thing to batch
  (e.g. by geohash) if the discoverable population gets very large.

Testing either path needs neither a live scheduler nor real Google
credentials: `FcmPushNotifierTest` fakes the HTTP client and stubs
`GoogleAccessTokenProvider`, and `ScheduledNotificationsTest` invokes the
commands directly via `$this->artisan(...)`.

---

## Not implemented in this pass

These are deliberate scope boundaries, not oversights. Each one is stubbed behind
config or an interface so it can be filled in without touching call sites.

**Payment gateway integration (Stripe / Paystack).** `plans`, `subscriptions` and
`payments` are modelled and seeded, and `App\Contracts\PaymentGateway` defines the
seam. Only `ManualPaymentGateway` exists: it records payments locally and never
contacts a provider. There are no checkout, webhook or refund endpoints, and
`users.is_premium` is not yet driven by subscription state.

**BLE proximity discovery.** Nearby discovery currently uses client-reported
lat/lng plus a haversine radius query. This is intentional for this pass. Real
Bluetooth Low Energy ranging needs native iOS/Android code (`CoreBluetooth` /
Android BLE APIs) running in the mobile client — a backend cannot perform it, and
the API's role would be limited to resolving advertised ephemeral IDs to profiles.
The lat/lng approximation is also the more privacy-conscious starting point: the
server stores one coarse coordinate pair per user rather than a continuous stream
of encounter events, users can turn it off with a single flag, and distance can be
hidden from others via `distance_visibility`. When BLE lands, the natural shape is
for the client to advertise a rotating ID resolved through a table much like
`qr_tokens`, with `/api/nearby` remaining the fallback where BLE is unavailable or
denied.

**Object storage (S3) wiring.** Uploads go through `Storage::disk(config('mingle.uploads.disk'))`,
which defaults to the local `public` disk. Switching to S3 is a matter of
configuring the `s3` disk and setting `MINGLE_UPLOAD_DISK=s3`; no credentials,
CDN configuration or signed-URL handling are set up here.

**Also out of scope:** email verification and password reset flows, social login
exchange for Google/Apple (the `auth_provider` column exists but no token
exchange endpoint does), rate limiting beyond Laravel's defaults, real-time
messaging between connections, and event invitations as an endpoint (the
`EventInvitation` notification class exists but nothing dispatches it yet —
unlike `EventReminder` and `NetworkingSuggestion`, which are now on the
scheduler; see *Push notifications* above).
