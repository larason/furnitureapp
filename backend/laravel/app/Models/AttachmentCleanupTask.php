<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $available_at */
#[Fillable([
    'storage_disk',
    'storage_key',
    'attempts',
    'last_error',
])]
class AttachmentCleanupTask extends Model
{
    protected $table = 'attachment_cleanup_tasks';

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'datetime',
        ];
    }
}
