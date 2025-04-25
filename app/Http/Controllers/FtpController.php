<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;


class FtpController extends Controller
{
    // Método estático 'getImage' na classe 'FtpController'
    public static function getImage($imageName)
    {
        try {
            // Tenta ler o fluxo de dados da imagem do servidor FTP
            $stream = Storage::disk('ftp')->getDriver()->readStream($imageName);
            // Salva a imagem localmente no diretório 'img_car'
            Storage::disk('public')->put("img_car/" . $imageName, stream_get_contents($stream));
            // Retorna o caminho temporário para a imagem
            return $imageName;
        } catch (\Exception $e) {
            // Se ocorrer uma exceção (por exemplo, falha na conexão), retorna false
            return false;
        }
    }
}
