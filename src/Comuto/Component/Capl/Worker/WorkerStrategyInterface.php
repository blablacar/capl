<?php

namespace Comuto\Component\Capl\Worker;

use Comuto\Component\Capl\MessageProcessorInterface;

interface WorkerStrategyInterface
{
    function __construct(array $amqpConfig, $queueName);
    function extractMessage(MessageProcessorInterface $messageProcessor);
    function ack();
    function nack($andRequeue);
}
