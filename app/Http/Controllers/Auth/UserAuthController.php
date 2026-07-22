<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginByMatriculaRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserAuthController extends Controller
{
    /**
     * Login de usuário do sistema por matrícula + senha, para a integração
     * externa (bot do Telegram) identificar qual usuário está agindo.
     * Restrito a usuários com o papel "comercial".
     */
    public function login(LoginByMatriculaRequest $request)
    {
        $user = User::where('matricula', $request->validated('matricula'))->first();
        

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['error' => 'Credenciais inválidas', 'data' => $user ?? 'sem usuario'], 401);
        }

        if (!$user->hasRole('comercial')) {
            return response()->json(['error' => 'Usuário não tem permissão para acessar'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['user' => $user], 200);
    }
}
