<?php

/**
 * Open Data Repository Data Publisher
 * ODRException
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * TODO - ...
 */

namespace ODR\AdminBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ODRException extends HttpException
{
    /**
     * @param string $message
     * @param string|null $statusCode
     * @param integer|null $source
     * @param \Exception|null $previous_exception
     */
    public function __construct($message, $statusCode = null, $source = null, \Exception $previous_exception = null)
    {
        if ( is_null($statusCode) )
            $statusCode = "500";

        parent::__construct($statusCode, $message, $previous_exception, array(), $source);
    }
}
