<?php

namespace TreeHouse\ConanStatisticoBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use TreeHouse\ConanStatisticoBundle\Exception\DirectResponseException;

class DirectResponseListener
{
    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if ($exception instanceof DirectResponseException) {
            $event->setResponse($exception->getResponse());
            $event->stopPropagation();
        }
    }
}
