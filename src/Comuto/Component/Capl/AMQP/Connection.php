<?php

namespace Comuto\Component\Capl\AMQP;

/**
 * AMQP Connection
 */
class Connection extends \AMQPConnection
{
    /**
     * @param array $infos keys must be 'host', 'port', 'login', 'password', 'vhost'
     */
    public function __construct(array $infos)
    {
        parent::__construct($infos);
    }
}
