<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vm extends Model
{
    protected $fillable = [
        'vmid',
        'name',
        'node',
        'status',
        'config',
        'upid',
        'user_id',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    //
}
