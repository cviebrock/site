<?php

/**
 * Base class for an application
 *
 * @package   Site
 * @copyright 2004-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteApplication extends SiteObject
{


	const VAR_POST    = 1;
	const VAR_GET     = 2;
	const VAR_REQUEST = 4;
	const VAR_COOKIE  = 8;
	const VAR_SERVER  = 16;
	const VAR_SESSION = 32;
	const VAR_FILES   = 64;
	const VAR_ENV     = 128;




	/**
	 * A unique identifier for this application
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Default time zone
	 *
	 * This time zone may be used to display dates that have no time zone
	 * information.
	 *
	 * Time zones are specified as {@link DateTimeZone} objects and it is
	 * recommended to use the continent/city time zone format. For example,
	 * if this application is based in Halifax, Canada, use 'America/Halifax'
	 * as the time zone.
	 *
	 * If unspecified, the default time zone is set to 'UTC'.
	 *
	 * @var DateTimeZone
	 */
	public $default_time_zone = null;




	/**
	 * Modules of this application
	 *
	 * Application modules are pieces of code that add specific functionality
	 * to an application such as database connectivity, session handling or
	 * configuration.
	 *
	 * This is an associative array of the modules of this application. The
	 * array is of the form 'module identifier' => 'module'.
	 *
	 * @var array
	 *
	 * @see SiteApplication::getDefaultModuleList()
	 * @see SiteApplication::addModule()
	 */
	protected $modules = [];

	/**
	 * Modules of this application indexed by provided features
	 *
	 * This array may contain a single module multiple times if the module
	 * provides multiple features.
	 *
	 * @var array
	 *
	 * @see SiteApplicationModule::provides()
	 */
	protected $modules_by_provides = [];

	/**
	 * The current locale of this application
	 *
	 * This must be a two-letter ISO 639 langauge code followed by an
	 * underscore then followed by a two-letter ISO 3166 country code.
	 * Additional locale specifiers such as character encoding and currency
	 * may optionally follow, separated by a period character. Example of valid
	 * locale strings are:
	 *
	 *  - en_CA
	 *  - fr_FR
	 *  - en_CA.utf8
	 *  - fr_FR.euro
	 *
	 * The following locale strings are used on some systems and are not valid:
	 *
	 *  - Brazillian
	 *  - en
	 *  - C
	 *  - POSIX
	 *
	 * @var string
	 */
	protected $locale;




	/**
	 * Creates a new application
	 *
	 * When this application is created, the default modules are loaded. See
	 * {@link SiteApplication::getDefaultModuleList()}.
	 *
	 * If a configuration filename is specified when this application is
	 * created, a configuration module is created and loaded before the other
	 * application modules are initialized. SiteApplication provides a hook to
	 * configure modules before initModules() is called in
	 * the {@link SiteApplication::configure()} method.
	 *
	 * @param string $id a unique identifier for this application.
	 * @param string $config_filename optional. The filename of the
	 *                                 configuration file. If not specified,
	 *                                 no special configuration is performed.
	 */
	public function __construct($id, $config_filename = null)
	{
		$this->id = $id;

		$this->addDefaultModules();

		$this->default_time_zone = new DateTimeZone('UTC');

		if ($config_filename !== null) {
			try {
				$config_module = $this->getModule('SiteConfigModule');
			} catch (SiteException $e) {
				$config_module = new SiteConfigModule($this);
				$this->addModule($config_module, 'config');
			}

			$this->addConfigDefinitions($config_module);
			$config_module->load($config_filename);
			$this->setUpErrorHandling($config_module);
			$this->configure($config_module);
		}
	}




	/**
	 * Run the application.
	 */
	abstract public function run();




	/**
	 * Gets the current locale of this application
	 *
	 * @return string the locale of this application. This will be a two-letter
	 *                 ISO 639 langauge code followed by an underscore followed
	 *                 by a two-letter ISO 3166 country code. Additional locale
	 *                 specifiers such as character encoding and currency may
	 *                 optionally follow, separated by a period character.
	 */
	public function getLocale()
	{
		return $this->locale;
	}




	/**
	 * Gets the country of this application
	 *
	 * @param string $locale optional. If specified, the country code for the
	 *                        specified locale is returned. Otherwise, the
	 *                        current locale of this application is used.
	 *
	 * @return string the two-letter ISO 3166 country code of this application.
	 */
	public function getCountry($locale = null)
	{
		$country = null;

		if ($locale === null)
			$locale = $this->locale;

		if ($locale !== null)
			$country = mb_substr($locale, 3, 2);

		return $country;
	}




	/**
	 * Helper method to get the current instance
	 *
	 * @return SiteInstance the current instance of this site or null
	 * @see SiteMultipleInstanceModule
	 */
	public function getInstance()
	{
		$instance = null;

		if ($this->hasModule('SiteMultipleInstanceModule')) {
			$module = $this->getModule('SiteMultipleInstanceModule');
			$instance = $module->getInstance();
		}

		return $instance;
	}




	/**
	 * Helper method to get the id of the current instance
	 *
	 * @return integer the current instance id or null if no instance exists.
	 * @see SiteMultipleInstanceModule
	 */
	public function getInstanceId()
	{
		$id = null;
		$instance = $this->getInstance();

		if ($instance !== null) {
			$id = $instance->id;
		}

		return $id;
	}




	/**
	 * Helper method to get the shortname of the current instance
	 *
	 * @return string the current instance shortname or null if no instance
	 *                 exists.
	 *
	 * @see SiteMultipleInstanceModule
	 */
	public function getInstanceShortname()
	{
		$shortname = null;
		$instance = $this->getInstance();

		if ($instance !== null) {
			$shortname = $instance->shortname;
		}

		return $shortname;
	}




	/**
	 * Helper method to get an instance-specifig config setting
	 *
	 * Useful for a case where an instance-specific config setting is accessed
	 * from an no-instance context (like an email that can be used on a
	 * front-end site as well as a multi-instance admin)
	 *
	 * @param string $setting config setting formatted: "section.variable"
	 * @param SiteInstance $instance optional instance
	 * @return mixed The instance config setting
	 * @see SiteMultipleInstanceModule
	 */
	public function getConfigSetting($setting, SiteInstance $instance = null)
	{
		static $config_cache = [];
		$value = null;

		if ($instance instanceof SiteInstance &&
			$this->getInstanceId() != $instance->id) {

			if (!isset($config_cache[$instance->id])) {
				$config_cache[$instance->id] =
					SwatDB::getOptionArray($this->db,
						'InstanceConfigSetting', 'value', 'name', null,
						sprintf('instance = %s',
							$this->db->quote($instance->id, 'integer')));
			}

			if (isset($config_cache[$instance->id][$setting])) {
				$value = $config_cache[$instance->id][$setting];
			}
		}

		if ($value === null) {
			[$section, $variable] = explode('.', $setting);
			$value = $this->config->$section->$variable;
		}

		return $value;
	}




	/**
	 * Configures modules of this application
	 *
	 * This method is run immediately after the configuration has been loaded
	 * from the config file. Developers should add module-specific configuration
	 * here. This method does not have access to overridden configuration
	 * values from the database.
	 *
	 * @param SiteConfigModule $config the config module of this application
	 *                                  to use for configuring the other
	 *                                  modules.
	 *
	 * @see SiteApplication::configure()
	 */
	protected function configure(SiteConfigModule $config)
	{
		$config->configure();
	}




	/**
	 * Configures error and exception handling of this application
	 *
	 * @param SiteConfigModule $config the config module of this application
	 *                                  to use for configuring the other
	 *                                  modules.
	 */
	protected function setUpErrorHandling(SiteConfigModule $config)
	{
		$this->setUpFileErrorHandling($config);
		$this->setUpSentryErrorHandling($config);
	}




	/**
	 * Configures file based error and exception handling of this application
	 *
	 * @param SiteConfigModule $config the config module of this application.
	 */
	protected function setUpFileErrorHandling(SiteConfigModule $config)
	{
		if (isset($config->exceptions->log_location)) {
			SwatException::addLogger(
				new SiteExceptionLogger(
					$config->exceptions->log_location,
					$config->exceptions->base_uri,
					$config->exceptions->unix_group
				)
			);
		}

		if (isset($config->errors->log_location)) {
			SwatError::addLogger(
				new SiteErrorLogger(
					$config->errors->log_location,
					$config->errors->base_uri,
					$config->errors->unix_group
				)
			);
		}

		if (isset($config->errors->fatal_severity)) {
			SwatError::setFatalSeverity($config->errors->fatal_severity);
		}
	}




	/**
	 * Configures sentry based error and exception handling of this application
	 */
	protected function setUpSentryErrorHandling(SiteConfigModule $config): void
	{
		\Sentry\Init($this->getSentryConfiguration($config));

		SwatException::addLogger(new SiteSentryExceptionLogger());
		SwatError::addLogger(new SiteSentryErrorLogger());
	}




	/**
	 * Gets the configuration array used to set up Sentry, which can be
	 * overridden in child application classes.
	 */
	protected function getSentryConfiguration(SiteConfigModule $config): array
	{
		return [
			'dsn' => $config->sentry->dsn,
			'environment' => $config->sentry->environment,
			'default_integrations' => false,
			'release' => $this->getReleaseVersion(),
			'integrations' => [
				new \Sentry\Integration\FatalErrorListenerIntegration()
			]
		];
	}




	/**
	 * Gets the release version of the site for Sentry, based on the
	 * RELEASE_VERSION global constant.
	 */
	protected function getReleaseVersion(): ?string
	{
		return defined('RELEASE_VERSION')
			? constant('RELEASE_VERSION')
			: null;
	}



	// module methods


	/**
	 * Adds a module to this application
	 *
	 * @param SiteApplicationModule $module the module to add to this
	 *                                       application.
	 * @param string $id an identifier for this module.
	 *
	 * @throws SiteException if a module with the given identifier already
	 *                       exists in this application.
	 * @throws SiteException if the module identifier collides with a property
	 *                       of this application.
	 * @throws SiteException if the module depends on a feature that no module
	 *                       in this application provides.
	 * @throws SiteException if the module provides a feature already provided
	 *                       by an existing module in this application.
	 */
	public function addModule(SiteApplicationModule $module, $id)
	{
		// check identifier against other modules
		if (isset($this->modules[$id]))
			throw new SiteException(sprintf(
				"A module with the identifier '%s' already exists in this ".
				"applicaiton.", $id));

		// check identifier against properties
		$properties = get_object_vars($this);
		if (array_key_exists($id, $properties))
			throw new SiteException(sprintf(
				"Invalid module identifier '%s'. Module identifiers must ".
				"not be the same as any of the property names of this ".
				"application object.", $id));

		// check module dependencies
		foreach ($module->depends() as $depend) {
			if (!($depend instanceof SiteApplicationModuleDependency)) {
				throw new SiteException(sprintf(
					'Module %s contains a dependency that is not a '.
					'SiteApplicationModuleDependency', $module::class));
			}

			if ($depend->isRequired() &&
				!isset($this->modules_by_provides[$depend->getFeature()])) {
				throw new SiteException(sprintf(
					"Module %s depends on feature '%s' which is not provided ".
					"by any module in this application.",
					$module::class, $depend->getFeature()));
			}
		}

		// check module against provides list
		foreach ($module->provides() as $provide) {
			if (isset($this->modules_by_provides[$provide])) {
				throw new SiteException(sprintf(
					"Module feature '%s' already provided by %s.",
					$provide,
					$this->modules_by_provides[$provide]::class));
			}
		}

		// add module provides
		foreach ($module->provides() as $provide)
			$this->modules_by_provides[$provide] = $module;

		// add module
		$this->modules[$id] = $module;
	}




	/**
	 * Gets a module of this application by a provided feature
	 *
	 * This method is useful for getting modules of this application without
	 * needing to know the module identifier. For example, you can get a
	 * session module of this application using:
	 * <code>
	 * <?php
	 * $session = $app->getModule('SiteSessionModule');
	 * ?>
	 * </code>
	 * This use is encouraged for module developers. For site-level code, use
	 * the magic get and set methods provided by this application to access
	 * modules by module identifiers.
	 *
	 * @param string $feature the provided feature.
	 *
	 * @return SiteApplicationModule the module of this application that
	 *                                provides the given feature.
	 *
	 * @throws SiteException if no module in this application provides the
	 *                       given feature.
	 */
	public function getModule($feature)
	{
		if (!isset($this->modules_by_provides[$feature]))
			throw new SiteException(sprintf(
				"Application does not have a module that provides '%s'",
				$feature));

		return $this->modules_by_provides[$feature];
	}




	/**
	 * Checks if this application has a module that provides the specified
	 * feature
	 *
	 * This method is useful for checking if an application provides certain
	 * features. For example, you can see if an application supports session
	 * features using:
	 * <code>
	 * <?php
	 * if ($app->hasModule('SiteSessionModule')) {  ... session code ... }
	 * ?>
	 * </code>
	 *
	 * @param string $feature the provided feature.
	 *
	 * @return boolean true if this applicaiton has a module that provides the
	 *                  specified feature and false if it does not.
	 */
	public function hasModule($feature)
	{
		return array_key_exists($feature, $this->modules_by_provides);
	}




	public function __get($name)
	{
		if (isset($this->modules[$name]))
			return $this->modules[$name];

		throw new SiteException('Application does not have a property with '.
			"the name '{$name}', and no application module with the ".
			"identifier '{$name}' is loaded.");
	}




	public function __isset($name)
	{
		$isset = isset($this->$name);
		if (!$isset)
			$isset = isset($this->modules[$name]);

		return $isset;
	}




	/**
	 * Initializes all modules in this application
	 */
	protected function initModules()
	{
		foreach ($this->modules as $module) {
			$module->init();
		}

		$config = $this->getModule('SiteConfigModule');
		$this->postInitConfigure($config);
	}




	/**
	 * Adds the default modules used by this application to this application
	 *
	 * Modules listed in the default module list are automatically added in
	 * their dependent order.
	 *
	 * @see SiteApplication::getDefaultModuleList()
	 *
	 * @throws SiteException if a circular module dependency is detected.
	 * @throws SiteException if a module with the given identifier already
	 *                       exists in this application.
	 * @throws SiteException if the module identifier collides with a property
	 *                       of this application.
	 * @throws SiteException if the module depends on a feature that no module
	 *                       in this application provides.
	 * @throws SiteException if the module provides a feature already provided
	 *                       by an existing module in this application.
	 */
	protected function addDefaultModules()
	{
		$modules = [];
		$module_ids = [];
		$modules_by_provides = [];
		$dependent_stack = [];
		$added_modules = [];

		// instantiate default modules
		foreach ($this->getDefaultModuleList() as $module_id => $module_class) {
			$module = new $module_class($this);
			$modules[] = $module;
			$module_ids[spl_object_hash($module)] = $module_id;
			foreach ($module->provides() as $provide)
				$modules_by_provides[$provide] = $module;
		}

		// add existing modules to array so dependency resolution can use
		// existing modules of this application
		foreach ($this->modules as $module)
			$added_modules[spl_object_hash($module)] = true;

		// add existing provides to array so dependency resolution can use
		// existing modules of this application
		$modules_by_provides += $this->modules_by_provides;

		// add default modules to this application
		foreach ($modules as $module) {
			if (!array_key_exists(spl_object_hash($module), $added_modules)) {
				$this->addDefaultModule($module_ids, $modules_by_provides,
					$added_modules, $module, $dependent_stack);
			}
		}
	}




	/**
	 * Adds a default module to this application
	 *
	 * Default module dependencies of the module are added recursively.
	 *
	 * @param array $module_ids a reference to the array of module identifiers.
	 *                           The array is indexed by the module object
	 *                           hash and the module identifier is the value.
	 * @param array $modules_by_provides a refrerence to the array of modules
	 *                                    available indexed by features the
	 *                                    modules provide.
	 * @param array $added_modules a reference to the array of modules already
	 *                              added to this application. The array is
	 *                              keyed with module object hashes and has
	 *                              true values.
	 * @param SiteApplicationModule $module the module to add to this
	 *                                       application.
	 * @param array $depdendency_stack a reference to the stack of modules
	 *                                  depending on the specified module to
	 *                                  be added. This is used to detect
	 *                                  circular dependencies.
	 *
	 * @throws SiteException if a circular module dependency is detected.
	 * @throws SiteException if a module with the given identifier already
	 *                       exists in this application.
	 * @throws SiteException if the module identifier collides with a property
	 *                       of this application.
	 * @throws SiteException if the module depends on a feature that no module
	 *                       in this application provides.
	 * @throws SiteException if the module provides a feature already provided
	 *                       by an existing module in this application.
	 */
	protected function addDefaultModule(
		array &$module_ids,
		array &$modules_by_provides,
		array &$added_modules,
		SiteApplicationModule $module,
		array &$dependent_stack
	) {
		// check for circular dependency
		if (array_key_exists(spl_object_hash($module), $dependent_stack)) {
			$circular_dependency = '';
			foreach ($dependent_stack as $dependency)
				$circular_dependency.= $dependency::class.' => ';

			$circular_dependency.= $module::class;
			throw new SiteException(sprintf(
				"Circular module dependency detected:\n%s",
				$circular_dependency));
		}

		// module object is value only so we can get nice error messages
		$dependent_stack[spl_object_hash($module)] = $module;

		// add module dependencies
		foreach ($module->depends() as $depend) {
			if (!($depend instanceof SiteApplicationModuleDependency)) {
				throw new SiteException(sprintf(
					'Module %s contains a dependency that is not a '.
					'SiteApplicationModuleDependency', $module::class));
			}

			// if dependency is provided, add it to the application first
			if (isset($modules_by_provides[$depend->getFeature()])) {
				$depend_module = $modules_by_provides[$depend->getFeature()];

				if (!array_key_exists(spl_object_hash($depend_module),
					$added_modules)) {
					$this->addDefaultModule($module_ids, $modules_by_provides,
						$added_modules, $depend_module, $dependent_stack);
				}
			} elseif ($depend->isRequired()) {
				// throw exception if required dependency is missing
				throw new SiteException(sprintf(
					"Module %s depends on '%s' but no module provides this ".
					"feature.",
					$module::class, $depend->getFeature()));
			}
		}

		// all dependencies loaded, pop dependent stack
		array_pop($dependent_stack);

		// add module
		$added_modules[spl_object_hash($module)] = true;
		$this->addModule($module, $module_ids[spl_object_hash($module)]);
	}




	/**
	 * Gets the list of modules to load for this application
	 *
	 * The list of modules is an associative array of the form
	 * 'module identifier' => 'module class name'. After instantiation, loaded
	 * modules are accessible as public, read-only properties of this
	 * application. The public property names correspond directly to the module
	 * identifiers specified in the module list array.
	 *
	 * @return array the default list of modules to load for this application.
	 */
	protected function getDefaultModuleList()
	{
		return [];
	}




	/**
	 * Configures this application
	 *
	 * This method is after modules have all been initialized. All overridden
	 * configuration settings from the database are loaded by this point.
	 */
	protected function postInitConfigure(SiteConfigModule $config)
	{
		$config->postInitConfigure();
	}




	/**
	 * Adds configuration definitions to the config module of this application
	 *
	 * This method runs before the configuration is loaded. Developers should
	 * add configuration definitions for every setting that will be loaded
	 * by the config module.
	 *
	 * Packages may provide a convenient list of configuration definitions
	 * in the static package class.
	 *
	 * @param SiteConfigModule $config the config module of this application to
	 *                                  which to add the config definitions.
	 */
	protected function addConfigDefinitions(SiteConfigModule $config)
	{
		$config->addDefinitions(Site::getConfigDefinitions());
	}



	// caching convenience methods


	/**
	 * Set a value to be cached
	 *
	 * @param mixed   $value
	 * @param string  $key
	 * @param string  $name_space
	 * @param integer $expiration
	 */
	public function addCacheValue(
		$value,
		$key,
		$name_space = null,
		$expiration = 0
	) {
		register_shutdown_function([$this, 'cacheOnShutdown'],
			$value, $key, $name_space, $expiration);
	}




	/**
	 * Get a cached value
	 *
	 * @param string $key
	 * @param string $name_space
	 *
	 * @return mixed false if no cached value is found, otherwise the cached
	 *               value is returned.
	 */
	public function getCacheValue($key, $name_space = null)
	{
		$value = false;

		if ($this->hasModule('SiteMemcacheModule')) {
			$cache = $this->getModule('SiteMemcacheModule');

			if ($name_space === null) {
				$value = $cache->get($key);
			} else {
				$value = $cache->getNs($name_space, $key);
			}
		}

		return $value;
	}




	/**
	 * Deletes a value from the cache by key
	 *
	 * @param string $key
	 * @param string $name_space
	 *
	 * @return boolean true if successful, false if not.
	 */
	public function deleteCacheValue($key, $name_space = null)
	{
		$success = false;

		if ($this->hasModule('SiteMemcacheModule')) {
			$cache = $this->getModule('SiteMemcacheModule');

			if ($name_space === null) {
				$success = $cache->delete($key);
			} else {
				$success = $cache->deleteNs($name_space, $key);
			}
		}

		return $success;
	}




	/**
	 * Flushes an entire namespace
	 *
	 * @param string $name_space
	 *
	 * @return boolean true if successful, false if not.
	 */
	public function flushCacheNs($name_space)
	{
		$success = false;

		if ($this->hasModule('SiteMemcacheModule')) {
			$cache = $this->getModule('SiteMemcacheModule');
			$cache->flushNs($name_space);
			$success = true;
		}

		return $success;
	}




	public function addCacheRecordset(
		SwatDBRecordsetWrapper $recordset,
		$key,
		$name_space = null,
		$index_property = 'id',
		$expiration = 0
	) {
		$ids = [];
		foreach ($recordset as $object) {
			$object_key = $key.'.'.$object->$index_property;
			$ids[] = $object_key;
			$this->addCacheValue($object, $object_key, $name_space,
				$expiration);
		}

		$this->addCacheValue($ids, $key, $name_space, $expiration);
	}




	public function getCacheRecordset($key, $wrapper_class, $name_space = null)
	{
		$indexes = $this->getCacheValue($key, $name_space);
		$wrapper = false;

		if ($indexes !== false) {
			$wrapper = new $wrapper_class();

			if (count($indexes) > 0) {
				$dataobject_array =
					$this->getCacheValue($indexes, $name_space);

				if (count($dataobject_array) !== count($indexes)) {
					// one or more objects are missing from the cache
					$wrapper = false;
				} else {
					foreach ($dataobject_array as $object) {
						$wrapper[] = $object;
					}

					$wrapper->setDatabase($this->db);
				}
			}
		}

		return $wrapper;
	}




	/**
	 * Callback method used by addCacheValue(). Must be public, but should not
	 * be accessed directly.
	 */
	public function cacheOnShutdown(
		$value,
		$key = null,
		$name_space = null,
		$expiration = 0
	) {
		if ($this->hasModule('SiteMemcacheModule')) {
			$cache = $this->getModule('SiteMemcacheModule');

			if ($name_space === null) {
				$cache->set($key, $value, $expiration);
			} else {
				$cache->setNs($name_space, $key, $value, $expiration);
			}
		}
	}



	// static convenience methods


	/**
	 * Initializes a variable
	 *
	 * Static convenience method to initialize a local variable with a value
	 * from one of the PHP global arrays.
	 *
	 * @param string $name the name of the variable to lookup.
	 * @param mixed $default the value to return if variable is not found in
	 *                        the super-global arrays. Defaults to null.
	 * @param integer $types a bitwise combination of self::VAR_*
	 *                        constants. Defaults to
	 *                        {@link SiteApplication::VAR_POST} |
	 *                        {@link SiteApplication::VAR_GET}.
	 *
	 * @return mixed the value of the variable.
	 */
	public static function initVar($name, $default = null, $types = 0)
	{
		$var = $default;

		if ($types == 0)
			$types = self::VAR_POST | self::VAR_GET;

		if (($types & self::VAR_POST) != 0 &&
			array_key_exists($name, $_POST)) {
				$var = $_POST[$name];

		} elseif (($types & self::VAR_GET) != 0 &&
			array_key_exists($name, $_GET)) {
				$var = $_GET[$name];

		} elseif (($types & self::VAR_REQUEST) != 0 &&
			array_key_exists($name, $_REQUEST)) {
				$var = $_REQUEST[$name];

		} elseif (($types & self::VAR_COOKIE) != 0 &&
			array_key_exists($name, $_COOKIE)) {
				$var = $_COOKIE[$name];

		} elseif (($types & self::VAR_SERVER) != 0 &&
			array_key_exists($name, $_SERVER)) {
				$var = $_SERVER[$name];

		} elseif (($types & self::VAR_SESSION) != 0	&&
			array_key_exists($name, $_SESSION)) {
				$var = $_SESSION[$name];

		} elseif (($types & self::VAR_FILES) != 0 &&
			array_key_exists($name, $_FILES)) {
				$var = $_FILES[$name];

		} elseif (($types & self::VAR_ENV) != 0) {
			// Use getenv() instead of $_ENV so we can load environment
			// variables that were set after the script started running.
			$value = getenv($name);
			if ($value !== false)
				$var = $value;
		}

		return $var;
	}


}

?>
