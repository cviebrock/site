<?php

/**
 * Layout for an XMLRPC Server.
 *
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteXMLRPCServerLayout extends SiteLayout
{
    public function __construct($app, $template_name = null)
    {
        parent::__construct($app, SiteXMLRPCServerTemplate::class);
    }
}
