<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DataTransaction extends Model
{
    protected $table = 'in_out_trx';
    protected $primaryKey = 'in_out_trx_id';
    public $incrementing = true;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'trx_date',
        'store_id',
        'inorout',
        'time_id',
        'qty',
        'price',
        'user_name',
        'date_modified',
        'pos_skip'
    ];

    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }

    public static function daily($store, $date)
    {
        return DB::select(DB::raw("
        SELECT
            (CASE WHEN in_out_trx.inorout = 1 THEN 3 ELSE 4 END) AS col2,
            DATE_FORMAT(in_out_trx.trx_date, '%m/%d/%Y') AS dateformated,
            colour_item.item_code,
            SUM(CASE WHEN in_out_trx.inorout = 1 AND outtrx.outqty IS NOT NULL THEN in_out_trx.qty-outtrx.outqty ELSE in_out_trx.qty END) AS sumqty,
            in_out_trx.price,
            colour_item.item_name,
            SUM(in_out_trx.qty*in_out_trx.price) AS sumqtyprice
        FROM colour_item
        JOIN store ON store.location_id=colour_item.location AND store.store_id='{$store}'
        LEFT JOIN in_out_trx ON in_out_trx.item_id=colour_item.item_id
            AND in_out_trx.trx_date = '{$date}'
            AND in_out_trx.store_id = store.store_id
        LEFT JOIN
            (
                SELECT trx_date, time_id, item_id, SUM(qty) AS outqty
                FROM in_out_trx
                WHERE trx_date = '{$date}'
                AND store_id= '{$store}'
                AND inorout=2
                GROUP BY trx_date, store_id, time_id, item_id
            ) AS outtrx ON outtrx.item_id=in_out_trx.item_id
            AND outtrx.trx_date=in_out_trx.trx_date
            AND outtrx.time_id=in_out_trx.time_id
        WHERE in_out_trx.qty IS NOT NULL
        GROUP BY in_out_trx.trx_date, colour_item.item_id, colour_item.item_code,  in_out_trx.inorout
        ORDER BY in_out_trx.trx_date, colour_item.item_code, in_out_trx.inorout LIMIT 0,10"));

    }

}
