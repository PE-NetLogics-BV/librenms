<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillMplsSap extends BillRelatedModel
{
    protected $table = 'bill_mpls_saps';
    public $timestamps = false;
    protected $fillable = [
        'bill_id',
        'sap_id',
    ];

    /**
     * @return BelongsTo<MplsSap, $this>
     */
    public function sap(): BelongsTo
    {
        return $this->belongsTo(MplsSap::class, 'sap_id', 'sap_id');
    }
}
