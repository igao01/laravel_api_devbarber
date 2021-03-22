<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserAppointment;
use App\Models\Barber;
use App\Models\BarberService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct() {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    //PEGA AS INFORMACOES DO USUARIO
    public function read() {
        $array = ['error' => ''];

        $info = $this->loggedUser;
        $info['avatar'] = url('media/avatars/'.$info['avatar']);
        $array['data'] = $info;

        return $array;
    }

      //CRIA UM USUARIO
     public function create(Request $request) {
        $array = ['error' => ''];

        //VALIDA OS DADOS ENVIADO NA REQUISICAO
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if(!$validator->fails()) {
            $name = $request->input('name');
            $email = $request->input('email');
            $password = $request->input('password');

            //VERIFICAS SE EMAIL JA EXISTE
            $emailExists = User::where('email', $email)->count();

            if($emailExists === 0){
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $newUser = new User();
                $newUser->name = $name;
                $newUser->email = $email;
                $newUser->password = $hash;
                $newUser->save();

                
                //REALIZA LOGIN DO USUARIO
                $token = auth()->attempt([
                    'email' => $email,
                    'password' => $password
                ]);

                if(!$token) {
                    $array['error'] = 'Ocorreu um erro!';
                    return $array;
                }

                $info = auth()->user();
                $array['avatar'] = url('media/avatars/'.$info['avatar']);
                $array['data'] = $info;
                $array['token'] = $token;
                
                } else {
                    $array['error'] = 'E-mail já cadastrado';
                    return $array;
                }
        } else {
            $array['error'] = 'Dados incorretos';
            return $array;
        }
        return $array;
    }

    //MARCA E DESMARCA OS FAVORITOS
    public function toggleFavorite(Request $request) {
        $array = ['error'=>''];

        $id_barber = $request->input('barber');
        $barber = Barber::find($id_barber);

        if($barber) {
            $fav = UserFavorite::select()
                ->where('id_user', $this->loggedUser->id)
                ->where('id_barber', $barber->id)
            ->first();

            if($fav) {
                //REMOVE O BARBEIRO
                $fav->delete();
                $array['have'] = false;
            } else {
                // ADICIONA O BARBEIRO
                $newFav = new UserFavorite();
                $newFav->id_user = $this->loggedUser->id;
                $newFav->id_barber = $barber->id;
                $newFav->save();

                $array['have'] = true;
            }
        } else {
            $array['error'] = 'Barbeiro inexistente';
        }
        return $array;
    }

    //LISTA OS FAVORITOS
    public function getFavorites() {
        $array = ['error'=>'', 'list'=>[]];

        $favs = UserFavorite::select()
            ->where('id_user', $this->loggedUser->id)
        ->get();
        
        if($favs) {
            foreach($favs as $fav) {
                $barber = Barber::find($fav['id_barber']);
                $barber['avatar'] = url('media/avatars/'.$barber['avatar']);
                $array['list'][] = $barber;
            }
        } else {
            $array['error'] = 'Você não possui barbeiros favoritos';
        }
        return $array;        
    }

    //LISTA OS AGENDAMENTOS DO USUARIO
    public function getAppointments() {
        $array = ['error' => '', 'list' => []];

        $apps = UserAppointment::select()
            ->where('id_user', $this->loggedUser->id)
            ->orderBy('ap_datetime', 'ASC')
        ->get();

        if($apps) {

            foreach($apps as $app) {
                //PEGA OS DADOS DO BARBEIRO
                $barber = Barber::find($app['id_barber']);
                $barber['avatar'] = url('media/avatars/'.$barber['avatar']);

                //PEGA OS DADOS DO SERVICO
                $service = BarberService::find($app['id_service']);

                $array['list'][] = [
                    'id' => $app['id'],
                    'datetime' => $app['ap_datetime'],
                    'barber' => $barber,
                    'service' => $service
                ];
            }
        } else {
            $array['error'] = 'Você não possui agendamentos';
        }

        return $array;
    }

    //ATUALIZAR INFORMACOES DO USUARIO
    public function update(Request $request) {
        $array = ['error' => ''];

        //CRIA REGRAS DE VALIDAÇÃO
        $rules = [
            'name' => 'min:2',
            'email' => 'email|unique:users',
            'password' => 'same:password_confirm',
            'password_confirm' => 'same:password'
        ];

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()) {
            $array['error'] = $validator->messages();
            return $array;
        }

        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $password_confirm = $request->input('password_confirm');

        $user = User::find($this->loggedUser->id);

        //VERIFICA QUAIS CAMPOS PRECISAM SER ALTERADOS
        if($name) {
            $user->name = $name;
        }

        if($email) {
            $user->email = $email;
        }

        if($password) {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
        }
        $user->save();

        return $array;
    }

    //use Intervention\Image\Facades\Image;
    /*
    /   O METODO DE ALTERAR AVATAR NÃO FUNCIONA
    /   NÃO FOI POSSIVEL ADICIONAR BIBLIOTECA DE EDICAO DE IMAGEM
    /   O COMPOSER NAO ATUALIZA
    */

    //ATUALIZA O AVATAR DO USUARIO
    /*
    public function updateAvatar(Request $request) {
        $array = ['error' => ''];

        $rules = [
            'avatar' => 'required|image|mimes:png,jpg,jpeg'
        ];
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()) {
            $array['error'] = $validator->messages();
            return $array;
        }

        $avatar = $request->file('avatar');

        $dest = public_path('/media/avatars');
        $avatarName = md5(time().rand(0,9999)).'jpg';

        $img = Image::make($avatar->getRealPath());
        $img->fit(300, 300)->save($dest.'/'.$avatarName);

        $user = Usser::find($this->loggedUser->id);
        $user->avatar = $avatarName;
        $user->save();

        return $array;
    }*/
}
