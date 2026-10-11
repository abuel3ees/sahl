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

    /*
     * Microsoft Entra ID (workforce) for ministry staff sign-in.
     * See docs/setup/01-entra-id-staff-sign-in.md for how these values are created.
     */
    'entra' => [
        'client_id' => env('ENTRA_CLIENT_ID'),
        'client_secret' => env('ENTRA_CLIENT_SECRET'),
        'redirect' => env('ENTRA_REDIRECT_URI'),
        'tenant' => env('ENTRA_TENANT_ID'),
    ],

    /*
     * Microsoft Entra External ID (external tenant) for citizen sign-in.
     * See docs/setup/02-entra-external-id-citizen-sign-in.md for how these values are created.
     */
    'entra_citizen' => [
        'client_id' => env('ENTRA_CITIZEN_CLIENT_ID'),
        'client_secret' => env('ENTRA_CITIZEN_CLIENT_SECRET'),
        'redirect' => env('ENTRA_CITIZEN_REDIRECT_URI'),
        'tenant' => env('ENTRA_CITIZEN_TENANT_ID'),
        'domain' => env('ENTRA_CITIZEN_DOMAIN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
