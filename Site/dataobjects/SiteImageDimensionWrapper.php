<?php

/**
 * A recordset wrapper class for SiteImageDimension objects.
 *
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 *
 * @see       SiteImageDimension
 */
class SiteImageDimensionWrapper extends SwatDBRecordsetWrapper
{
    protected function init()
    {
        parent::init();

        $this->row_wrapper_class = SwatDBClassMap::get(SiteImageDimension::class);
        $this->index_field = 'id';
    }
}
