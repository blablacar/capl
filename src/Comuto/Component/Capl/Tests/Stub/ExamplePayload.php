<?php

namespace Comuto\Component\Capl\Tests\Stub;

use Comuto\Component\Capl\AMQP\Message\PayloadInterface;

class ExamplePayload implements PayloadInterface
{
    public function getData()
    {
        return array('foo', 'bar');
    }
}
