<?php

namespace App\Models;

use App\Support\DevicePlatform;
use App\Support\DeviceRegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer app installation registered for push delivery.
 *
 * @property int $id
 * @property int $user_id
 * @property DevicePlatform $platform
 * @property string $app_instance_id
 * @property string $token_ciphertext
 * @property string $token_fingerprint
 * @property DeviceRegistrationStatus $status
 * @property string|null $app_version
 * @property string|null $app_build
 * @property Carbon|null $last_registered_at
 * @property Carbon|null $deactivated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'user_id',
    'platform',
    'app_instance_id',
    'token_ciphertext',
    'token_fingerprint',
    'status',
    'app_version',
    'app_build',
    'last_registered_at',
    'deactivated_at',
])]
class DeviceInstallation extends Model
{
    use HasFactory;

    public const TABLE = 'device_installations';

    public const MAX_APP_INSTANCE_ID = 128;

    public const MAX_APP_VERSION = 64;

    public const MAX_APP_BUILD = 64;

    protected $table = self::TABLE;

    /**
     * The encrypted token and its fingerprint are storage details. They are
     * never serializable, so no resource or audit log can leak them.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_ciphertext',
        'token_fingerprint',
    ];

    protected $casts = [
        'platform' => DevicePlatform::class,
        'status' => DeviceRegistrationStatus::class,
        'last_registered_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === DeviceRegistrationStatus::ACTIVE;
    }
}
