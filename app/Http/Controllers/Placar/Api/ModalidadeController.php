<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Placar\ModalidadeResource;
use App\Models\Placar\Modalidade;

class ModalidadeController extends Controller
{
    public function index()
    {
        return ModalidadeResource::collection(
            Modalidade::ativas()->orderBy('nome')->get()
        );
    }
}
