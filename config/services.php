<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'recaptcha' => [
        'secretkey' => env('CAPTCHA_SECRET_KEY'),
        'sitekey' => env('CAPTCHA_SITE_KEY'),
        'enabled' => env('CAPTCHA_ENABLED'),
    ],

    'sysmailing' => [
        'mailonlogin' => env('MAIL_ON_LOGIN'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'templates' => [
            'notification' => env('WHATSAPP_TEMPLATE_NOTIFICATION', 'task_notification'),
            'reminder' => env('WHATSAPP_TEMPLATE_REMINDER', 'task_reminder'),
            'status_update' => env('WHATSAPP_TEMPLATE_STATUS_UPDATE', 'task_status_update'),
            'loop' => env('WHATSAPP_TEMPLATE_LOOP', 'task_loop_notification'),
            'activity_update' => env('WHATSAPP_TEMPLATE_ACTIVITY_UPDATE', 'task_activity_update'),
            'job_completed' => env('WHATSAPP_TEMPLATE_JOB_COMPLETED', 'job_completed'),
        ],
    ],
];
