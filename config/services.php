<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Third Party Services
     |--------------------------------------------------------------------------
     |
     | This file is for storing the credentials for third party services such
     | as Mailgun, Postmark, AWS and more. This file provides a de facto
     | location for this type of information, allowing packages to have
     | a conventional file to locate the various service credentials.
     |
     */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_NOTIFICATIONS_CHANNEL'),
        ],
    ],

    'wago' => [
        'base_url' => env('WAGO_BASE_URL', 'https://wago.tools'),
        'product' => env('WAGO_PRODUCT', 'wow'),
        'timeout' => (int) env('WAGO_TIMEOUT', 120),
    ],

    // Amont de la curation des collections, tiré à la main une fois par patch. Aucun import
    // ne passe par là : le chemin d'exécution lit l'instantané versionné du dépôt.
    'simplearmory' => [
        'base_url' => env('SIMPLEARMORY_BASE_URL', 'https://simplearmory.com/data'),
        'timeout' => (int) env('SIMPLEARMORY_TIMEOUT', 120),
    ],

    // L'export qui suit un arbitrage réécrit l'instantané du dépôt. En production ce fichier
    // vit dans l'image et ne peut pas être commité : il y reste la version commitée.
    'taxonomy' => [
        'export_after_arbitration' => (bool) env('TAXONOMY_EXPORT_AFTER_ARBITRATION', env('APP_ENV') !== 'production'),
    ],

    'blizzard' => [
        'client_id' => env('BLIZZARD_CLIENT_ID'),
        'client_secret' => env('BLIZZARD_CLIENT_SECRET'),
        'region' => env('BLIZZARD_REGION', 'eu'),
        'redirect_uri' => env('BLIZZARD_REDIRECT_URI'),
        'admin_bnet_id' => env('ADMIN_BNET_ID'),

        // Réimport : plafond horaire réservé aux imports (< quota réel 36000, laisse la
        // marge au trafic site), durée max d'une passe de job, et nombre de fenêtres de
        // recherche balayées ensemble — une fenêtre pesant environ 1,2 Mo, c'est elle
        // qui fixe le pic mémoire de l'import de la garde-robe.
        'import_hourly_ceiling' => (int) env('BLIZZARD_IMPORT_HOURLY_CEILING', 30000),
        'import_chunk_timebox' => (int) env('BLIZZARD_IMPORT_CHUNK_TIMEBOX', 600),
        'appearance_window_batch' => (int) env('BLIZZARD_APPEARANCE_WINDOW_BATCH', 5),

        // Requêtes en vol simultanées côté import. Le plafond réel reste celui de
        // RateLimitingMiddleware, 80 par seconde, marge délibérée sous les 100 de Blizzard.
        'import_concurrency' => (int) env('BLIZZARD_IMPORT_CONCURRENCY', 20),
    ],

    'discord' => [
        'webhook_changelog' => env('DISCORD_WEBHOOK_URL_CHANGELOG'),
        'webhook_discussion' => env('DISCORD_WEBHOOK_URL_DISCUSSION'),
    ],

];
