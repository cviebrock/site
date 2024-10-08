<?php

/**
 * Web application module for sending messages to an AMQP broker.
 *
 * This uses pecl-amqp and works with AMQP 0-9-1, which is supported by
 * RabbitMQ.
 *
 * @copyright 2013-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteAMQPModule extends SiteApplicationModule
{
    /**
     * List of exchanges to which messages are published.
     *
     * This is indexed by keys of the form "namespace.exchange".
     *
     * @var array
     *
     * @see SiteAMQPModule::getExchange()
     */
    protected $exchanges = [];

    /**
     * Connection to the AMQP broker.
     *
     * @var AMQPConnection
     *
     * @see SiteAMQPModule::connect()
     */
    protected $connection;

    /**
     * Channel to the AMQP broker.
     *
     * @var AMQPChannel
     *
     * @see SiteAMQPModule::connect()
     */
    protected $channel;

    /**
     * Default AMQP namespace.
     *
     * @var string
     */
    protected $default_namespace;

    /**
     * Initializes this module, pulling values from the application
     * configuration.
     *
     * @throws SiteException if the AMQP extension is not available
     */
    public function init()
    {
        if (!extension_loaded('amqp')) {
            throw new SiteException(
                'The PHP AMQP extension is required for the SiteAMQPModule.'
            );
        }

        $config = $this->app->getModule('SiteConfigModule');
        $this->default_namespace = $config->amqp->default_namespace;
    }

    /**
     * Gets the module features this module depends on.
     *
     * The AMQP module depends on the SiteConfigModule feature.
     *
     * @return array an array of {@link SiteApplicationModuleDependency}
     *               objects defining the features this module
     *               depends on
     */
    public function depends()
    {
        $depends = parent::depends();
        $depends[] = new SiteApplicationModuleDependency('SiteConfigModule');

        return $depends;
    }

    /**
     * Does an asynchronous job.
     *
     * This implements background processing using AMQP. It allows offloading
     * processor intensive jobs where the results are not needed in the
     * current thread.
     *
     * @param string $namespace  the job namespace
     * @param string $exchange   the job exchange name
     * @param string $message    the job data
     * @param array  $attributes optional. Additional message attributes. See
     *                           {@link https://github.com/pdezwart/php-amqp/blob/master/stubs/AMQPExchange.php#L155}
     */
    public function doAsyncNs(
        $namespace,
        $exchange,
        $message,
        array $attributes = []
    ) {
        // always persist messages
        $attributes = array_merge(
            $attributes,
            ['delivery_mode' => AMQP_DURABLE]
        );

        $this->connect();
        $this->getExchange($namespace, $exchange)->publish(
            (string) $message,
            '',
            AMQP_NOPARAM,
            $attributes
        );
    }

    /**
     * Does an asynchronous job in the default application namespace.
     *
     * This implements background processing using AMQP. It allows offloading
     * processor intensive jobs where the results are not needed in the
     * current thread.
     *
     * @param string $exchange   the job exchange name
     * @param string $message    the job data
     * @param array  $attributes optional. Additional message attributes. See
     *                           {@link https://github.com/pdezwart/php-amqp/blob/master/stubs/AMQPExchange.php#L155}
     */
    public function doAsync($exchange, $message, array $attributes = [])
    {
        return $this->doAsyncNs(
            $this->default_namespace,
            $exchange,
            $message,
            $attributes
        );
    }

    /**
     * Does a synchronous job and returns the result data.
     *
     * This implements RPC using AMQP. It allows offloading and distributing
     * processor intensive jobs that must be performed synchronously.
     *
     * @param string $namespace  the job namespace
     * @param string $exchange   the job exchange name
     * @param string $message    the job data
     * @param array  $attributes optional. Additional message attributes. See
     *                           {@link https://github.com/pdezwart/php-amqp/blob/master/stubs/AMQPExchange.php#L155}
     *
     * @return array an array containing the following fields:
     *               - <kbd>status</kbd>   - a string containing "success" for
     *               a successful message receipt.
     *               Failure responses will throw an
     *               exception rather than return a
     *               status here.
     *               - <kbd>body</kbd>     - a string containing the response
     *               value returned by the worker.
     *               Depending on the application, this
     *               may or may not be JSON encoded.
     *               - <kbd>raw_body</kbd> - a string containing the raw
     *               message response from the AMQP
     *               envelope. This will typically be
     *               JSON encoded.
     *
     * @throws SiteAMQPJobFailureException if the job processor can't process
     *                                     the job
     */
    public function doSyncNs(
        $namespace,
        $exchange,
        $message,
        array $attributes = []
    ) {
        $this->connect();

        $correlation_id = uniqid(true);

        $reply_queue = new AMQPQueue($this->getChannel());
        $reply_queue->setFlags(AMQP_EXCLUSIVE);
        $reply_queue->declareQueue();

        $attributes = array_merge(
            $attributes,
            ['correlation_id' => $correlation_id, 'reply_to' => $reply_queue->getName(), 'delivery_mode' => AMQP_DURABLE]
        );

        $this->getExchange($namespace, $exchange)->publish(
            (string) $message,
            '',
            AMQP_MANDATORY,
            $attributes
        );

        $response = null;

        // Callback to handle receiving the response on the reply queue. This
        // callback function must return true or false in order to be handled
        // correctly by the AMQP extension. If an exception is thrown in this
        // callback, behavior is undefined.
        $callback = function (
            AMQPEnvelope $envelope,
            AMQPQueue $queue
        ) use (
            &$response,
            $correlation_id
        ) {
            // Make sure we get the reply message we are looking for. This handles
            // possible race conditinos on the queue broker.
            if ($envelope->getCorrelationId() === $correlation_id) {
                $raw_body = $envelope->getBody();

                // Parse the response. If the response can not be parsed, create a
                // failure response value.
                $response = json_decode($raw_body, true);
                if ($response === null
                    || !isset($response['status'])) {
                    $response = ['status' => 'fail', 'raw_body' => $raw_body, 'body' => 'AMQP job response data is in an unknown format.'];
                } else {
                    $response['raw_body'] = $raw_body;
                }

                // Ack the message so it is removed from the reply queue.
                $queue->ack($envelope->getDeliveryTag());

                // resume execution
                return false;
            }

            // get next message
            return true;
        };

        try {
            // This will block until a response is received.
            $reply_queue->consume($callback);

            // Delete the reply queue once the reply is successfully received.
            // For long-running services we don't want used reply queues
            // to remain on the queue broker.
            $reply_queue->delete();

            // Check for failure response and throw an exception
            if ($response['status'] === 'fail') {
                throw new SiteAMQPJobFailureException(
                    $response['body'],
                    0,
                    $response['raw_body']
                );
            }
        } catch (AMQPConnectionException $e) {
            // Always delete the queue before throwing an exception. For long-
            // running services we don't want stale reply queues to remain on
            // the queue broker.
            $reply_queue->delete();

            // Ignore timeout exceptions, rethrow other exceptions.
            if ($e->getMessage() !== 'Resource temporarily unavailable') {
                throw $e;
            }
        }

        // read timeout occurred
        if ($response === null) {
            throw new SiteAMQPJobFailureException(
                'Did not receive response from AMQP job processor before ' .
                'timeout.'
            );
        }

        return $response;
    }

    /**
     * Does a synchronous job in the default application namespace and returns
     * the result data.
     *
     * This implements RPC using AMQP. It allows offloading and distrubuting
     * processor intensive jobs that must be performed synchronously.
     *
     * @param string $exchange   the job exchange name
     * @param string $message    the job data
     * @param array  $attributes optional. Additional message attributes. See
     *                           {@link https://github.com/pdezwart/php-amqp/blob/master/stubs/AMQPExchange.php#L155}
     *
     * @return array an array containing the following fields:
     *               - <kbd>status</kbd>   - a string containing "success" for
     *               a successful message receipt.
     *               Failure responses will throw an
     *               exception rather than return a
     *               status here.
     *               - <kbd>body</kbd>     - a string containing the response
     *               value returned by the worker.
     *               Depending on the application, this
     *               may or may not be JSON encoded.
     *               - <kbd>raw_body</kbd> - a string containing the raw
     *               message response from the AMQP
     *               envelope. This will typically be
     *               JSON encoded.
     *
     * @throws SiteAMQPJobFailureException if the job processor can't process
     *                                     the job
     */
    public function doSync($exchange, $message, array $attributes = [])
    {
        return $this->doSyncNs(
            $this->default_namespace,
            $exchange,
            $message,
            $attributes
        );
    }

    /**
     * Lazily creates an AMQP connection and opens a channel on the connection.
     */
    protected function connect()
    {
        $config = $this->app->getModule('SiteConfigModule')->amqp;
        if ($this->connection === null && $config->server != '') {
            $server_parts = explode(':', trim($config->server), 2);
            $host = $server_parts[0];
            $port = (count($server_parts) === 2) ? $server_parts[1] : 5672;

            $this->connection = new AMQPConnection();
            $this->connection->setReadTimeout($config->sync_timeout / 1000);
            $this->connection->setHost($host);
            $this->connection->setPort($port);
            $this->connection->connect();
        }
    }

    /**
     * Gets the current connected channel.
     *
     * If the channel disconnects because of an error, a new channel is connected
     * automatically.
     *
     * @return AMQPChannel the current connected channel or null if the module
     *                     is not connected to a broker
     */
    protected function getChannel()
    {
        if ($this->channel instanceof AMQPChannel) {
            if (!$this->channel->isConnected()) {
                $this->channel = new AMQPChannel($this->connection);

                // Clear exchange cache. The exchanges will be reconnected
                // on-demand using the new channel.
                $this->exchanges = [];
            }
        } elseif ($this->connection instanceof AMQPConnection) {
            // Create initial channel.
            $this->channel = new AMQPChannel($this->connection);
        }

        return $this->channel;
    }

    /**
     * Gets an exchange given a namespace and exchange name.
     *
     * If the exchange doesn't exist on the AMQP broker it is declared.
     *
     * @param string $namespace the exchange namespace
     * @param string $name      the exchange name
     *
     * @return AMQPExchange the exchange
     */
    protected function getExchange($namespace, $name)
    {
        $key = $namespace . '.' . $name;
        if (!isset($this->exchanges[$key])) {
            $exchange = new AMQPExchange($this->getChannel());
            $exchange->setName($key);
            $exchange->setType(AMQP_EX_TYPE_DIRECT);
            $exchange->setFlags(AMQP_DURABLE);
            $exchange->declare();

            $queue = new AMQPQueue($this->getChannel());
            $queue->setName($key);
            $queue->setFlags(AMQP_DURABLE);
            $queue->declare();
            $queue->bind($key);

            $this->exchanges[$key] = $exchange;
        }

        return $this->exchanges[$key];
    }
}
