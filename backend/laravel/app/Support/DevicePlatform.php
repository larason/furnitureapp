<?php

namespace App\Support;

/**
 * CLOSED Version 1 registered device platforms (docs/api/api-contract.md §31).
 *
 * Only platforms with a verified FCM transport are admitted. Adding a value is
 * an additive contract change under the post-freeze process.
 */
enum DevicePlatform: string
{
    case ANDROID = 'ANDROID';
}
