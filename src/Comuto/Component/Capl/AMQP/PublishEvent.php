<?php

namespace Comuto\Component\Capl\AMQP;

use Comuto\Component\Capl\AMQP\Message\Message;
use Symfony\Component\EventDispatcher\Event;

/**
 * Publish Event for the EventDispatcher
 */
class PublishEvent extends Event
{
    /**
     * @var AbstractMessage
     */
    private $amqpMessage;

    /**
     * @param Message $message
     */
    public function __construct(Message $message)
    {
        $this->amqpMessage = $message;
    }

    /**
     * @return AbstractMessage
     */
    public function getAmqpMessage()
    {
        return $this->amqpMessage;
    }
}
