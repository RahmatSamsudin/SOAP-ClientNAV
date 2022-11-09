<?php

namespace App\Http\Controllers;

use App\Models\DataPOS;


class TestController extends Controller
{
    public function index()
    {
        return DataPOS::transaction('20220824TSPRG', '12')->get();
    }
}