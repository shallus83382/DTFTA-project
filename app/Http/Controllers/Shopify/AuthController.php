<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class AuthController extends Controller
{
    //
    public function install(Request $request)
{
    $shop = $request->shop;

    $redirectUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
        'client_id' => config('shopify.client_id'),
        'scope' => config('shopify.scopes'),
        'redirect_uri' => route('shopify.callback'),
    ]);

    return redirect($redirectUrl);
}

    public function callback(Request $request)
    {
        $shop = $request->shop;
        $code = $request->code;

        // Exchange the authorization code for a permanent access token
        $response = Http::post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.client_id'),
            'client_secret' => config('shopify.client_secret'),
            'code' => $code,
        ]);

        $accessToken = $response->json()['access_token'];

        // Store the access token securely for future API calls
        // ...

        return redirect('/')->with('status', 'Shopify app installed successfully!');
    }   
}
