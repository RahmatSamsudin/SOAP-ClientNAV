<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesHeader extends Model
{
    use HasFactory;

    protected $table = 'sales_headers';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'extdocno',
        'custno',
        'orderdate',
        'sync',
    ];

    public function salesLines()
    {
        return $this->hasMany(SalesLine::class, 'document_no', 'extdocno');
    }
}
