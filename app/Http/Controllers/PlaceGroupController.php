<?php

namespace App\Http\Controllers;

use App\Models\PlaceGroup;
use App\Http\Requests\StorePlaceGroupRequest;
use App\Http\Requests\UpdatePlaceGroupRequest;
use Illuminate\Http\Request;

class PlaceGroupController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function indexByCategory($category)
    {
        $groups = PlaceGroup::where('category', $category)->get();
        //Order by name
        $groups = $groups->sortBy('name');

        //Get the images
        foreach ($groups as $group) {
            //If has not "imgur" in the image path, add it
            if (!str_contains($group->image_vertical, 'imgur')) {
                $group->image_vertical = asset('images/' . $group->image_vertical);
            }
            if (!str_contains($group->image_horizontal, 'imgur')) {
                $group->image_horizontal = asset('images/' . $group->image_horizontal);
            }
        }

        return response()->json($groups);
    }

    public function index(){
        $groups = PlaceGroup::all();

        //Order by name
        $groups = $groups->sortBy('name');
        
        return view('location.placeGroup.index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('location.placeGroup.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->all();
        
        $imageNameV = time().'.'.$request->image_vertical->extension();
        $imageNameH = time().'.'.$request->image_horizontal->extension();

        $request->image_vertical->move(public_path('images'), $imageNameV);
        $request->image_horizontal->move(public_path('images'), $imageNameH);

        $validated['image_vertical'] = $imageNameV;
        $validated['image_horizontal'] = $imageNameH;

        PlaceGroup::create($validated);

        return redirect()->route('place-group.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(PlaceGroup $placeGroup)
    {
        $placeGroup->load('places.scheduleRules');

        $ruleToPlaces = [];

        // Itera sobre os lugares como objetos
        foreach ($placeGroup->places as $place) {
            $place->schedule_rules;
            $placeName = $place->name;

            foreach ($place->scheduleRules as $rule) {
                $ruleId = $rule->id;
                if (!isset($ruleToPlaces[$ruleId])) {
                    // Clona o objeto para evitar modificar o original
                    $ruleToPlaces[$ruleId] = [
                        'rule' => clone $rule,
                        'places' => []
                    ];
                    // Remove campo pivot se existir
                    unset($ruleToPlaces[$ruleId]['rule']->pivot);
                }
                $ruleToPlaces[$ruleId]['places'][] = $placeName;
            }
        }


        // Agrupa regras por conjunto de lugares
        $rules = [];

        foreach ($ruleToPlaces as $ruleData) {
            $places = $ruleData['places'];

            // Adiciona o campo "places" no objeto da regra
            $rule = $ruleData['rule'];
            $rule->places = $places;

            $rules[] = $rule;
        }



        //Delete schedule_rules from places
        foreach ($placeGroup->places as $place) {
            unset($place->scheduleRules);
        }

        #Order rules by length of places
        usort($rules, function ($a, $b) {
            return count($b->places) <=> count($a->places);
        });


        return view('location.placeGroup.show', [
            'item' => $placeGroup,
            'rules' => $rules,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PlaceGroup $placeGroup)
    {
        dd($placeGroup);
        return view('location.placeGroup.edit', [
            'group' => $placeGroup,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlaceGroupRequest $request, PlaceGroup $placeGroup)
    {
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

        return redirect()->route('place-group.index');
    }


    public function createSchedule($group_id)
    {
        $group = PlaceGroup::find($group_id);
        $group->places;
        return view('location.schedule.rule.create', [
            'group' => $group,
        ]);
    }

    
    public function storeSchedule(Request $request)
    {
        $validated = $request->all();

        //dd($validated);

        $validated['start_time'] = date('H:i:s', strtotime($validated['start_time']));
        $validated['end_time'] = date('H:i:s', strtotime($validated['end_time']));

        //dd($validated);

        PlaceGroup::find($validated['place_group_id'])->schedule()->create($validated);

        return redirect()->route('place-group.show', ['placeGroup' => $validated['group_id']]);
    }

    public function scheduleRules($id)
    {
        $placeGroup = PlaceGroup::where('id', $id)->first();
        $placeGroup->load('places.scheduleRules');


        return response()->json($rules);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlaceGroup $placeGroup)
    {
        //
    }
}
