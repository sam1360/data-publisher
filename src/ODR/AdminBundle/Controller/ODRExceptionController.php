<?php

/**
 * Open Data Repository Data Publisher
 * ODRException Controller
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * This controller overrides Symfony's built-in ExceptionController, because
 * the default Responses returned on uncaught errors/exceptions don't really
 * work nicely with ODR's extensive use of AJAX.
 *
 */

namespace ODR\AdminBundle\Controller;

use Symfony\Bundle\TwigBundle\Controller\ExceptionController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;


class ODRExceptionController extends ExceptionController
{

    /**
     * Overrides the
     *
     * @param Request $request
     * @param FlattenException $exception
     * @param DebugLoggerInterface|null $logger
     *
     * @return Response
     */
    public function showAction(Request $request, FlattenException $exception, DebugLoggerInterface $logger = null)
    {
        $currentContent = $this->getAndCleanOutputBuffering($request->headers->get('X-Php-Ob-Level', -1));
        $showException = $request->attributes->get('showException', $this->debug); // As opposed to an additional parameter, this maintains BC

        $code = $exception->getStatusCode();

        if ( !$request->isXmlHttpRequest() ) {
            // If a conventional GET request, return a full error page
            return new Response(
                $this->twig->render(
                    (string)$this->findTemplate($request, $request->getRequestFormat(), $code, $showException),
                    array(
                        'status_code' => $code,
                        'status_text' => isset(Response::$statusTexts[$code]) ? Response::$statusTexts[$code] : '',
                        'exception' => $exception,
                        'logger' => $logger,
                        'currentContent' => $currentContent,
                    )
                )
            );
        }
        else {
            // Otherwise...
            $return = array(
                'r' => 0,
                't' => '',
                'd' => '',
            );

            $return['error'] = $this->twig->render(
                (string)$this->findTemplate($request, $request->getRequestFormat(), $code, $showException),
                array(
                    'status_code' => $code,
                    'status_text' => isset(Response::$statusTexts[$code]) ? Response::$statusTexts[$code] : '',
                    'exception' => $exception,
                    'logger' => $logger,
                    'currentContent' => $currentContent,
                    'is_xml_http_request' => true,
                )
            );

            $response = new Response(json_encode($return));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
    }
}
