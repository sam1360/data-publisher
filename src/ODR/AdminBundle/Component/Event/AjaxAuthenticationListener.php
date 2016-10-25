<?php

/**
 * Open Data Repository Data Publisher
 * Ajax Authentication Listener
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * Listens for exceptions raised by the firewall during execution of AJAX
 * events, and re-throws one of ODR's custom exceptions to "handle" it.
 *
 */

namespace ODR\AdminBundle\Component\Event;

// Exceptions
use ODR\AdminBundle\Exception\ODRPermissionDeniedException;
// Symfony
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


class AjaxAuthenticationListener
{
    /**
     * Handles security related exceptions.
     *
     * @param GetResponseForExceptionEvent $event An GetResponseForExceptionEvent instance
     */
    public function onCoreException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $request = $event->getRequest();

        if ($request->isXmlHttpRequest()) {
            // ...do somthing if this exception was caused during an AJAX request
            if ($exception instanceof AuthenticationException || $exception instanceof AccessDeniedException) {
//                $event->setResponse(new Response('', 403));
                $event->setException( new ODRPermissionDeniedException($exception->getMessage()) );
            }
        }
        else {
            // ...do something if this exception was not caused during an AJAX request
            if ($exception instanceof AuthenticationException || $exception instanceof AccessDeniedException) {
//                $event->setResponse(new Response('', 403));
                $event->setException( new ODRPermissionDeniedException($exception->getMessage()) );
            }
        }
    }
}
