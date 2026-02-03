<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PlaceGroup;


class PlaceGroupService
{
    // Service methods would go here

    public function store(Request $request){
        DB::beginTransaction();
        try {
            // Logic to store a PlaceGroup
            $placeGroup = PlaceGroup::create($request->all());

            foreach ($request->weekdays as $weekdayId) {
                $placeGroup->weekdays()->attach($weekdayId);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show($placeGroup){
        // Logic to show a PlaceGroup
        return $placeGroup;
    }

    public function update(Request $request, $placeGroup){
        DB::beginTransaction();
        try {
           $validated = $request->all();

            //dd($validated);

            if ($request->hasFile('image_vertical')) {
                $imageNameV = time().'.'.$request->image_vertical->extension();
                $request->image_vertical->move(public_path('images'), $imageNameV);
                $validated['image_vertical'] = $imageNameV;
            }

            if ($request->hasFile('image_horizontal')) {
                $imageNameH = time().'.'.$request->image_horizontal->extension();
                $request->image_horizontal->move(public_path('images'), $imageNameH);
                $validated['image_horizontal'] = $imageNameH;
            }

            //dd($validated);

            PlaceGroup::find($placeGroup->id)->update($validated);

            //Sync weekdays
            $placeGroup->weekdays()->sync($validated['weekdays'] ?? []);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}