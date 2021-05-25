<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 * @version $Id: ModelRouteProvider.php 2086 2016-08-14 08:01:48Z glenn $
 */

namespace Codeacious\Mapper;

interface ModelRouteProvider
{
    /**
     * @return array
     */
    public function getRouteParameters();
}