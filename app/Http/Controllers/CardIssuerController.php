<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;

class CardIssuerController extends Controller
{
    public function create()
    {
        $templates = CardTemplate::where('is_active', true)->orderBy('name')->get();

        return view('card-issuer.create', compact('templates'));
    }
}
