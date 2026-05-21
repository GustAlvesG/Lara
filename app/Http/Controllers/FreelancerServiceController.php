<?php

namespace App\Http\Controllers;

use App\Models\FreelancerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Http\Requests\UpdateFreelancerServiceRequest;


class FreelancerServiceController extends Controller
{
    protected $freelancerService;
    protected function __construct($freelancerService)
    {
        $this->freelancerService = $freelancerService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreFreelancerServiceRequest $request)
    {
        
    }

    /**
     * Display the specified resource.
     */
    public function show(FreelancerService $freelancerService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FreelancerService $freelancerService)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFreelancerServiceRequest $request, FreelancerService $freelancerService)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FreelancerService $freelancerService)
    {
        //
    }
}
