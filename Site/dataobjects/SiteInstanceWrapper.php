<?php

/**
 * A recordset wrapper class for SiteInstance objects.
 *
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteInstanceWrapper extends SwatDBRecordsetWrapper
{
    protected function init()
    {
        parent::init();
        $this->index_field = 'id';
        $this->row_wrapper_class = SwatDBClassMap::get(SiteInstance::class);
    }
}
