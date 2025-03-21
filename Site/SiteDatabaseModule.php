<?php

/**
 * Application module for database connectivity.
 *
 * During the initialization of this module, a convenience property named 'db'
 * is set on the application containing this module. After initialization,
 * you may make database calls using <code>$app->db</code> instead of
 * <code>$app->database->getConnection()</code>.
 *
 * @copyright 2005-2025 silverorange
 */
class SiteDatabaseModule extends SiteApplicationModule
{
    /**
     * DSN of database.
     *
     * A DSN string specifying the database to connect to. Set this before
     * calling {@link SiteApplication::init()}. After calling init(), consider
     * this property read-only.
     *
     * @var string
     */
    public $dsn;

    /**
     * The database connection object.
     *
     * @var MDB2_Driver_Common
     *
     * @see SiteDatabaseModule::getConnection()
     */
    protected $connection;

    public function init()
    {
        $this->connection = SwatDB::connect($this->dsn);

        if (MDB2::isError($this->connection)) {
            throw new SwatDBException($this->connection);
        }

        $this->connection->options['portability'] =
            $this->connection->options['portability'] ^
                MDB2_PORTABILITY_EMPTY_TO_NULL;

        $this->setupEnumMapping();

        // Set up convenience reference
        $this->app->db = $this->getConnection();
    }

    /**
     * Retrieves the MDB2 connection object.
     *
     * @return MDB2_Driver_Common the MDB2 connection object of this module
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Configures any enum mapping for SwatDB objects.
     */
    protected function setupEnumMapping()
    {
        if (!$this->app->hasModule('SiteConfigModule')) {
            return;
        }

        $config = $this->app->getModule('SiteConfigModule');
        $enum_mapping = $config->swatdb->enum_mapping ?? [];

        if (count($enum_mapping) === 0) {
            return;
        }

        SwatDBEnumMapper::initialize($this->connection, $enum_mapping);
    }
}
