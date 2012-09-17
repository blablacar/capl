<?php

namespace Comuto\Component\Capl\Worker;

use Comuto\Component\Capl\AMQP\Message\Message;
use Comuto\Component\Capl\MessageProcessorInterface;
use Comuto\Component\Capl\AMQP\Queue;
use Comuto\Component\Capl\AMQP\Connection;

class AmqpStrategy implements WorkerStrategyInterface
{
    private $queue;
    private $messageProcessor;
    private $lastEnvelope;

    public function __construct(array $amqpConfig, $queueName)
    {
        if (!extension_loaded('amqp')) {
            throw new \RunTimeException("PHP extension 'amqp' is required");
        }

        $con = new Connection($amqpConfig);

        if (!$con->isConnected()) {
            $con->connect();
        }

        $this->queue = new Queue(new \AMQPChannel($con), $queueName);
    }

    public function extractMessage(MessageProcessorInterface $processor)
    {
        $this->messageProcessor = $processor;
        /* Blocking callback until false is returned
           if false has been returned, then this basicaly will abort the process (see CaplCommand) */

        return $this->queue->consume(array($this, 'extractMessageFromAMQP'), AMQP_NOPARAM); /* no AMQP_AUTOACK */
    }

    /* This is the AMQP callback */
    public function extractMessageFromAMQP(\AMQPEnvelope $env, \AMQPQueue $q)
    {
        $this->lastEnvelope = $env;
        $message = @unserialize($env->getBody());
        if (! is_object($message)) {
            /* Since ext/amqp 1.0.5, exceptions may be thrown from the callback
             * though we haven't patched that yet */
            @trigger_error(sprintf('Given message should be an object, "%s" given.', gettype($message)), E_USER_WARNING);
            /* Return from the callback, this will return to extractMessage() */

            return false;
        }

        if (! $message instanceof Message) {
            /* Since ext/amqp 1.0.5, exceptions may be thrown from the callback
             * though we haven't patched that yet */
            @trigger_error(sprintf('Unexpected message, should be a Capl\AMQP\Message\Message, "%s" given', get_class($message)), E_USER_WARNING);
            /* Return from the callback, this will return to extractMessage() */

            return false;
        }

        $message->setAMQPEnvelope($env);
        $this->messageProcessor->processMessage($message);
    }

    /**
     * ACKes a message.
     */
    public function ack()
    {
        $this->queue->ack($this->lastEnvelope->getDeliveryTag());
    }

    /**
     * NACKes a message.
     *
     * @param bool $requeue Weither to requeue the message or not
     */
    public function nack($requeue = false)
    {
        $this->queue->reject($this->lastEnvelope->getDeliveryTag(), $requeue ? AMQP_REQUEUE : null);
    }
}
