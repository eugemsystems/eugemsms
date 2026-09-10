<?php

declare(strict_types=1);

namespace Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Fiscal\Database\Factories\FiscalDeviceFactory;

/**
 * Book H3 FIN-13 §3.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $device_id
 * @property string $device_serial
 * @property string|null $device_branch_id
 * @property string $taxpayer_name
 * @property string $taxpayer_tin
 * @property string|null $vat_number
 * @property Carbon|null $csr_generated_at
 * @property string|null $certificate_pem
 * @property string|null $private_key_ref
 * @property Carbon|null $certificate_issued_at
 * @property Carbon|null $certificate_expires_at
 * @property string $environment
 * @property string $api_base_url
 * @property string|null $operating_mode
 * @property int|null $taxpayer_day_max_hours
 * @property array<int, string>|null $applicable_taxes
 * @property Carbon|null $last_config_sync_at
 * @property Carbon|null $last_ping_at
 * @property string|null $last_ping_status
 * @property string $status
 * @property bool $is_active
 */
class FiscalDevice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FiscalDeviceFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'device_id', 'device_serial', 'device_branch_id', 'taxpayer_name', 'taxpayer_tin',
        'vat_number', 'csr_generated_at', 'certificate_pem', 'private_key_ref', 'certificate_issued_at',
        'certificate_expires_at', 'environment', 'api_base_url', 'operating_mode', 'taxpayer_day_max_hours',
        'applicable_taxes', 'last_config_sync_at', 'last_ping_at', 'last_ping_status', 'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'taxpayer_tin' => 'encrypted',
            'vat_number' => 'encrypted',
            'certificate_pem' => 'encrypted',
            'csr_generated_at' => 'datetime',
            'certificate_issued_at' => 'datetime',
            'certificate_expires_at' => 'datetime',
            'applicable_taxes' => 'array',
            'last_config_sync_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FiscalDeviceFactory::new();
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
