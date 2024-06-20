<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;;

use App\Models\DataTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ColorPlateController extends Controller
{
    public function get(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
            ]);

            $startDate = Carbon::parse($validatedData['start_date']);
            $endDate = Carbon::parse($validatedData['end_date']);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        }

        return response()->json(DataTransaction::getRanged($startDate, $endDate));
    }

}
