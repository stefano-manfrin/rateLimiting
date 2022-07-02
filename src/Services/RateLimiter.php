<?php

namespace App\Services;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;


class RateLimiter
{

    protected $cache;
    private $clientIp;
    private $requestUri;
    private $method;
    private $method_requestPerSeconds;

    public function __construct()
    {
        $client = RedisAdapter::createConnection('redis://localhost');
        $this->cache = new RedisTagAwareAdapter($client);
        $req = Request::createFromGlobals();
        $this->clientIp = $req->getClientIp();
        $this->requestUri = $req->getRequestUri();
        $this->method = $req->getMethod();
        $this->method_requestPerSeconds = $this->getLimitedRoutes();
    }

    /**
     * Get list of restricted routes and their rateLimiting settings
     *
     * @return array
     */
    private function getLimitedRoutes(){
        $method_requestPerSeconds = array();
        foreach(explode("\n", file_get_contents('../config/routes/rateLimiter')) as $routeSetting) {
            $routeSetting = explode(':', $routeSetting);
            $method_requestPerSeconds[$routeSetting[0].$routeSetting[1]] = $routeSetting[2];
        }
        return $method_requestPerSeconds;
    }

    /**
     * Check if default number of request per seconds is set for the route
     *
     * @return false|string
     */
    private function checkLimitedRoute(){
        $k = $this->method.$this->requestUri;
        if (isset($this->method_requestPerSeconds[$k])){
            return $this->method_requestPerSeconds[$k];
        } else {
            return false;
        }
    }

    /**
     * Set first hit of a request to the routeCounter
     *
     * @param $routeCounter
     * @return void
     */
    private function setFirstHit($routeCounter)
    {
        $routeCounter->set(time().'|'.'1');
        $this->cache->save($routeCounter);
    }

    /**
     * Limit requests to the current route
     *
     * @return true|false (limit|don't limit)
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function rateLimiting()
    {
        $limit = $this->checkLimitedRoute();
        if ($limit === false){
            //route doesn't have settings to limit number of request per seconds
            return false;
        } else {
            //route has settings to limit number of request per seconds
            $requestPerSeconds = explode('/', $limit);
            $max_request = (int)$requestPerSeconds[0];
            $seconds = (int)$requestPerSeconds[1];
            $k = md5($this->clientIp.$this->requestUri.$this->method);
            $routeCounter = $this->cache->getItem($k);
            $time_connection = $routeCounter->get();

            if ($time_connection == NULL){
                $this->setFirstHit($routeCounter);
                return false;
            } else {
                $time_connection = explode('|', $time_connection);
                $first_hit = (int)$time_connection[0];
                $connections = (int)$time_connection[1];
                $connections++;
                if ($first_hit >= time() - $seconds){
                    //check for restrictions
                    if ($connections <= $max_request) {
                        $routeCounter->set($first_hit.'|'.$connections);
                        $this->cache->save($routeCounter);
                        return false;
                    } else {
                        return true;
                    }
                } else {
                    $this->setFirstHit($routeCounter);
                    return false;
                }
            }
        }


    }

}