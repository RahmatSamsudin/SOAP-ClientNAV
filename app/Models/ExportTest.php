<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ItemExcluded;

class ExportTest extends Model
{
    protected $table = 'export_lines_copy1';
    public $incrementing = true;
    protected $fillable = [
        'export_id',
        'store_id',
        'busidate',
        'extdocno',
        'salestype',
        'itemno',
        'desc',
        'qty',
        'unitprice',
        'totalprice',
        'loccode',
        'is_cps'
    ];

    public function Store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'store_id');
    }

    public function ExportNAV()
    {
        return $this->belongsTo(ExportNAV::class, 'export_id', 'export_id');
    }

    public static function getLine($export_id, $exclude_location)
    {
        #$excludes = ItemExcluded::select('exc_item_code')->where('exc_item_loc', $exclude_location)->get();

        return self::select('id as postdocumentid', 'extdocno', 'loccode', 'salestype', 'itemno', 'qty', 'unitprice', 'totalprice', 'desc')
            ->where('export_id', $export_id)
            ->where('itemno', '!=', '')
            ->where('desc', '!=', '')
            ->orderBy('itemno', 'ASC');

    }

}
