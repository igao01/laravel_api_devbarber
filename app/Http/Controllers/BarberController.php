<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illumininate\Support\Facades\Auth;

use App\Models\User;
use App\Models\UserAppointment;
use App\Models\UserFavorite;
use App\Models\Barber;
use App\Models\BarberPhotos;
use App\Models\BarberService;
use App\Models\BarberTestimonial;
use App\Models\BarberAvailability;

class BarberController extends Controller
{
    private $loggedUser;
   
    public function __construct() {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    //METODO DE BUSCA UTILIZANDO API DO GOOGLE
    private function searchGeo($address){
        //PEGA KEY DA API ADICIONADA NO .env
        $key = env('MAPS_KEY', null);

        $address = urlencode($address);

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.$key;
        
        //UTILIZA A BIBLIOTECA curl PARA FAZER A REQUISICÃO
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //PEGA A RESPOSTA DA REQUISICAO
        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true);
    }

    //LISTA OS BARBEIROS
    public function list(Request $request) {
        $array = ['error' => ''];

        //BUSCA LOCALIZACAO
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $city = $request->input('city');

        //OFFSET PARA PAGINAÇÃO
        $offset = $request->input('offset');
        if(!$offset) {
            $offset = 0;
        }

        if(!empty($city)) {
            $res = $this->searchGeo($city);

            if(count($res['results']) > 0) {
                $lat = $res['results'][0]['geometry']['location']['lat'];
                $lng = $res['results'][0]['geometry']['location']['lng'];
            }
        } elseif(!empty($lat) && !empty($lng)) {
            $res = $this->searchGeo($lat.','.$lng);

            if(count($res['results']) > 0) {
                $city = $res['results'][0]['formatted_address'];
            }
        } else {
            $lat = '-23.5630907';
            $lng = '-46.6682795';
            $city = 'São Paulo';
        }

        //BUSCA BARBEIRO POR PROXIMIDADE
        $barbers = Barber::select(Barber::raw('*, SQRT(
            POW(69.1 * (latitude - '.$lat.'), 2) +
            POW(69.1 * ('.$lng.' - longitude) * COS(latitude / 57.3), 2)) AS distance')) //CALCULA A DISTANCIA DO PONTO DE ORIGEM ATE BARBEIRO
            ->havingRaw('distance < ?',[10]) //PEGA OS BARBEIROS EM UM RAIO DE 10Km
            ->orderBy('distance', 'ASC') //LISTA DE ACORDO COM A DISTANCIA
            ->offset($offset) //CRIA PAGINAÇAO DE 5 EM 5
            ->limit(5)
            ->get();

        //CONCATENA A URL DO AVATAR
        foreach($barbers as $bkey => $bvalue){
            $barbers[$bkey]['avatar'] = url('media/avatars/'.$barbers[$bkey]['avatar']);
        }

        $array['data'] = $barbers;
        $array['loc'] = 'São Paulo';

        return $array;
    }

    //EXIBE OS DETALHES DE UM BARBEIRO
    public function one($id) {
        $array = ['error' => ''];

        $barber = Barber::find($id);

        if($barber) {

            $barber['avatar'] = url('media/avatars/'.$barber['avatar']); 
            $barber['favorited'] = false;
            $barber['photos'] = [];
            $barber['services']  = [];
            $barber['testimonials'] = [];
            $barber['availability'] = [];   

            //VERIFICA SE O BARBEIRO E FAVORITO
            $cFavorite = UserFavorite::where('id_user', $this->loggedUser->id)
                ->where('id_barber', $barber->id)
                ->count();
            if($cFavorite > 0)
                $barber['favorited'] = true;

            //PEGA AS FOTOS DO BARBEIRO
            $barber['photos'] = BarberPhotos::select(['id', 'url'])
                ->where('id_barber', $barber->id)
                ->get();
            //ARRUMA A URL DAS PHOTOS
            foreach($barber['photos'] as $bpkey => $bpvalue) {
                $barber['photos'][$bpkey]['url'] = url('media/uploads/'.$barber['photos'][$bpkey]['url']);
            }

            //PEGA OS SERVIÇOS DO BARBEIRO
            $barber['services'] = BarberService::select(['id', 'name', 'price'])
                ->where('id_barber', $barber->id)
                ->get();
            
            //PEGA OS DEPOIMENTOS DO BARBEIRO
            $barber['testimonials'] = BarberTestimonial::select(['id', 'name', 'rate', 'body'])
            ->where('id_barber', $barber->id)
            ->get();

            //PEGA OS HORARIOS DO BARBEIRO
            $availability = [];

            //PEGA DISPONIBILIDADE GERAL
            $avails = BarberAvailability::select(['id', 'weekday', 'hours'])
            ->where('id_barber', $barber->id)
            ->get();
            $availsWeekday = [];
            foreach($avails as $item) {
                //UTILIZA CADA DIA DA SEMANA COMO UMA CHAVE E TRANSFORMA OS HORARIOR EM ARRAY
                $availsWeekday[$item['weekday']] = explode(',', $item['hours']);
            }

            // PEGA OS AGENDAMENTOS DO BARBEIRO
            $appointments = [];
            //PEGA OS AGENDAMENTOS DOS PROXIMOS 20 DIAS
            $appQuery = UserAppointment::where('id_barber', $barber->id)
                ->whereBetween('ap_datetime', [
                    date('Y-m-d').' 00:00:00', //data hoje
                    date('Y-m-d', strtotime('+20 days')).' 23:59:59' // 20 dias
                ])
                ->get();

                foreach($appQuery as $appItem) {
                    $appointments[] = $appItem['ap_datetime']; //SALVA OS AGENDAMENTOS
                }

                $dayFormated = '';
                // GERAR DISPONIBILIDADE REAL
                for($q=0; $q<20; $q++) {    // $q numero de dias
                    $timeItem = strtotime('+' .$q. ' days');
                    $weekday = date('w', $timeItem);    //pega o dia da semana

                    if(in_array($weekday, array_keys($availsWeekday))) { //verifica se há o dia da semana no array de disponibilidade da semana
                        $hours = [];

                        $dayItem = date('Y-m-d', $timeItem); //data 

                        foreach($availsWeekday[$weekday] as $hourItem) { 
                            $dayFormated = $dayItem. ' ' .$hourItem. ':00'; //pra cada dia da semana é atribuido os horarios

                            if(!in_array($dayFormated, $appointments)) { // se horario nao esta nos agendamentos é adicionado como disponivel
                                $hours[] = $hourItem;
                            }
                        }
                        //VERIFICA SE HA HORARIOS DISPONIVEIS
                        if(count($hours) > 0) {
                            $availability[] = [
                                'date' => $dayItem,
                                'hours' => $hours
                            ];
                        }
                    }
                }
            $barber['availability'] = $availability;
            $array['data'] = $barber;
            
        } else {
            $array['error'] = 'Barbeiro não existe';
            return $array;
        }
        return $array;
    }

    //AGENDA UM HORARIO
    public function setAppointment($id, Request $request) {

        $array = ['error' => ''];

        $service = $request->input('service');
        $year = intval($request->input('year'));
        $month = intval($request->input('month'));
        $day = intval($request->input('day'));
        $hour = intval($request->input('hour'));

        $month = ($month < 10) ? '0'.$month : $month;
        $day = ($day < 10) ? '0'.$day : $day;
        $hour = ($hour < 10) ? '0'.$hour : $hour;

        // VERIFICAR SE O SERVIÇO DO BARBEIRO EXISTE
        $barberservice = BarberService::select()
            ->where('id', $service) //verificar se é $service->id ou $service
            ->where('id_barber', $id)
            ->first();

        if($barberservice) {
            //VERIFICAR SE A DATA É REAL
            $apDate = $year. '-' .$month. '-' .$day. ' ' .$hour.':00:00';

            if(strtotime($apDate) > 0) {

                // VERIFICAR SE O BARBEIRO JA POSSUI AGENDAMENTO
                $apps = UserAppointment::where('id_barber', $id)
                    ->where('ap_datetime', $apDate)
                    ->count();

                if($apps === 0) {
                    // VERIFICAR SE O BARBEIRO ATENDE NESTA DATA
                    $weekday = date('w', strtotime($apDate));

                    $avail = BarberAvailability::where('id_barber', $id)
                        ->where('weekday', $weekday)
                    ->first();

                    if($avail) {
                        //VERIFICAR SE ELE ATENDE NESSA HORA
                        $hours = explode(',', $avail['hours']);            

                        if(in_array($hour.':00', $hours)) {
                            
                            //FAZER O AGENDAMENTO
                            $newApp = new UserAppointment();
                            $newApp->id_user = $this->loggedUser->id;
                            $newApp->id_barber = $id;
                            $newApp->id_service = $service;
                            $newApp->ap_datetime = $apDate;
                            $newApp->save();
                        } else {
                            $array['error'] = 'O barbeiro não atende nesta hora';
                        }
                    } else {
                        $array['error'] = 'O barbiro nao atende nesta data';
                    }
                } else {
                    $array['error'] = 'Barbeiro já possui agendamento neste dia/hora.';
                }
            } else {
                $array['error'] = 'Data inválida';
            }
        } else {
            $array['error'] = 'Serviço inexistente';
        }
        return $array;
    }

    //BUSCA UM BARBEIRO ELA PESQUISA
    public function search(Request $request) {
        $array = ['error'=>'', 'list'=>[]];

        $q = $request->input('q');

        if($q) {
            $barbers = Barber::select()
                ->where('name', 'LIKE', '%'. $q .'%')
            ->get();

            foreach($barbers as $bkey=>$barber) {
                $barbers[$bkey]['avatar'] = url('media/avatars/'. $barbers[$bkey]['avatar']);
            }
            $array['list'] = $barbers;
        } else {
            $array['error'] = 'Digite algo para buscar';
        }
        return $array;
    }

    /*public function createRandom() {
        $array = ['error'=>''];

        for($q=0; $q<15; $q++) {
            $names = ['Boniek', 'Paulo', 'Pedro', 'Amanda', 'Leticia', 'Gabriel', 'Gabriela', 'Thais', 'Luiz', 'Diogo', 'José', 'Jeremias', 'Francisco', 'Dirce', 'Marcelo' ];
            $lastnames = ['Santos', 'Silva', 'Santos', 'Silva', 'Alvaro', 'Sousa', 'Diniz', 'Josefa', 'Luiz', 'Diogo', 'Limoeiro', 'Santos', 'Limiro', 'Nazare', 'Mimoza' ];
            $servicos = ['Corte', 'Pintura', 'Aparação', 'Unha', 'Progressiva', 'Limpeza de Pele', 'Corte Feminino'];
            $servicos2 = ['Cabelo', 'Unha', 'Pernas', 'Pernas', 'Progressiva', 'Limpeza de Pele', 'Corte Feminino'];
            $depos = [
            'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
            'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
            'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
            'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
            'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.'
            ];
            $newBarber = new Barber();
            $newBarber->name = $names[rand(0, count($names)-1)].' '.$lastnames[rand(0, count($lastnames)-1)];
            $newBarber->avatar = rand(1, 4).'.png';
            $newBarber->stars = rand(2, 4).'.'.rand(0, 9);
            $newBarber->latitude = '-23.5'.rand(0, 9).'30907';
            $newBarber->longitude = '-46.6'.rand(0,9).'82759';
            $newBarber->save();
            $ns = rand(3, 6);
            for($w=0;$w<4;$w++) {
                $newBarberPhoto = new BarberPhotos();
                $newBarberPhoto->id_barber = $newBarber->id;
                $newBarberPhoto->url = rand(1, 5).'.png';
                $newBarberPhoto->save();
            }
            for($w=0;$w<$ns;$w++) {
                $newBarberService = new BarberService();
                $newBarberService->id_barber = $newBarber->id;
                $newBarberService->name = $servicos[rand(0, count($servicos)-1)].' de '.$servicos2[rand(0, count($servicos2)-1)];
                $newBarberService->price = rand(1, 99).'.'.rand(0, 100);
                $newBarberService->save();
            }
            for($w=0;$w<3;$w++) {
                $newBarberTestimonial = new BarberTestimonial();
                $newBarberTestimonial->id_barber = $newBarber->id;
                $newBarberTestimonial->name = $names[rand(0, count($names)-1)];
                $newBarberTestimonial->rate = rand(2, 4).'.'.rand(0, 9);
                $newBarberTestimonial->body = $depos[rand(0, count($depos)-1)];
                $newBarberTestimonial->save();
            }
            for($e=0;$e<4;$e++){
                $rAdd = rand(7, 10);
                $hours = [];

                for($r=0;$r<8;$r++) {
                    $time = $r + $rAdd;
                    if($time < 10) {
                    $time = '0'.$time;
                    }
                $hours[] = $time.':00';
                }
                $newBarberAvail = new BarberAvailability();
                $newBarberAvail->id_barber = $newBarber->id;
                $newBarberAvail->weekday = $e;
                $newBarberAvail->hours = implode(',', $hours);
                $newBarberAvail->save();
            }
        }
        return $array;
    }*/
}
