<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesLine extends Model
{
    use HasFactory;
    protected $table = 'sales_lines';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    // protected $keyType = 'string';
    protected $fillable = [
        'document_no',
        'location_code',
        'sales_type',
        'item_no',
        'quantity',
        'price',
        'total_price',
        'description',
        'desc',
    ];
}
