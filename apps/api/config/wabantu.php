<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment edition
    |--------------------------------------------------------------------------
    | standalone = single licensed intermediary, no SaaS billing/platform admin
    | saas       = multi-tenant control plane enabled
    */
    'edition' => env('APP_EDITION', 'standalone'),

    'default_timezone' => env('APP_TIMEZONE', 'Africa/Dar_es_Salaam'),
    'default_currency' => env('APP_CURRENCY', 'TZS'),
    'default_locale' => env('APP_LOCALE', 'en-TZ'),

    /*
    | Exceptional agent premium collection is OFF by default (Insurance Act s.72).
    | Enabling requires documented legal/compliance approval.
    */
    'allow_agent_premium_collection' => (bool) env('ALLOW_AGENT_PREMIUM_COLLECTION', false),

    'broker_retention_years_minimum' => (int) env('BROKER_RETENTION_YEARS_MINIMUM', 6),

    /*
    | Customer portal registration attaches new accounts to this organization
    | when PORTAL_REGISTRATION_ORG_ID is unset (first org is used as fallback).
    */
    'portal_registration_org_id' => env('PORTAL_REGISTRATION_ORG_ID'),

    'portal_otp_enabled' => (bool) env('PORTAL_OTP_ENABLED', true),
];
