<?php

namespace Comuto\Component\Capl\AMQP;

/**
 * AMQP Queue.
 *
 * Our queues are bound to an exchange with the same name and a routing key
 * with the same name.
 *
 *          <Foo>
 * Ex:Foo ---------> Qu:Foo
 */
class Queue extends \AMQPQueue
{
    /**
     * @param \AMQPChannel $channel
     * @param string       $name
     */
    public function __construct(\AMQPChannel $channel, $name)
    {
        parent::__construct($channel);
        $this->setName($name);
    }
}
