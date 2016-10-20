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

        $response = new Response(
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
        $response->setStatusCode($code);

        if ( $request->isXmlHttpRequest() ) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }


    /**
     * @inheritdoc
     */
    public function findTemplate(Request $request, $format, $code, $showException)
    {
        $name = $showException ? 'exception' : 'error';
        if ($showException && 'html' == $format) {
            $name = 'exception_full';
        }

        // TODO - need to decide on a more final format for the names of the twig files...
        if ( $request->isXmlHttpRequest() ) {
            $name = 'error';
            $format = 'json';
        }

        // For error pages, try to find a template for the specific HTTP status code and format
        if ($name == 'error') {
            $template = sprintf('@Twig/Exception/%s%s.%s.twig', $name, $code, $format);
            if ($this->templateExists($template)) {
                return $template;
            }
        }

        // try to find a template for the given format
        $template = sprintf('@Twig/Exception/%s.%s.twig', $name, $format);
        if ($this->templateExists($template)) {
            return $template;
        }

        // default to a generic HTML exception
        $request->setRequestFormat('html');

        return sprintf('@Twig/Exception/%s.html.twig', $showException ? 'exception_full' : $name);
    }
}
