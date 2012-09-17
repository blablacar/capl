<?php

namespace Comuto\Component\Capl;

use Comuto\Component\Capl\AMQP\Message\PayloadInterface;

interface ProcessorInterface
{
    function preProcess(PayloadInterface $payload);
    function process(PayloadInterface $payload);
    function postProcess(PayloadInterface $payload);
}
