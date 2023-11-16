<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ExportTestNAV extends Model
{
    use HasFactory;

    protected $table = 'export_nav_copy1';
    protected $primaryKey = 'export_id';
    public $incrementing = true;
    public $timestamps = 'last_update';
    const UPDATED_AT = 'last_update';
    // protected $keyType = 'string';
    protected $fillable = [
        'store',
        'sales_date',
        'document_number',
        'export_status'
    ];

    public static function readyExport()
    {
        return self::where('export_status', 0)
            #->where('export_id', 795);
            ->where('export_nav', 1)
            #->whereDate('sales_date', '>', '2021-12-31')
            #->whereDate('sales_date', '<', '2022-05-02')
            ->whereDate('sales_date', '<', Carbon::now()->subDays(14))
            #->whereDate('last_update', '<', Carbon::now()->subDays(2))
            ->orderBy('sales_date', 'asc')
            ->orderBy('store', 'asc');
    }

    public function dataPOS()
    {
        return $this->hasMany(DataPOS::class, 'store', 'store_id');
    }

    public function stores()
    {
        return $this->belongsTo(Store::class, 'store', 'store_id');
    }

}
