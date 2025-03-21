<?php

/**
 * Container for package wide static methods.
 *
 * @copyright 2005-2025 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Site
{
    /**
     * The gettext domain for Site.
     *
     * This is used to support multiple locales.
     */
    public const GETTEXT_DOMAIN = 'site';

    /**
     * Whether or not this package is initialized.
     *
     * @var bool
     */
    private static $is_initialized = false;

    /**
     * Prevent instantiation of this static class.
     */
    private function __construct() {}

    /**
     * Translates a phrase.
     *
     * This is an alias for {@link self::gettext()}.
     *
     * @param string $message the phrase to be translated
     *
     * @return string the translated phrase
     */
    public static function _($message)
    {
        return self::gettext($message);
    }

    /**
     * Translates a phrase.
     *
     * This method relies on the php gettext extension and uses dgettext()
     * internally.
     *
     * @param string $message the phrase to be translated
     *
     * @return string the translated phrase
     */
    public static function gettext($message)
    {
        return dgettext(self::GETTEXT_DOMAIN, $message);
    }

    /**
     * Translates a plural phrase.
     *
     * This method should be used when a phrase depends on a number. For
     * example, use ngettext when translating a dynamic phrase like:
     *
     * - "There is 1 new item" for 1 item and
     * - "There are 2 new items" for 2 or more items.
     *
     * This method relies on the php gettext extension and uses dngettext()
     * internally.
     *
     * @param string $singular_message the message to use when the number the
     *                                 phrase depends on is one
     * @param string $plural_message   the message to use when the number the
     *                                 phrase depends on is more than one
     * @param int    $number           the number the phrase depends on
     *
     * @return string the translated phrase
     */
    public static function ngettext($singular_message, $plural_message, $number)
    {
        return dngettext(
            self::GETTEXT_DOMAIN,
            $singular_message,
            $plural_message,
            $number
        );
    }

    public static function setupGettext()
    {
        $path = '@DATA-DIR@/Site/locale';
        if (mb_substr($path, 0, 1) === '@') {
            $path = __DIR__ . '/../locale';
        }

        bindtextdomain(self::GETTEXT_DOMAIN, $path);
        bind_textdomain_codeset(self::GETTEXT_DOMAIN, 'UTF-8');
    }

    public static function generateRandomHash(): string
    {
        return sha1(uniqid(random_int(0, mt_getrandmax()), true));
    }

    /**
     * Displays the methods of an object.
     *
     * This is useful for debugging.
     *
     * @param mixed $object the object whose methods are to be displayed
     */
    public static function displayMethods($object)
    {
        echo sprintf(self::_('Methods for class %s:'), $object::class);
        echo '<ul>';

        foreach (get_class_methods($object::class) as $method_name) {
            echo '<li>', $method_name, '</li>';
        }

        echo '</ul>';
    }

    /**
     * Displays the properties of an object.
     *
     * This is useful for debugging.
     *
     * @param mixed $object the object whose properties are to be displayed
     */
    public static function displayProperties($object)
    {
        $class = $object::class;

        echo sprintf(self::_('Properties for class %s:'), $class);
        echo '<ul>';

        foreach (get_class_vars($class) as $property_name => $value) {
            $instance_value = $object->{$property_name};
            echo '<li>', $property_name, ' = ', $instance_value, '</li>';
        }

        echo '</ul>';
    }

    /**
     * Gets configuration definitions used by the Site package.
     *
     * Applications should add these definitions to their config module before
     * loading the application configuration.
     *
     * @return array the configuration definitions used by the Site package
     *
     * @see SiteConfigModule::addDefinitions()
     */
    public static function getConfigDefinitions()
    {
        return [
            // Accounts
            // How long a persistent login cookie will exist in seconds.
            // Default value is 28 days.
            'account.persistent_login_time' => 2419200,
            // Whether or not persistent logins are enabled.
            'account.persistent_login_enabled' => false,
            // Whether or not to set a cookie containing the account id
            // for displaying a restore session message (i.e Welcome back
            // Joe. _Login_ to restore your 15 cart items.)
            'account.restore_cookie_enabled' => false,
            // Meta description for HTML head
            'site.meta_description' => null,
            // Title of the site
            'site.title' => null,
            // Resource tag, used for uncaching html-head-entries. Deprecated
            // in favor of 'resources.tag'.
            'site.resource_tag' => null,
            // Shortname for the site
            'site.shortname' => null,
            // Resource tag, used for uncaching html-head-entries.
            'resources.tag' => null,
            // Whether or not to combine resources
            'resources.combine' => false,
            // Whether or not to minify resources
            'resources.minify' => false,
            // DSN of database
            'database.dsn' => null,
            // Default locale (defaults to 'en_CA.UTF8')
            'i18n.locale' => 'en_CA.UTF8',
            // Default timezone
            'date.time_zone' => null,
            // Salts
            'swat.form_salt' => null,
            'cookies.salt'   => null,
            // URIs
            'uri.base'          => null,
            'uri.absolute_base' => null,
            'uri.cdn_base'      => null,
            'uri.admin_base'    => null,
            'uri.account_login' => 'account/login',
            // Exceptions & errors
            'exceptions.log_location' => null,
            'exceptions.base_uri'     => null,
            'exceptions.unix_group'   => null,
            'errors.log_location'     => null,
            'errors.base_uri'         => null,
            'errors.unix_group'       => null,
            'errors.fatal_severity'   => null,
            // Analytics
            // Google analytics website property id (UA-XXXXX-XXX)
            'analytics.enabled'         => true,
            'analytics.google_account'  => null,
            'analytics.google4_account' => null,
            // Google analytics account id (XXXXXXXX)
            'analytics.google_account_id'                => null,
            'analytics.google_enhanced_link_attribution' => true,
            'analytics.google_display_advertising'       => false,
            // Facebook Pixel id
            'analytics.facebook_pixel_id' => null,
            // Twitter Pixel ids
            'analytics.twitter_track_pixel_id'    => null,
            'analytics.twitter_purchase_pixel_id' => null,
            // Bing Universal Event Tracker id
            'analytics.bing_uet_id' => null,
            // Ads
            // Tracking id in URIs
            'ads.tracking_id'  => 'utm_source',
            'ads.save_referer' => '1',
            // Session
            'session.name' => 'sessionid',
            'session.path' => null,
            // Instance
            'instance.default' => null,
            // Email
            'email.smtp_server'   => null,
            'email.smtp_port'     => null,
            'email.smtp_username' => null,
            'email.smtp_password' => null,
            'email.log'           => true,
            // to address for test emails
            'email.test_address' => null,
            // to address for contact-us emails
            'email.contact_address' => null,
            // CC and BCC lists for contact-us emails
            // addresses are delimited by ; characters
            'email.contact_cc_list'  => null,
            'email.contact_bcc_list' => null,
            // from address for contact-us emails (from "the website" to client)
            'email.website_address' => null,
            // from address for automated emails sent by the system
            'email.service_address' => null,
            // memcache
            'memcache.enabled'            => true,
            'memcache.server'             => 'localhost',
            'memcache.app_ns'             => '',
            'memcache.page_cache'         => false,
            'memcache.page_cache_timeout' => 900,
            // in seconds
            'memcache.resource_cache'      => true,
            'memcache.resource_cache_stat' => true,
            // amazon AWS
            // @see https://docs.aws.amazon.com/general/latest/gr/rande.html
            'amazon.region' => 'us-east-1',
            // amazon S3
            'amazon.bucket'             => null,
            'amazon.access_key_id'      => null,
            'amazon.access_key_secret'  => null,
            'amazon.reduced_redundancy' => false,
            // amazon Cloudfront
            'amazon.cloudfront_enabled'                  => false,
            'amazon.distribution'                        => null,
            'amazon.streaming_distribution'              => null,
            'amazon.streaming_distribution_port'         => null,
            'amazon.private_distribution'                => null,
            'amazon.private_streaming_distribution'      => null,
            'amazon.private_streaming_distribution_port' => null,
            'amazon.distribution_key_pair_id'            => null,
            'amazon.distribution_private_key_file'       => 'cloud_front_private_key.pem',
            // mobile
            'mobile.auto_relocate' => false,
            // media
            'media.days_to_delete_threshold' => 7,
            // in days
            // JWPlayer
            'jwplayer.key' => null,
            // Expiry dates for the privateer data deleter
            'expiry.contact_messages' => '1 year',
            // P3P headers. See https://en.wikipedia.org/wiki/P3P
            'p3p.compact_policy' => null,
            'p3p.policy_uri'     => null,
            // Redis server. Specified as host[:port]
            'redis.server' => null,
            // Redis database. An integer.
            'redis.database' => 0,
            // Prefix to use for Redis keys in this application.
            'redis.prefix' => null,
            // Location of AMQP server in the form host[:port].
            'amqp.server' => null,
            // default namespace for AMQP exchanges and queues for this
            // application.
            'amqp.default_namespace' => '',
            // how long in milliseconds to wait for a synchronous response from
            // the AMQP job processor.
            'amqp.sync_timeout' => 2000,
            // The crypt supported password hashing method and rounds to use.
            // Use blowfish and 10 rounds (~0.088s with 2 x Intel Xeon E5345)
            // as sane defaults.
            'crypt.method' => 'blowfish',
            'crypt.rounds' => '10',
            // Olark
            'olark.site_id' => null,
            // Mandrill
            'mandrill.api_key'         => null,
            'mandrill.template_prefix' => null,
            // Sentry error logging
            'sentry.dsn'         => null,
            'sentry.environment' => null,
            // SwatDB enum mapping
            'swatdb.enum_mapping' => [],
        ];
    }

    public static function init()
    {
        if (self::$is_initialized) {
            return;
        }

        Swat::init();

        self::setupGettext();

        SwatUI::mapClassPrefixToPath('Site', 'Site');

        // Setup custom exception and error handlers.
        SiteError::setupHandler();
        SiteException::setupHandler();

        self::$is_initialized = true;
    }
}
