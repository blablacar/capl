<?php

namespace Comuto\Component\Capl\AMQP\Message;

class Message
{
    private $branding;
    private $media;
    private $payload;
    private $processorId;

    /**
     * @var AMQPEnveloppe
     */
    private $amqpEnvelope;

    public function __construct(PayloadInterface $payload, $processorName, $branding = null, $media = null)
    {
        $this->branding      = $branding;
        $this->media         = $media;
        $this->payload       = $payload;
        $this->processorName = $processorName;
    }

    public function getBranding()
    {
        return $this->branding;
    }

    public function setBranding($branding)
    {
        $this->branding = $branding;
    }

    public function getMedia()
    {
        return $this->media;
    }

    public function setMedia($media)
    {
        $this->media = $media;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function setPayload(Payload $payload)
    {
        $this->payload = $payload;
    }

    public function getProcessorName()
    {
        return $this->processorName;
    }

    public function setProcessorName($processorName)
    {
        $this->processorName = $processorName;
    }

    public function getId()
    {
        return $this->amqpEnvelope->getMessageId();
    }

    public function setAMQPEnvelope(\AMQPEnvelope $env)
    {
        $this->amqpEnvelope = $env;
    }

    /**
     * Is this message a dead letter one ?
     *
     * @return bool
     */
    public function isDeadLettered()
    {
        return $this->amqpEnvelope->isRedelivery();
    }

    /**
     * Take amqpEnveloppe out of serialization
     *
     * @return array
     */
    public function __sleep()
    {
        $props = get_object_vars($this);

        return array_diff(array_keys($props), array('amqpEnveloppe'));
    }

    /**
     * Retrieves message AMQP headers
     */
    public function getHeaders()
    {
        return $this->amqpEnvelope->getHeaders();
    }
}
