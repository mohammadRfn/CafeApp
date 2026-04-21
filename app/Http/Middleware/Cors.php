<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $this->getAllowOriginHeader($request));
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');  
        $response->headers->set('Access-Control-Max-Age', 3600);  

        if ($request->getMethod() == "OPTIONS") {
            return response('', 200)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        return $response;
    }

    /**
     *
     * @param Request $request
     * @return string
     */
    protected function getAllowOriginHeader(Request $request)
    {
        $allowedOrigins = [
            'https://annaponsprojects.com', 
            'http://localhost:5173',
        ];

        $origin = $request->headers->get('Origin');
        
        $allowedPatterns = [
            '/^https?:\/\/([a-z0-9-]+\.)?annaponsprojects\.com$/',
        ];

        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return $origin;
            }
        }

        return '*';
    }
}

