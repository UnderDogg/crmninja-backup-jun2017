<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, Mandrill, and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'postmark' => env('POSTMARK_API_TOKEN', ''),

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN', ''),
        'secret' => env('MAILGUN_SECRET', ''),
    ],

    'mandrill' => [
        'secret' => '',
    ],

    'ses' => [
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
    ],

    'stripe' => [
        'model' => 'User',
        'secret' => '',
    ],

    'github' => [
        'relation_id' => env('GITHUB_RELATION_ID'),
        'relation_secret' => env('GITHUB_RELATION_SECRET'),
        'redirect' => env('GITHUB_OAUTH_REDIRECT'),
    ],

    'google' => [
        'relation_id' => env('GOOGLE_RELATION_ID'),
        'relation_secret' => env('GOOGLE_RELATION_SECRET'),
        'redirect' => env('GOOGLE_OAUTH_REDIRECT'),
    ],

    'facebook' => [
        'relation_id' => env('FACEBOOK_RELATION_ID'),
        'relation_secret' => env('FACEBOOK_RELATION_SECRET'),
        'redirect' => env('FACEBOOK_OAUTH_REDIRECT'),
    ],

    'linkedin' => [
        'relation_id' => env('LINKEDIN_RELATION_ID'),
        'relation_secret' => env('LINKEDIN_RELATION_SECRET'),
        'redirect' => env('LINKEDIN_OAUTH_REDIRECT'),
    ],

];
