<?php

namespace Comuto\Component\Capl\AMQP;

/**
 * AMQP Exchange.
 *
 * Our exchanges are all in DIRECT mode and are DURABLE.
 */
class Exchange extends \AMQPExchange
{
    const DELIVERY_MODE_NON_PERSISTENT = 1;
    const DELIVERY_MODE_PERSISTENT     = 2;

    /**
     * We deliver PERSISTENT messages.
     *
     * @param AbstractMessage $message
     */
    public function messagePublish(Message\Message $message, $routingKey)
    {
        $metadata = array();
        $data     = serialize($message);

        $metadata['timestamp'] = time();
        $metadata['delivery_mode'] = self::DELIVERY_MODE_PERSISTENT;
        $metadata['message_id'] = md5(microtime().$data);
        $metadata['content_type'] = 'application/php';
        $metadata['content_encoding'] = 'utf8';

        parent::publish($data, $routingKey, AMQP_MANDATORY, $metadata);
    }
}
