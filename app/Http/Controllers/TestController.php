<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\RedeItauService;

class TestController extends Controller
{
    public function __construct(protected RedeItauService $redeItauService)
    {
        $this->redeItauService = $redeItauService;
    }

    public function index()
    {
        $response = $this->redeItauService->getTransaction('10472602100635122313');

        return view('emails.payment', ['transaction' => $response]);
    }
}
