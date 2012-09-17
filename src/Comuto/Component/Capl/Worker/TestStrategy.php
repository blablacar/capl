<?php

namespace Comuto\Component\Capl\Worker;

use Comuto\Component\Capl\Tests\Stub\ExampleAMQPEnvelope;
use Comuto\Component\Capl\Tests\Stub\ExamplePayload;
use Comuto\Component\Capl\MessageProcessorInterface;
use Comuto\Component\Capl\AMQP\Message\Message;

class TestStrategy implements WorkerStrategyInterface
{
    public function __construct(array $amqpConfig, $queueName)
    {

    }

    public function extractMessage(MessageProcessorInterface $messageProcessor)
    {
        $message = new Message(new ExamplePayload, 'fooprocessor', 'blablacar', 'web');
        $message->setAMQPEnvelope(new ExampleAMQPEnvelope);
        $messageProcessor->processMessage($message);
    }

    public function ack()
    {

    }

    public function nack($requeue)
    {

    }
}
