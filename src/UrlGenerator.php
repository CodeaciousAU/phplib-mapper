<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 * @version $Id: UrlGenerator.php 2086 2016-08-14 08:01:48Z glenn $
 */

namespace Codeacious\Mapper;


interface UrlGenerator
{
    /**
     * Get the URL to a named route.
     *
     * @param string $name
     * @param array $parameters
     * @param bool $absolute
     * @return string
     */
    public function route($name, $parameters = [], $absolute = true);
}