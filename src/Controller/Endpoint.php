<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Services\RateLimiter;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/api/v1")
 */
class Endpoint extends AbstractController
{

    private RateLimiter $RateLimiter;
    private array $rateLimiterErrorMessage;

    public function __construct(RateLimiter $RateLimiter){
        $this->RateLimiter = $RateLimiter;
        $this->rateLimiterErrorMessage = array('status'=>'ko','errorCode'=>1,'errorMessage'=>'Too many requests');
    }

    /**
     * @Route("/hello", name="hello_get", methods={"GET"})
     *
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function hello_get()
    {
        $return = array('status' => 'ok');
        if ($this->RateLimiter->rateLimiting()){
            $return = $this->rateLimiterErrorMessage;
        } else {
            $return['greeting'] = 'Hello GET';
        }
        return new Response(json_encode($return));
    }

    /**
     * @Route("/hello", name="hello_post", methods={"POST"})
     */
    public function hello_post()
    {
        $return = array('status' => 'ok');
        if ($this->RateLimiter->rateLimiting()){
            $return = $this->rateLimiterErrorMessage;
        } else {
            $return['greeting'] = 'Hello POST';
        }
        return new Response(json_encode($return));
    }


}