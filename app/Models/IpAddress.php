<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpAddress extends Model
{
    protected $fillable = [
        'ip',
        'gateway',
        'netmask',
        'is_reserved',
        'vm_id',
        'label',
    ];

    public function vm()
    {
        return $this->belongsTo(Vm::class);
    }

    public function scopeFree($query)
    {
        return $query->whereNull('vm_id')->where('is_reserved', false);
    }
}
