<?php

namespace TreeHouse\ConanStatisticoBundle\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DirectResponseException extends \Exception implements HttpExceptionInterface
{
    /**
     * @var Response
     */
    protected $response;

    /**
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * @param Response $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        return $this->response->headers->all();
    }

    /**
     * @inheritdoc
     */
    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }
}
