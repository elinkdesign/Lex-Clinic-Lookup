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

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'searchTerm' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $searchTerm = $request->input('searchTerm');

        $shortlistResults = Shortlist::where('NID', 'LIKE', "%{$searchTerm}%")
            ->orWhere('LIC', 'LIKE', "%{$searchTerm}%")
            ->orWhere('name', 'LIKE', "%{$searchTerm}%")
            ->get();

        $longlistResults = Longlist::where('NID', 'LIKE', "%{$searchTerm}%")
            ->orWhere('LIC', 'LIKE', "%{$searchTerm}%")
            ->orWhere('name', 'LIKE', "%{$searchTerm}%")
            ->get();

        $results = $shortlistResults->concat($longlistResults);

        return response()->json(['results' => $results]);
    }
}
