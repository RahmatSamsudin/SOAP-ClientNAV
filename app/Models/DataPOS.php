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
        return $this->belongsTo(Store::class, 'store_id', 'store_id');
    }

    public static function transaction($store, $date, $exclude_location)
    {
        $excludes = ItemExcluded::select('exc_item_code')->where('exc_item_loc', $exclude_location)->get();

        return self::where('store_id', $store)
            ->where('sales_date', $date)
            ->where(function ($query) use ($excludes) {
                return $query->whereNotIn('item_code', $excludes)
                    // Exclude non inventory item TS
                    ->where('item_code', 'NOT LIKE', '0301-120-%')
                    //Exclude non inventory item ST
                    ->where('item_code', 'NOT LIKE', '9990%')
                    ->where('item_code', 'NOT LIKE', '1ST%')
                    //Exclude non inventory item CAFE
                    ->where('item_code', 'NOT LIKE', '1CF%')
                    // Exclude non inventory item HY
                    ->where('item_code', 'NOT LIKE', '0501-200-%');
            })

            ->orderBy('item_code', 'ASC');
    }
    public static function monthly($store, $date, $exclude_location)
    {
        $excludes = ItemExcluded::select('exc_item_code')->where('exc_item_loc', $exclude_location)->get();
        $start_date = Carbon::parse($date)->startOfMonth();
        $end_date = Carbon::parse($date)->endOfMonth();
        return self::where('store_id', $store)
            ->whereBetween('sales_date', $start_date, $end_date)
            ->where(function ($query) use ($excludes) {
                return $query->whereNotIn('item_code', $excludes)
                    // Exclude non inventory item TS
                    ->where('item_code', 'NOT LIKE', '0301-120-%')
                    //Exclude non inventory item ST
                    ->where('item_code', 'NOT LIKE', '9990%')
                    ->where('item_code', 'NOT LIKE', '1ST%')
                    //Exclude non inventory item CAFE
                    ->where('item_code', 'NOT LIKE', '1CF%')
                    // Exclude non inventory item HY
                    ->where('item_code', 'NOT LIKE', '0501-200-%');
                    
            })

            ->orderBy('item_code', 'ASC');
    }

    public function getTotal()
    {
        return $this->sum(function ($detail) {
            return $detail->sales_qty * $detail->sales_price;
        });
    }
}
