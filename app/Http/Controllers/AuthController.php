<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function __construct() {
        //ESPECIFICA QUE É NECESSÁRIO ESTA LOGADO EXCETO EM create() e login()
        $this->middleware('auth:api', ['except' => ['create', 'login', 'unauthorized']]);
    }    

    public function login(Request $request) {

        $array = ['error' => ''];

        $email = $request->input('email');
        $password = $request->input('password');

        $token = auth()->attempt([
            'email' => $email,
            'password' => $password
        ]);

        if(!$token) {
            $array['error'] = 'Usuário e/ou senha incorretos';
            return $array;
        }

        $info = auth()->user();
        $info['avatar'] = url('media/avatars'.$info['avatar']);
        $array['data'] = $info;
        $array['token'] = $token;

        return $array;
    }

    public function logout() {
        auth()->logout();
        return ['error'=>''];
    }

    public function refresh() {
        $array = ['error' => ''];

        $token = auth()->refresh();

        $info = auth()->user();
        $info['avatar'] = url('media/avatars'.$info['avatar']);
        $array['data'] = $info;
        $array['token'] = $token;

        return $array;
    }

    //TODA  ROTA REQUISITADA QUE PRECISE DE LOGIN É REDIRECIONADA PARA ESSE METODO
    public function unauthorized() {
        return response()->json([
            'error' => 'Não autorizado'
        ], 401);
    }
}
