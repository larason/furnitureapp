<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private Attachment Disk
    |--------------------------------------------------------------------------
    |
    | Request/Enquiry attachments are private customer data. The disk name is
    | resolved here (never hard-coded in services) and must reference a
    | non-public disk. Tests use `Storage::fake()`.
    |
    */

    'disk' => env('ATTACHMENTS_DISK', 'attachments'),

    /*
    |--------------------------------------------------------------------------
    | V1 Attachment Limits (frozen contract)
    |--------------------------------------------------------------------------
    */

    'max_bytes' => 5 * 1024 * 1024,

    /** @var array<string, string> detected content type => canonical extension */
    'allowed_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ],

    'max_filename_length' => 255,

    /*
    |--------------------------------------------------------------------------
    | Private Delivery Link Lifetime
    |--------------------------------------------------------------------------
    |
    | Lifetime of the temporary signed URL a request attachment is delivered
    | through (REQ-008). The signature is the credential: the link expires and
    | is never refreshed or cached.
    |
    */

    'download_url_ttl_minutes' => (int) env('ATTACHMENTS_DOWNLOAD_URL_TTL_MINUTES', 15),

];
