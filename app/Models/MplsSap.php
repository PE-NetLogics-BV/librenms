<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LibreNMS\Interfaces\Models\Keyable;
use LibreNMS\OS\Timos;

/**
 * @property-read string $encap_display
 */
class MplsSap extends DeviceRelatedModel implements Keyable
{
    use HasFactory;
    protected $primaryKey = 'sap_id';
    public $timestamps = false;
    protected $fillable = [
        'svc_id',
        'svc_oid',
        'sapPortId',
        'ifName',
        'sapEncapValue',
        'device_id',
        'sapRowStatus',
        'sapType',
        'sapDescription',
        'sapAdminStatus',
        'sapOperStatus',
        'sapLastMgmtChange',
        'sapLastStatusChange',
    ];

    // ---- Helper Functions ----

    /**
     * Get a string that can identify a unique instance of this model
     */
    public function getCompositeKey(): string
    {
        return $this->svc_oid . '-' . $this->sapPortId . '-' . $this->sapEncapValue;
    }

    // ---- Accessors/Mutators ----

    /**
     * Human readable outer.inner encapsulation, the stored value is the raw TmnxEncapVal
     * (or '*' for a dot1q wildcard SAP)
     */
    protected function encapDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => is_numeric($this->sapEncapValue) ? Timos::decodeEncapVal((int) $this->sapEncapValue) : (string) $this->sapEncapValue,
        );
    }

    // ---- Define Relationships ----
    /**
     * @return HasMany<MplsSdpBind, $this>
     */
    public function binds(): HasMany
    {
        return $this->hasMany(MplsSdpBind::class, 'svc_id');
    }

    /**
     * @return BelongsTo<MplsService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(MplsService::class, 'svc_id');
    }

    /**
     * @return BelongsToMany<Bill, $this>
     */
    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_mpls_saps', 'sap_id', 'bill_id');
    }
}
