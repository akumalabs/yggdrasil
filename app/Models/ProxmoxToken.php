<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProxmoxToken extends Model
{
    protected $fillable = [
        'name',
        'host',
        'token_id',
        'token_secret',
    ];

    protected $casts = [
        'token_secret' => 'encrypted',
    ];
}
