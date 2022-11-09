<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogExportNav extends Model
{

    public $incrementing = true;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'export_id',
        'message',
        'quantity',
        'total',
        'created_at',
    ];

}
