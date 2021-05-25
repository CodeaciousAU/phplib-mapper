<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 * @version $Id: ModelHalProvider.php 2086 2016-08-14 08:01:48Z glenn $
 */

namespace Codeacious\Mapper;

use Nocarrier\Hal;

interface ModelHalProvider
{
    /**
     * @param Mapper $mapper
     * @return Hal
     */
    public function toHalResource(Mapper $mapper);
}