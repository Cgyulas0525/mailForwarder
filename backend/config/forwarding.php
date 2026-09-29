<?php

return [

    /*
    | A napközbeni ellenőrzés időablaka. Üres érték = nincs szűkített ablak
    | (a teljes nap). Az admin felület felülírhatja az app_settings táblában.
    | Szándékosan nincs rögzített üzleti idő.
    */
    'window_start' => env('MAIL_CHECK_START'),
    'window_end' => env('MAIL_CHECK_END'),

    'sync_batch_size' => (int) env('MAIL_SYNC_BATCH_SIZE', 40),
    'sync_max_per_run' => (int) env('MAIL_SYNC_MAX_PER_RUN', 200),
    'sync_overlap' => (int) env('MAIL_SYNC_OVERLAP', 25),

    'max_attachment_bytes' => (int) env('FORWARD_MAX_ATTACHMENT_BYTES', 10 * 1024 * 1024),
    'message_retention_days' => (int) env('MESSAGE_RETENTION_DAYS', 90),

    /*
    | Fejlesztésben minden kimenő levél a Mailpitre megy.
    | Éles SMTP csak akkor, ha ez kifejezetten true.
    */
    'live_smtp' => (bool) env('FORWARD_LIVE_SMTP', false),
    'capture_dsn' => env('FORWARD_CAPTURE_DSN', 'smtp://mailpit:1025'),

    'legacy_connection' => 'legacy_gmail_eval',
    'legacy_app_key' => env('LEGACY_APP_KEY'),
    'test_connections_after_import' => (bool) env('LEGACY_IMPORT_TEST_CONNECTIONS', true),

];
