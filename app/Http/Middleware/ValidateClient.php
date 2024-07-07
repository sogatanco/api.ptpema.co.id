<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Client\Client;

class ValidateClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query('id');
        $token = $request->bearerToken();

        if($query){
            $client = Client::where('client_id', $query)->first();
            if($client && $client->client_secret == $token){
                return $next($request);
            }else{
                throw new HttpResponseException(response([
                    "status" => false,
                    "message" => "Unauthenticated"
                ], 401));
            }
        }else{
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Bad Request"
            ], 400));
        }

    }
}
