<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 * @version $Id: ModelFactory.php 2086 2016-08-14 08:01:48Z glenn $
 */

namespace Codeacious\Mapper;

interface ModelFactory
{
    /**
     * @param object $sourceModel The source model
     * @return object|null A serializable object representation of the source model, or null
     */
    public function createModel($sourceModel);
}