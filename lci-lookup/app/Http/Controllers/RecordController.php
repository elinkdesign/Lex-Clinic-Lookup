<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shortlist;
use App\Models\Longlist;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class RecordController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'NID' => 'required|string|max:255',
            'LIC' => 'required|string|max:255',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Determine if it's a shortlist or longlist based on NID length
        if (strlen($request->NID) === 4 && ctype_digit($request->NID)) {
            $record = new Longlist();
        } else {
            $record = new Shortlist();
        }

        $record->NID = $request->NID;
        $record->LIC = $request->LIC;
        $record->name = $request->name;
        $record->save();

        return Inertia::render('Welcome', ['message' => 'Record saved successfully']);
    }
}
