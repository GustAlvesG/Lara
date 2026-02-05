<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Place;
use App\Services\SchedulesService;
use App\Services\ScheduleRulesService;


class PlaceService
{
    protected $schedulesService;
    protected $scheduleRulesService;
    public function __construct(SchedulesService $schedulesService, ScheduleRulesService $scheduleRulesService)
    {
        $this->schedulesService = $schedulesService;
        $this->scheduleRulesService = $scheduleRulesService;
    }

    public function getPlacesByGroup($group_id, $member_id, $date)
    {
        $places = Place::where('place_group_id', $group_id)->where('status_id', 1)->get();
        //Order by name
        $places = $places->sortBy('name');
        
        $places->load('scheduleRules');

        foreach ($places as $place) {
            //If has not "imgur" in the image path, add it
            if (!str_contains($place->image, 'http')) {
                $place->image = asset('images/' . $place->image);
            }
            $place->available_slots = 0;
            $place->is_all_excluded = 0;
            $time_options = $this->scheduleRulesService->getTimeOptions($place->id, $date);
            foreach ($time_options as $option) {
                if (!$option['colides']){
                    $place->available_slots += 1;
                }
                if (isset($option['excluded_by_rule'])){
                    $place->is_all_excluded += 1;
                }
            }
            if ($place->is_all_excluded == count($time_options)){
                $place->available_slots = -1;
            }
            unset($place->scheduleRules);
            unset($place->is_all_excluded);
        }

        $group = $places[0]->group;

        unset($places[0]->group);

        if (!str_contains($group->image_horizontal, 'http')) {
            $group->image_horizontal = asset('images/' . $group->image_horizontal);
        }

        $limit = $this->schedulesService->countMemberSchedulesInPlaceGroupOnDate($group, $member_id, $date);
        
        return response()->json([
            'group' => $group,
            'places' => $places,
            'limit' => $limit
        ]); 
    }
}