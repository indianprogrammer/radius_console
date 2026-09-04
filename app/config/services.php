<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google Maps Platform — used by the subscriber Installation Address
    | picker (App\Http\Controllers\GeocodeController).
    |
    | Requires the "Geocoding API" and "Places API" to be enabled on the
    | project. The key is used SERVER-SIDE ONLY: it is never sent to the
    | browser, so it should be restricted by IP (not by HTTP referrer).
    |
    | When `key` is empty the controller falls back to OpenStreetMap's
    | Nominatim, so local/dev environments keep working without a key.
    */
    'google_maps' => [
        'key'      => env('GOOGLE_MAPS_API_KEY'),
        // Biases (not restricts) results toward one region: an ISP's
        // addresses are almost always in a single country.
        'region'   => env('GOOGLE_MAPS_REGION', 'in'),
        'language' => env('GOOGLE_MAPS_LANGUAGE', 'en'),
    ],

];
