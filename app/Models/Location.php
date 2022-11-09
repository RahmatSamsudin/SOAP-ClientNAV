<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{

    protected $table = 'location';
    protected $primaryKey = 'location_id';
    public $incrementing = true;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'location_name',
        'location_status',
        'location_code',
        'location_type'
    ];

    public function stores()
    {
        return $this->hasMany(Store::class, 'location_id', 'location_id');
    }

}
