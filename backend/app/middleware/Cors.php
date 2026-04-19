<?php

namespace app\middleware;

use Closure;
use think\Request;
use think\exception\HttpResponseException;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('origin') ?: '*';
        $headers = [
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Headers'     => 'Authorization,Content-Type,X-Requested-With',
            'Access-Control-Allow-Methods'     => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
        ];

        if ($request->isOptions()) {
            $response = response('', 204);
        } else {
            try {
                $response = $next($request);
            } catch (HttpResponseException $exception) {
                $response = $exception->getResponse();
                $this->applyHeaders($response, $headers);
                throw new HttpResponseException($response);
            }
        }

        $this->applyHeaders($response, $headers);

        return $response;
    }

    protected function applyHeaders($response, array $headers): void
    {
        foreach ($headers as $key => $value) {
            $response->header([$key => $value]);
        }
    }
}
