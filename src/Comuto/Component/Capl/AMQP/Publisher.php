<?php

namespace Comuto\Component\Capl\AMQP;

use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Main AMQP Publisher.
 * Used to publish messages into RabbitMQ Exchanges
 */
class Publisher
{
    const DELIVERY_MODE_NON_PERSISTENT = 1;
    const DELIVERY_MODE_PERSISTENT     = 2;
    const EVENT_PRE_PUBLISH            = 'amqppublisher.pre_publish';
    const EVENT_POST_PUBLISH           = 'amqppublisher.post_publish';

    /**
     * @var Exchange
     */
    private $exchange;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection, EventDispatcher $dispatcher = null)
    {
        $this->connection = $connection;
        $this->eventDispatcher = $dispatcher ? $dispatcher : new EventDispatcher;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return \AMQPConnection
     *
     * Lazy connect
     */
    public function getExchange()
    {
        if (!$this->exchange) {
            if (!$this->connection->isConnected()) {
                $this->connection->connect();
            }
            $this->exchange = new Exchange(new \AMQPChannel($this->connection));
        }

        return $this->exchange;
    }

    /**
     * Adds a listener to the only event we dispatch actually
     *
     * @param callable $listener
     * @param string   $event
     * @param int      $priority
     */
    public function addListener($listener, $event = self::EVENT_PRE_PUBLISH, $priority = null)
    {
        $this->eventDispatcher->addListener($event, $listener, $priority);

        return $this;
    }

    /**
     * Publish a message.
     *
     * @param Message\Message $message
     * @param string          $exchangeName
     * @param string          $routingkey
     */
    public function publish(Message\Message $message, $exchangeName, $routingKey = null)
    {
        $this->eventDispatcher->dispatch(self::EVENT_PRE_PUBLISH, new PublishEvent($message));

        $this->getExchange()->setName($exchangeName);
        if (!$routingKey) {
            $routingKey = $exchangeName; /* A convenient default */
        }
        $this->exchange->messagePublish($message, $routingKey);

        $this->eventDispatcher->dispatch(self::EVENT_POST_PUBLISH, new PublishEvent($message));
    }
}
