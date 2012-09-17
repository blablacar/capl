<?php

namespace Comuto\Component\Capl\Tests\Stub;

class ExampleAMQPEnvelope extends \AMQPEnvelope
{
    public function getMessageId()
    {
        return uniqid();
    }
}
