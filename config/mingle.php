<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Uploads
    |---------------------------------------------------------------------------
    | Disk used for avatars and event banners. Local/public by default; point it
    | at an "s3" disk in production. No object storage credentials are wired in
    | this pass -- see the README.
    */
    'uploads' => [
        'disk' => env('MINGLE_UPLOAD_DISK', 'public'),
        'avatar_path' => 'avatars',
        'event_banner_path' => 'event-banners',
        'max_kilobytes' => (int) env('MINGLE_UPLOAD_MAX_KB', 5120),
    ],

    /*
    |---------------------------------------------------------------------------
    | Discovery
    |---------------------------------------------------------------------------
    | Nearby discovery is a lat/lng + haversine approximation of BLE proximity.
    | See the README for why BLE itself is out of scope for this pass.
    */
    'discovery' => [
        'default_radius_meters' => (int) env('MINGLE_DEFAULT_DISCOVERY_RADIUS', 500),
        'max_radius_meters' => (int) env('MINGLE_MAX_DISCOVERY_RADIUS', 50000),
        'page_size' => (int) env('MINGLE_DISCOVERY_PAGE_SIZE', 25),
    ],

    /*
    |---------------------------------------------------------------------------
    | QR codes
    |---------------------------------------------------------------------------
    */
    'qr' => [
        'deep_link_base' => env('MINGLE_QR_DEEP_LINK_BASE', 'mingle://connect'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Push notifications
    |---------------------------------------------------------------------------
    | Delivery is stubbed behind App\Contracts\PushNotifier. Enabling this without
    | a real driver binding is a no-op by design.
    */
    'push' => [
        'enabled' => env('FCM_ENABLED', false),
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Payments
    |---------------------------------------------------------------------------
    | Gateway integration is stubbed behind App\Contracts\PaymentGateway.
    */
    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'manual'),
        'currency' => env('PAYMENTS_CURRENCY', 'usd'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Pagination
    |---------------------------------------------------------------------------
    */
    'pagination' => [
        'default' => 20,
        'max' => 100,
    ],

];
