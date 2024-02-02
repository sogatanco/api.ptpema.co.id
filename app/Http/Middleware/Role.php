<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Role
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ... $roles): Response
    {

        $user = auth()->user();

        if(!$user) {
            return response()->json([
                "message" => "Unauthenticated."
            ], 401);
        }

        if(in_array("Super Admin", $user->roles)){
            // return $next($request)
            return response()->json([
                "message" => "Super Admin!"
            ], 200);
        }
    
        foreach ($roles as $role) {
            if(in_array($role , $user->roles)){
                return $next($request);
                // return response()->json([
                //     "message" => "allow to access!!"
                // ], 200);
            }
        }

        return response()->json([
            "message" => "Unauthorized!",
            "roles" => $roles
        ], 401);
        
    }
}
