<?php

namespace App\Http\Controllers;

use App\Models\FunctionFreelancer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFunctionFreelancerRequest;
use App\Http\Requests\UpdateFunctionFreelancerRequest;
use App\Services\FreelancerService;

class FunctionFreelancerController extends Controller
{
    protected $freelancerService;
    
    public function __construct()
    {
        $this->freelancerService = new FreelancerService();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $functions = $this->freelancerService->getFunctions();
            return response()->json($functions, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
    public function store(StoreFunctionFreelancerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(FunctionFreelancer $functionFreelancer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FunctionFreelancer $functionFreelancer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFunctionFreelancerRequest $request, FunctionFreelancer $functionFreelancer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FunctionFreelancer $functionFreelancer)
    {
        //
    }
}
