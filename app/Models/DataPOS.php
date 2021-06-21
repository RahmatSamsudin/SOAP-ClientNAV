<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ItemExcluded;

class DataPOS extends Model
{
    protected $table = 'pos_data';
    protected $primaryKey = 'pos_data_id';
    public $incrementing = true;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'store',
        'sales_doc',
        'sales_type_id',
        'sales_data',
        'store_id',
        'sales_qty',
        'sales_price',
        'item_code',
        'item_name'
    ];

    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }

    public static function transaction($store, $date, $exclude_location)
    {
        return self::where('sales_date', $date)
            ->where('store_id', $store)
            ->whereNotIn('item_code', ItemExcluded::select('exc_item_code')->where('exc_item_loc', $exclude_location)
            ->orderBy('item_code', 'ASC')
        );
    }

}
