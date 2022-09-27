<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ConnectyuLoginController extends Controller
{
    public function store()
    {
        //following connectyu's api documentation
        $app_id = env('CONNECTYU_APP_ID');
        $app_secret = env('connectyu_app_key');
        $code = $_GET['code']; // the GET parameter you got in the callback: http://yourdomain/?code=XXX
        $get = file_get_contents("https://www.connectyu.com/authorize?app_id={$app_id}&app_secret={$app_secret}&code={$code}");
        $json = json_decode($get, true);
        if (!empty($json['access_token'])) {
            $access_token = $json['access_token']; // your access token
            $type = "get_user_data"; // or posts_data
            $get = file_get_contents("https://www.connectyu.com/app_api?access_token={$access_token}&type={$type}");
            $connectyu_data = json_decode($get, true);
//            convert any $connectyu_data to a single dimensional array
            //function to convert multi to single dimentional array
            function array_flatten($array)
            {
                if (!is_array($array)) {
                    return FALSE;
                }
                $result = array();
                foreach ($array as $key => $value) {
                    if (is_array($value)) {
                        $result = array_merge($result, array_flatten($value));
                    } else {
                        $result[$key] = $value;
                    }
                }
                return $result;
            }

            $data = array_flatten($connectyu_data);//we create a new 1 dimentional array
            ddd($data);
//            we confirm that the user logged in to connectyu before we proceed
//            plan for authentication
//                login to connectyu
//            check if data already exist else create and login  new user

            if ($data['status'] == '200') {

                if (User::where('email', '=', $data['email'])->exists()) {// we check if feilds already exist
                    // user found
                    $credentials = [      //our login credentials
                        'email' => $data['email'],
                        'password' => $data['email'] . $data['username']
                    ];
                    if (Auth::attempt($credentials)) { //we login our user with the above credentials
                        return redirect()->intended('dashboard')
                            ->withSuccess('Signed in');
                    }

                } else { //else we create new account


                    $user = User::create([
                        'name' => $data['username'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['email'] . $data['username']),
                    ]);

                    event(new Registered($user));
                    ddd($user);

                    Auth::login($user);

                    return redirect(RouteServiceProvider::HOME);
                }


            }
        }


    }
}

