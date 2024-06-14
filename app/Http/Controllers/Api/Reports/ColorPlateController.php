<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;;

use App\Models\DataTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Exception;


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


    public function show($id)
    {
        return Article::find($id);
    }

    public function store(Request $request)
    {
        return Article::create($request->all());
    }

    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $article->update($request->all());

        return $article;
    }

    public function delete(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return 204;
    }
}
