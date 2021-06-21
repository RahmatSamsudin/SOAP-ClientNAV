<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $table = 'store';
    protected $primaryKey = 'store_id';
    public $incrementing = true;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'store_name',
        'store_status',
        'location_id',
        'group_id',
        'pos_id',
        'pos_code',
        'nav_code',
        'need_cp',
        'time_category_id',
        'cp_kitchen'
    ];

    public function salesLines()
    {
        return $this->hasMany(DataPOS::class, 'store', 'sales_date');
    }
}
