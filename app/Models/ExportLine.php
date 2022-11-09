<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ExportLine extends Model
{

    public $incrementing = true;
    protected $fillable = [
        'export_id',
        'store_id',
        'extdocno',
        'salestype',
        'itemno',
        'desc',
        'qty',
        'unitprice',
        'totalprice',
        'loccode',
    ];

    public function Store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'store_id');
    }

    public function ExportNAV()
    {
        return $this->belongsTo(ExportNAV::class, 'export_id', 'export_id');
    }

}
