<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 * @version $Id: ModelUriProvider.php 2756 2017-05-19 07:53:20Z glenn $
 */

namespace Codeacious\Mapper;

interface ModelUriProvider
{
    /**
     * @return string
     */
    public function getUri();
}