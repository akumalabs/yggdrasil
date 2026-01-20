<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmBandwidthUsage extends Model
{
    protected $table = 'vm_bandwidth_usage';

    protected $fillable = [
        'vm_id',
        'date',
        'bytes_in',
        'bytes_out',
        'total_bytes',
    ];

    protected $casts = [
        'date' => 'date',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'total_bytes' => 'integer',
    ];

    public function vm(): BelongsTo
    {
        return $this->belongsTo(Vm::class);
    }
}
