<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemExcluded extends Model
{

    protected $table = 'item_excluded';
    protected $primaryKey = 'exc_id';
    public $incrementing = true;
    public $timestamps = 'exc_date';
    // protected $keyType = 'string';
    protected $fillable = [
        'exc_item_code',
        'exc_item_name',
        'exc_item_status',
        'exc_date',
        'exc_item_location',
    ];
}
