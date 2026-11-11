<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LMS License Enforcement Feature Flag
    |--------------------------------------------------------------------------
    |
    | When set to false (default), license expiration is tracked for reporting
    | and email reminders, but does not block school tenant operations.
    | When enabled (true), expired school tenants are politely suspended until
    | renewed by a platform superuser.
    |
    */
    'enforcement_enabled' => (bool) env('LICENSE_ENFORCEMENT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Automated License Reminders Feature Flag
    |--------------------------------------------------------------------------
    |
    | When set to false (default safe rollout), reminder checks and simulations
    | (--dry-run) operate normally, but real emails are not dispatched.
    | Set to true once mail service and recipient lists are verified.
    |
    */
    'reminders_enabled' => (bool) env('LICENSE_REMINDERS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Automated Reminder Threshold
    |--------------------------------------------------------------------------
    |
    | Days remaining before term expiration date to trigger automated email
    | reminders to school administrators.
    |
    */
    'reminder_days' => (int) env('LICENSE_REMINDER_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Partnership & Helpdesk Support Contact
    |--------------------------------------------------------------------------
    |
    | Contact information displayed on expiration notice screens and reminder
    | emails for school administrators to request license renewals.
    | If not configured, safe generic institutional instructions are shown.
    |
    */
    'support_contact' => [
        'email' => env('LICENSE_SUPPORT_EMAIL', null),
        'phone' => env('LICENSE_SUPPORT_PHONE', null),
        'helpdesk_url' => env('LICENSE_SUPPORT_URL', null),
    ],
];
