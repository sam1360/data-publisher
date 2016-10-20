<?php

/**
 * Open Data Repository Data Publisher
 * ODRDeletedEntity Exception
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * TODO - ...
 */

namespace ODR\AdminBundle\Exception;

class ODRDeletedEntityException extends ODRException
{
    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message, self::getStatusCode());
    }


    /**
     * @inheritdoc
     */
    public function getStatusCode()
    {
        return "404";
    }
}
