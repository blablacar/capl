# CAPL #

_Comuto Asynchronous Process Launcher/Consumer_

* CAPL is a command used internally by BlaBlaCar to launch a worker for consuming an
AMQP Queue from RabbitMQ.
* CAPL doesn't manage process creation, it is based on Symfony/Command component
and SupervisorD is used for process management.
* CAPL is based on ext/amqp from pecl PHP. Please, use the latest version as this
lib still has bugs and is under development.

## Details ##

Let's explain what are the problems we met so that you understand the design
rules and the architecture that led to our code.

* We actually only use "direct" exchanges
* We publish "Message" (Capl\AMQP\Message\Message.php), this is an abstract view
  of our business stuff, it contains data such as 'branding' and 'media' which
  are used by CAPL to boot some Symfony Kernels.
	* Then the Message contains the Payload and the 'processor' which is just
	  a string naming a service that will handle that message in the dispatched
	  Kernel's DIC and be passed the Payload as incoming data.
* Kernels have to implement our CaplKernelInterface, this is the code we have in
  our kernel :

```php
    public function getAsyncMessageProcessor($processorName)
    {
        $processorId = "comuto.amqp.processor.$processorName";
        if (!$this->container->has($processorId)) {
            throw new \RuntimeException("Service $processorId not found");
        }

        return $this->container->get($processorId);
    }
```

### CAPL Command workflow ###

* Parse config, find AMQP adapter credentials, connect, use a logger (GrayLog
of file based)
* Read the queue name from command line, then listen to it
* When message comes :
	* Unserialize it
	* Check the validity (is it a Capl\AMQP\Message\Message ?)
	* Calls the processor
	    * Boots the right Kernel depending on 'branding' and 'media' found into
          the Message
	    * Gets the DIC service that can process the message
	    * Process message passing it payload
* ACK if no exception
* NACK if exception (requeued in a Dead Letter Queue) and log exception

## Documentation ##

RabbitMQ Getting started : http://www.rabbitmq.com/getstarted.html
ext/amqp : http://pecl.php.net/package/amqp
librabbitmq : http://hg.rabbitmq.com/rabbitmq-c/
supervisor: http://supervisord.org/

## Licence ##
See Licence

Warning: We distribute this project as is. It may not have been designed for
distribution, though may not be usable as is.
