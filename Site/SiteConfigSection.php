<?php

/**
 * Configuration section for the configuration module.
 *
 * @copyright 2007-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteConfigSection extends SwatObject implements Iterator
{
    /**
     * The name of this configuration section.
     *
     * @var string
     */
    private $name;

    /**
     * Settings of this configuration section.
     */
    private $values = [];

    /**
     * The config module of this section.
     *
     * @var SiteConfigModule
     */
    private $config;

    /**
     * Creates a new configuration section.
     *
     * @param string           $name   the name of this configuration section
     * @param array            $values an associative array containing values as parsed
     *                                 from an ini file
     * @param SiteConfigModule $config the config module of this section
     * @param int              $source optional. The setting source of the
     *                                 <code>$values</code> array.
     */
    public function __construct(
        $name,
        array $values,
        SiteConfigModule $config,
        $source = SiteConfigModule::SOURCE_FILE
    ) {
        $this->name = (string) $name;
        $this->values = $values;
        $this->config = $config;

        foreach ($this->values as $name => $value) {
            $this->config->setSource($this->name, $name, $source);
        }
    }

    /**
     * Gets a string representation of this config section.
     *
     * This gets the name value pairs of this section with the section name as
     * the header.
     *
     * @return string a string representation of this config section
     */
    public function __toString(): string
    {
        ob_start();

        $is_empty = true;
        foreach ($this->values as $name => $value) {
            if ($value !== null) {
                $is_empty = false;
            }
        }

        if (!$is_empty) {
            echo '[', $this->name, "]\n";
            foreach ($this->values as $name => $value) {
                if ($value != '') {
                    if ($value == 1) {
                        $value = 'On';
                    } else {
                        $value = '"' . $value . '"';
                    }

                    echo $name, ' = ', $value, "\n";
                }
            }
            echo "\n";
        }

        return (string) ob_get_clean();
    }

    /**
     * Returns the current value.
     *
     * @return mixed the current value
     */
    public function current(): mixed
    {
        return current($this->values);
    }

    /**
     * Returns the key of the current value.
     *
     * @return int the key of the current value
     */
    public function key(): int
    {
        return key($this->values);
    }

    /**
     * Moves forward to the next value.
     */
    public function next(): void
    {
        next($this->values);
    }

    /**
     * Rewinds this iterator to the first value.
     */
    public function rewind(): void
    {
        reset($this->values);
    }

    /**
     * Checks is there is a current value after calls to rewind() and next().
     *
     * @return bool true if there is a current value and false if there
     *              is not
     */
    public function valid(): bool
    {
        return key($this->values) !== null;
    }

    /**
     * Sets a setting of this configuration section.
     *
     * @param string $name  the name of the setting
     * @param mixed  $value the value of the setting
     *
     * @throws SiteException if the name of the setting being set does not
     *                       exist in this section
     */
    public function __set($name, $value)
    {
        if (!array_key_exists($name, $this->values)) {
            throw new SiteException(
                sprintf(
                    "Can not set configuration setting. Setting '%s' " .
                    "does not exist in the section '%s'.",
                    $name,
                    $this->name
                )
            );
        }

        $this->values[$name] = $value;

        $this->config->setSource(
            $this->name,
            $name,
            SiteConfigModule::SOURCE_RUNTIME
        );
    }

    /**
     * Gets a setting of this configuration section.
     *
     * @param string $name the name of the setting
     *
     * @return mixed the value of the configuration setting
     *
     * @throws SiteException if the setting being set does not exist in this
     *                       section
     */
    public function __get($name)
    {
        if (!array_key_exists($name, $this->values)) {
            throw new SiteException(
                sprintf(
                    "Can not get configuration setting. Setting '%s' " .
                    "does not exist in the section '%s'.",
                    $name,
                    $this->name
                )
            );
        }

        return $this->values[$name];
    }

    /**
     * Checks for existence of a configuration setting in this section.
     *
     * @param string $name the name of the configuration setting to check
     *
     * @return bool true if the configuration setting exists and false if it
     *              does not
     */
    public function __isset($name)
    {
        return isset($this->values[$name]);
    }
}
