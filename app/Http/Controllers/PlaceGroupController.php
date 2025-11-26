<?php

namespace App\Http\Controllers;

use App\Models\PlaceGroup;
use App\Http\Requests\StorePlaceGroupRequest;
use App\Http\Requests\UpdatePlaceGroupRequest;
use Illuminate\Http\Request;
use App\Models\Place;
use App\Models\ScheduleRules;

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
            if (!str_contains($group->image_vertical, 'http')) {
                $group->image_vertical = asset('images/' . $group->image_vertical);
            }
            if (!str_contains($group->image_horizontal, 'http')) {
                $group->image_horizontal = asset('images/' . $group->image_horizontal);
            }
        }

        return response()->json($groups);
    }

    public function index_api()
    {
        $groups = PlaceGroup::all();
        //Order by name
        $groups = $groups->sortBy('name');

        //Get the images
        foreach ($groups as $group) {
            //If has not "imgur" in the image path, add it
            if (!str_contains($group->image_vertical, 'http')) {
                $group->image_vertical = asset('images/' . $group->image_vertical);
            }
            if (!str_contains($group->image_horizontal, 'http')) {
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

        $rules = [];

        $rules = $this->filterRules($placeGroup);

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


    public function createScheduleRule($group_id)
    {
        $group = PlaceGroup::find($group_id);
        $group->places;
        return view('location.schedule.rule.create', [
            'group' => $group,
        ]);
    }

    
    public function storeScheduleRule(Request $request)
    {

        $validated = $request->all();

        $rule = ScheduleRules::create($validated);

        //Check if has weekdays
        if (isset($validated['weekdays']) && !empty($validated['weekdays'])) {
            //Attach the weekdays to the rule
            $rule->weekdays()->attach($validated['weekdays']);
        }



        //Check if "all" in places, and remove
        $index = array_search('all', $validated['places']);
        if ($index !== false) {
            unset($validated['places'][$index]);
        }


        foreach ($validated['places'] as $place_id) {
            //Create the schedule rule with the relastionship to the place
            $place = Place::find($place_id);
            $place->scheduleRules()->attach($rule->id);
        }

  

        return redirect()->route('place-group.show', ['place_group' => $validated['place_group_id']]);
    }

    public function editScheduleRule($id)
    {
        $rule = ScheduleRules::find($id);
        $rule->weekdays;

        foreach ($rule->weekdays as $weekday) {
            unset($weekday->pivot);
        }

        $places = [];
        $rule->places;

        //Remove pivot from places
        foreach ($rule->places as $place) {
            unset($place->pivot);
        }

        $group_id = $rule->places->first()->place_group_id;
        $group = PlaceGroup::find($group_id);
        $group->places;
        unset($group->pivot);


        return view('location.schedule.rule.edit', [
            'rule' => $rule,
            'group' => $group,
        ]);
    }

    public function scheduleRules($id)
    {
        $placeGroup = PlaceGroup::where('id', $id)->first();
        $placeGroup->load('places.scheduleRules');

        return response()->json($rules);
    }

    public function updateScheduleRule(Request $request, $id){
        $validated = $request->all();
        
        $rule = ScheduleRules::find($id);



        $rule->update($validated);
        
        //Check if has weekdays
        if (isset($validated['weekdays']) && !empty($validated['weekdays'])) {
            //Detach all weekdays
            $rule->weekdays()->detach();
            //Attach the weekdays to the rule
            $rule->weekdays()->attach($validated['weekdays']);
        } else {
            //Detach all weekdays
            $rule->weekdays()->detach();
        }

        //Check if "all" in places, and remove
        $index = array_search('all', $validated['places']);
        if ($index !== false) {
            unset($validated['places'][$index]);
        }

        //Detach all places
        $rule->places()->detach();
        
        foreach ($validated['places'] as $place_id) {
            //Create the schedule rule with the relastionship to the place
            $place = Place::find($place_id);
            $place->scheduleRules()->attach($rule->id);
        }
        return redirect()->route('place-group.show', ['place_group' => $rule->places->first()->place_group_id]);
    }

    public function destroyScheduleRule($id)
    {
        $rule = ScheduleRules::find($id);

        //Detach all weekdays
        $rule->weekdays()->detach();

        $group_id = $rule->places->first()->place_group_id;
        
        //Detach all places
        $rule->places()->detach();

        //Delete the rule
        $rule->delete();

        return redirect()->route('place-group.show', ['place_group' => $group_id]);
    }

    public function createPlace($group_id)
    {
        $group = PlaceGroup::find($group_id);

        $group->load('places.scheduleRules');

        $rules = [];

        $rules = $this->filterRules($group);


        return view('location.place.create', [
            'place_group' => $group,
            'rules' => $rules,
        ]);
    }

    public function storePlace(Request $request)
    {
        
        $validated = $request->all();

         if ($request->hasFile('image')) {
            $imageName = time().'.'.$request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            $validated['image'] = $imageName;
        }

        $place = Place::create([
            'name' => $validated['name'],
            'place_group_id' => $validated['place_group_id'],
            'image' => $validated['image'] ?? null,
            'price' => $validated['price'],
        ]);

        //Check if has rules
        if (!isset($validated['rules']) || empty($validated['rules'])) {
            return redirect()->route('place-group.show', ['place_group' => $validated['place_group_id']]);
        }
        foreach ($validated['rules'] as $rule_id) {
            //Create the schedule rule with the relastionship to the place
            $place->scheduleRules()->attach($rule_id);
        }

        return redirect()->route('place-group.show', ['place_group' => $validated['place_group_id']]);
    }

    public function editPlace($place_id)
    {

        // $group = PlaceGroup::find($group_id);
        $place = Place::find($place_id);

        $rules = [];

        $rules = $this->filterRules($place->group);

        unset($place->pivot);
        unset($place->group);



        return view('location.place.edit', [
            'place' => $place,
            'rules' => $this->filterRules($place->group),
        ]);
    }

    public function updatePlace(Request $request, $place_id)
    {
        $validated = $request->all();

        $place = Place::find($place_id);

        if ($request->hasFile('image')) {
            $imageName = time().'.'.$request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            $validated['image'] = $imageName;
        } else {
            $validated['image'] = $place->image;
        }

        //Update the place
        $place->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'status' => $validated['status'],
            'image' => $validated['image'] ?? null,
        ]);

        $place->scheduleRules()->detach();
        //Check if has rules
        if (!isset($validated['rules']) || empty($validated['rules'])) {
            return redirect()->route('place-group.show', ['place_group' => $place->place_group_id]);
        } else {
            //Detach all rules
            foreach ($validated['rules'] as $rule_id) {
                //Create the schedule rule with the relastionship to the place
                $place->scheduleRules()->attach($rule_id);
            }
        }

        return redirect()->route('place-group.show', ['place_group' => $place->place_group_id]);
    }

    public function destroyPlace($place_id)
    {
        
        $place = Place::find($place_id);

        //Detach all rules
        $place->scheduleRules()->detach();

        //Delete the place
        $place->delete();

        return redirect()->route('place-group.show', ['place_group' => $place->place_group_id]);
    }

    
    

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlaceGroup $placeGroup)
    {
        //
    }

    public function filterRules($group){
        $ruleToPlaces = [];

        // Itera sobre os lugares como objetos
        foreach ($group->places as $place) {
            $place->schedule_rules;
            $placeData = [
                'id' => $place->id,
                'name' => $place->name
            ];

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
                $ruleToPlaces[$ruleId]['places'][] = $placeData;
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
        foreach ($group->places as $place) {
            unset($place->scheduleRules);
        }

        #Order rules by length of places
        usort($rules, function ($a, $b) {
            return count($b->places) <=> count($a->places);
        });

        //Remove weekdays pivot
        foreach ($rules as $rule) {
            if (isset($rule->weekdays)) {
                foreach ($rule->weekdays as $weekday) {
                    unset($weekday->pivot);
                }
            }
        }

        return $rules;
    }
}
