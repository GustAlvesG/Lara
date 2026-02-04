<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Http\Requests\StoreplaceRequest;
use App\Http\Requests\UpdateplaceRequest;
use App\Models\PlaceGroup;
use App\Services\PlaceService;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function __construct(protected PlaceService $placeService)
    {

    }
    /**
     * Display a listing of the resource.
     */
    public function indexByGroup(Request $request)
    {
        $group_id = $request->input('group_id');
        $member_id = $request->input('member_id');
        $date = $request->input('date');

        try{
            return $this->placeService->getPlacesByGroup($group_id, $member_id, $date);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching places: ' . $e->getMessage()
            ], 500);
        }
    }

    public function scheduleRules($id)
    {
        $place = Place::where('id', $id)->first();
        
        if ($place->scheduleRules == null) {
            return response()->json([
                'message' => 'No schedule rules found for this place'
            ], 404);
        }
        return response()->json([
            'place' => $place
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreplaceRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        
        $place = Place::where('id', $id)->first();

        $place->load('scheduleRules');

        return $place;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(place $place)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateplaceRequest $request, place $place)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(place $place)
    {
        //
    }
}
