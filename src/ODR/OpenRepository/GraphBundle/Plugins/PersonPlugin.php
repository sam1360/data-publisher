<?php

/**
 * Open Data Repository Data Publisher
 * Person Plugin
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * The person plugin requires the datatype to have a number of
 * datafields for storing metadata about a person.
 *
 */

namespace ODR\OpenRepository\GraphBundle\Plugins;


class PersonPlugin
{
    /**
     * @var mixed
     */
    private $templating;


    /**
     * URLPlugin constructor.
     *
     * @param $templating
     */
    public function __construct($templating) {
        $this->templating = $templating;
    }


    /**
     * Executes the Person Plugin on the provided datarecords
     *
     * @param array $datarecords
     * @param array $datatype
     * @param array $render_plugin
     * @param array $theme
     * @param array $rendering_options
     *
     * @return string
     * @throws \Exception
     */
    public function execute($datarecords, $datatype, $render_plugin, $theme, $rendering_options)
    {
        // This render plugin does not override any part of the rendering, and therefore this function will never be called.
        return '';
    }
}
