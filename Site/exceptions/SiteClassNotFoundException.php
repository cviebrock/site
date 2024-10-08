<?php

/**
 * Thrown when a class is not found.
 *
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteClassNotFoundException extends SiteException
{
    /**
     * The name of the class that is not found.
     *
     * @var string
     */
    protected $class_name;

    /**
     * Creates a new class not found exception.
     *
     * @param string $message    the message of the exception
     * @param int    $code       the code of the exception
     * @param string $class_name the name of the class that is not found
     */
    public function __construct($message = null, $code = 0, $class_name = null)
    {
        parent::__construct($message, $code);
        $this->class_name = $class_name;
    }

    /**
     * Gets the name of the class that is not found.
     *
     * @return string the name of the class that is not found
     */
    public function getClassName()
    {
        return $this->class_name;
    }
}
