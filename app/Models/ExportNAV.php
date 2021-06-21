<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportNAV extends Model
{
    use HasFactory;

    protected $table = 'export_nav';
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
        return self::where('export_status', 0)->orderBy('store', 'asc')->orderBy('sales_date', 'asc')->get();
    }

    public function dataPOS()
    {
        return $this->hasMany(DataPOS::class, 'store_id', 'store');
    }

    public function stores()
    {
        return $this->hasOne(Store::class, 'store_id', 'store');
    }
}
