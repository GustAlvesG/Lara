<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Freelancer;
use App\Models\FunctionFreelancer;
use App\Models\FreelancerService as FreelancerServiceModel;




class FreelancerService
{
    public function create($data)
    {
        $freelancer = Freelancer::create($data);
        return $freelancer;
    }

    public function get($cpf)
    {
        $freelancer = Freelancer::where('cpf', $cpf)->first();
        return $freelancer;
    }

    public function getFunctions()
    {
        $functions = FunctionFreelancer::all();
        return $functions;
    }

    public function createService($data)
    {
        $service = FreelancerServiceModel::create($data);
        return $service;
    }


}