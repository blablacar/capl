<?php

namespace Comuto\Component\Capl;

interface MessageProcessorInterface
{
    function processMessage(AMQP\Message\Message $message);
}
