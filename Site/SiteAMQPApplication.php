<?php

use Psr\Log\LoggerInterface;
use Psr\Log\LoggingInterface;

/**
 * Application that does a AMQP task.
 *
 * Example:
 * <code>
 * <?php
 *
 * $parser = SiteAMQPCommandLine::fromXMLFile('my-cli.xml');
 * $logger = new SiteCommandLineLogger($parser);
 * $app    = new MyAMQPApplication('my-task', $parser, $logger);
 *
 * $app();
 *
 * ?>
 * </code>
 *
 * @copyright 2013-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteAMQPApplication extends SiteApplication
{
    /**
     * How long to wait when the queue is empty before checking again.
     *
     * In milliseconds.
     */
    public const WORK_LOOP_TIMEOUT = 100;

    /**
     * The AMQP queue name of this application.
     *
     * @var string
     */
    protected $queue = '';

    /**
     * The command-line context of this application.
     *
     * @var Console_CommandLine
     */
    protected $parser;

    /**
     * The logging interface of this application.
     *
     * @var LoggingInterface
     */
    protected $logger;

    /**
     * @var AMQPExchange
     */
    protected $exchange;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * Creates a new AMQP application.
     *
     * @param string              $queue  the AQMP queue name
     * @param Console_CommandLine $parser the commane-line context
     * @param LoggerInterface     $logger the logging interface
     * @param string              $config optional. The filename of the
     *                                    configuration file. If not
     *                                    specified, no special
     *                                    configuration is performed.
     */
    public function __construct(
        $queue,
        Console_CommandLine $parser,
        LoggerInterface $logger,
        $config = null
    ) {
        parent::__construct('aqmp-' . $queue, $config);

        $this->queue = $queue;
        $this->logger = $logger;
        $this->parser = $parser;
    }

    /**
     * Runs this application.
     */
    public function __invoke()
    {
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, $this->handleSignal(...));
        }

        $this->initModules();

        try {
            $this->cli = $this->parser->parse();
            $this->logger->setLevel($this->cli->options['verbose']);
            $this->init();

            $connection = new AMQPConnection();
            $connection->setHost($this->cli->args['address']);
            $connection->setPort($this->cli->options['port']);

            // re-connection loop if AMQP server goes away
            while (true) {
                try {
                    $this->logger->debug(
                        Site::_('Connecting worker to AMQP server {address}:{port} ... '),
                        ['address' => $this->cli->args['address'], 'port' => $this->cli->options['port']]
                    );
                    $connection->connect();
                    $this->channel = new AMQPChannel($connection);
                    $this->exchange = new AMQPExchange($this->channel);
                    $this->logger->debug(Site::_('done') . PHP_EOL);

                    $this->work();
                } catch (AMQPConnectionException $e) {
                    $this->logger->debug(Site::_('connection error') . PHP_EOL);

                    if ($e->getMessage() ===
                        'Socket error: could not connect to host.') {
                        $this->logger->error(
                            'Could not connect to AMQP server on host ' .
                            '{host}.' . PHP_EOL,
                            ['host' => $this->cli->args['address']]
                        );
                    } else {
                        $this->logger->error($e->getMessage() . PHP_EOL);
                    }

                    sleep(10);
                }
            }
        } catch (Console_CommandLine_Exception $e) {
            $this->logger->error($e->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    /**
     * Runs this application.
     *
     * Interface required by SiteApplication.
     */
    public function run()
    {
        $this();
    }

    /**
     * Handles signals sent to this process.
     *
     * @param int $signal the sinal that was received (e.g. SIGTERM).
     */
    public function handleSignal($signal)
    {
        switch ($signal) {
            case SIGTERM:
                $this->handleSigTerm();
                break;
        }
    }

    /**
     * Completes a job.
     *
     * Subclasses must implement this method to perform work.
     */
    abstract protected function doWork(SiteAMQPJob $job);

    /**
     * Performs any initilization of this application.
     *
     * Subclasses should extend this method to add any required start-up
     * initialization.
     */
    protected function init() {}

    /**
     * Enters this application into the work-listen loop.
     */
    protected function work()
    {
        // Get namespaced queue name if a default_namespace is set in the
        // application config. This allows global workers to have no namespace.
        if ($this->config->amqp->default_namespace != '') {
            $queue_name = $this->config->amqp->default_namespace .
                '.' . $this->queue;
        } else {
            $queue_name = $this->queue;
        }

        $queue = new AMQPQueue($this->channel);
        $queue->setName($queue_name);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();

        $this->logger->debug(
            '=== ' . Site::_('Ready for work.') . ' ===' .
            PHP_EOL . PHP_EOL
        );

        while (true) {
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            if ($this->canWork()) {
                $envelope = $queue->get();
                if ($envelope === false) {
                    usleep(self::WORK_LOOP_TIMEOUT * 1000);
                    $this->logger->debug(
                        '=: ' . Site::_('work loop timeout') . PHP_EOL
                    );
                } else {
                    $this->doWork(
                        new SiteAMQPJob(
                            $this->exchange,
                            $envelope,
                            $queue
                        )
                    );
                }
            }
        }
    }

    /**
     * Provides a place for subclasses to add application-specific timeouts.
     *
     * For example, if a database server or another service goes away this
     * can be used to wait for it to return before continuing to do work.
     *
     * If work can not be done, the subclass should take responsibility for
     * adding a sleep() or wait() call in the canWork() method so as not to
     * overwhelm the processor.
     *
     * @return bool true if work can be done and false if not
     */
    protected function canWork()
    {
        return true;
    }

    /**
     * Provides a safe shutdown function.
     *
     * Jobs are atomic. When this worker is cleanly stopped via a monitoring
     * script sending SIGTERM it will not be in the middle of a job.
     *
     * Subclasses must call exit() or parent::handleSigTerm() to ensure
     * the process ends.
     */
    protected function handleSigTerm()
    {
        $this->logger->info(Site::_('Got SIGTERM, shutting down.' . PHP_EOL));
        exit;
    }
}
