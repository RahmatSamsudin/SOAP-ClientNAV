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

    public static function transaction($sales_doc, $exclude_location)
    {
        $excludes = ItemExcluded::select('exc_item_code')->where('exc_item_loc', $exclude_location)->get();

        return self::where('sales_doc', $sales_doc)
            ->where(function ($query) use ($excludes) {
                return $query->whereNotIn('item_code', $excludes)
                    ->where('item_code', 'NOT LIKE', '0301-120-%')
                    ->where('item_code', 'NOT LIKE', '999990%');
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
