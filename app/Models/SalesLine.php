<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesLine extends Model
{
    use HasFactory;
    protected $table = 'sales_lines';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'extdocno',
        'loccode',
        'salestype',
        'itemno',
        'qty',
        'unitprice',
        'totalprice',
        'postdocumentid',
        'desc',
    ];
}
