<?php

namespace Comuto\Component\Capl;

interface CaplKernelInterface
{
    function getAsyncMessageProcessor($processorName);
}
