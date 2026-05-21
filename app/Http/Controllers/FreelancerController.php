<?php

namespace App\Http\Controllers;

use App\Models\Freelancer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreelancerRequest;
use App\Http\Requests\UpdateFreelancerRequest;
use App\Services\FreelancerService;
use Illuminate\Http\Request;

class FreelancerController extends Controller
{

    public function __construct()
    {
        $this->freelancerService = new FreelancerService();
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
    public function store(StoreFreelancerRequest $request)
    {
        try {
            $freelancer = $this->freelancerService->create($request->all());
            return response()->json($freelancer, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    
    }

    /**
     * Display the specified resource.
     */
    public function show($cpf)
    {
        try {
            $freelancer = $this->freelancerService->get($cpf);
            return response()->json($freelancer, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid CPF'], 400);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Freelancer $freelancer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFreelancerRequest $request, Freelancer $freelancer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Freelancer $freelancer)
    {
        //
    }
}
