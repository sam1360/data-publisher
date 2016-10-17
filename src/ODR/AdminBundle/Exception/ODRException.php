<?php

namespace ODR\AdminBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ODRException extends HttpException
{

    /**
     * ODRException constructor.
     *
     * @param string $statusCode
     * @param null $message
     * @param integer $code
     */
    public function __construct($statusCode, $message, $code)
    {
        parent::__construct($statusCode, $message, null, array(), $code);
    }
}
