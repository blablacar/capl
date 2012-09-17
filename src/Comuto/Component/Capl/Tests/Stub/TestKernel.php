<?php

namespace Comuto\Component\Capl\Tests\Stub;

use Comuto\Component\Capl\CaplKernelInterface;

class TestKernel implements CaplKernelInterface
{
    public function __construct($environment, $debug, $branding = null, $media = null)
    {

    }

    public function getAsyncMessageProcessor($processorId)
    {

    }

    public function boot()
    {

    }
}
