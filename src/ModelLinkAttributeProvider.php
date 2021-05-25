<?php
/**
 * @author Glenn Schmidt <glenn@codeacious.com>
 */

namespace Codeacious\Mapper;

interface ModelLinkAttributeProvider
{
    /**
     * @param string $linkName
     * @param mixed $linkTarget
     * @return array
     */
    public function getLinkAttributes($linkName, $linkTarget);
}