<?php

namespace Comuto\Component\Capl\Command;

use Monolog\Logger;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Comuto\Component\Capl\AMQP\Message\Message;
use Comuto\Component\Capl\MessageProcessorInterface;
use Comuto\Component\Capl\ProcessorInterface;
use Comuto\Component\Capl\CaplKernelInterface;
use Comuto\Component\Capl\Monolog\Handler\CaplStreamHandler;
use Comuto\Component\Monolog\Handler\GrayLogHandler;
use Comuto\Component\Monolog\Processor\GrayLogProcessor;

class CaplCommand extends Command implements MessageProcessorInterface
{
    private $config;
    private $kernelsMap = array();
    private $processorLog;
    private $outputStream;
    private $worker;
    private $queueName;
    private $timer = 0;
    private $inputStream;
    private $options = array();
    private $workerClass;
    private $kernelClass;

    private $loggers = array();

    const DEFAULT_GC_COLLECT_CYCLES = 100;
    const DEFAULT_WORKER_STRATEGY   = 'amqp';
    const DEFAULT_KERNEL_TIMEOUT    = 600; /* 10min */
    const DEFAULT_KERNEL_ENV        = 'dev';
    const DEFAULT_LOG_DIR           = '/tmp';

    public function __construct($configFile)
    {
        ini_set('memory_limit' ,-1);
        parent::__construct(); /* calls configure() */
        $this->config = CommandHelper::parseConfig($configFile);
    }

    protected function configure()
    {
        $this
            ->setName('consume')
            ->setDefinition(new InputDefinition(array(
                // new InputArgument('amqp_connection', InputArgument::REQUIRED),
                new InputArgument('queue_name', InputArgument::REQUIRED, 'queue name'),
            )))
        ;

        $this->addOption('no-kernel-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.');
        $this->addOption('requeue-on-error', null, InputOption::VALUE_NONE, 'Requeue in the same queue on error');
        $this->addOption('kernel-timeout', null, InputOption::VALUE_REQUIRED, 'Kernel cleaning timeout (seconds)', self::DEFAULT_KERNEL_TIMEOUT);
        $this->addOption('gc-collect-cycles', null, InputOption::VALUE_REQUIRED, 'Number of cycles before GC run', self::DEFAULT_GC_COLLECT_CYCLES);
        $this->addOption('no-sighandler', null, InputOption::VALUE_NONE, 'Disable signal handlers');
        $this->addOption('worker-strategy', null, InputOption::VALUE_REQUIRED, "AMQP worker strategy ('amqp', 'test')", self::DEFAULT_WORKER_STRATEGY);
        $this->addOption('kernel-env', null, InputOption::VALUE_REQUIRED, 'Kernel Environnement', self::DEFAULT_KERNEL_ENV);
        $this->addOption('log-dir', null, InputOption::VALUE_REQUIRED, 'Log directory', self::DEFAULT_LOG_DIR);
    }

    /* Called just before execute() */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->options['isDebug']        = !$input->getOption('no-kernel-debug');
        $this->options['requeueOnError'] = $input->getOption('requeue-on-error');
        $this->outputStream              = $output;
        $this->inputStream               = $input;

        set_error_handler(array($this, 'errorHandler'), E_RECOVERABLE_ERROR);
        register_shutdown_function(array($this, 'shutDown'));

        $this->options['kernelTimeout']   = $input->getOption('kernel-timeout');
        $this->options['kernelEnv']       = $input->getOption('kernel-env');
        $this->options['logDir']          = $input->getOption('log-dir');
        $this->options['gcCollectCycles'] = $input->getOption('gc-collect-cycles');
        $this->options['useSigHandler']   = !$input->getOption('no-sighandler');

        if ($this->options['useSigHandler'] && !extension_loaded('pcntl')) {
            throw new \RuntimeException("You choose to use signal handlers, PHP extension 'pcntl' is required");
        }

        $workerType = $input->getOption('worker-strategy');
        $this->workerClass = CommandHelper::getWorkerClass($workerType);
        $this->kernelClass = CommandHelper::getKernelClass($workerType);
    }

    private function writeError($msg, array $context = array())
    {
        $this->outputStream->getErrorOutput()->writeln("<error>$msg</error>");
        try {
            $this->processorLog->addError($msg, $context);
        } catch (\Exception $e) {
            $this->fatalCaplError($e->getMessage());
        }
    }

    private function writeDebug($msg, array $context = array())
    {
        $this->outputStream->writeln("<info>$msg</info>");
        try {
            $this->processorLog->addInfo($msg, $context);
        } catch (\Exception $e) {
            $this->fatalCaplError($e->getMessage());
        }
    }

    private function fatalCaplError($msg)
    {
        $this->outputStream->getErrorOutput()->writeln("<error>FATAL CAPL Error: $msg</error>");
        exit(1);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->worker    = new $this->workerClass($this->config['amqp'], $input->getArgument('queue_name'));
            $this->queueName = $input->getArgument('queue_name');
            $amqpStrategy    = $this->workerClass;
            $this->outputStream->write("<info>Capl Running on queue <comment>$this->queueName</comment> using <comment>$amqpStrategy</comment></info> \n");
        } catch (\Exception $e) {
            $this->fatalCaplError("Could not start AMQP worker strategy: " . $e->getMessage());
        }

        /* This should call processMessage() on each message */
        $return = $this->worker->extractMessage($this);
        if ($return === false) {
            $lastPhpError = error_get_last();
            $this->fatalCaplError("Could not process messages: {$lastPhpError['message']}");
        }
        $this->writeDebug("Capl ended normally. Bye");
        exit(0);
    }

    public function processMessage(Message $message)
    {
        static $n = 0;
        $this->processorLog = $this->getNamedLogger($message->getProcessorName());

        $kernel = $this->getKernel($message->getBranding(), $message->getMedia(), $this->options['kernelEnv'], $this->options['isDebug']);
        if ($this->options['useSigHandler']) {
            CommandHelper::startSignalHandler();
        }

        $this->doProcessMessage($kernel, $message);

        if ($this->options['useSigHandler']) {
            CommandHelper::stopSignalHandler();
        }
        if (++$n % $this->options['gcCollectCycles'] == 0) {
            $garbages = gc_collect_cycles();
            $this->outputStream->writeln("<info>Ran GC, collected $garbages garbages</info>");
        }

        $usage = round(memory_get_usage()/1024/1024, 2);
        $this->writeDebug("Memory usage : <comment>$usage Mo</comment>");
    }

    private function doProcessMessage(CaplKernelInterface $kernel, Message $message)
    {
        try {
            $processor = $kernel->getAsyncMessageProcessor($message->getProcessorName());
            if (!$processor instanceof ProcessorInterface) {
                throw new \RuntimeException("invalid processor");
            }

            $processor->preProcess($message->getPayload());
            $processor->process($message->getPayload());
            $processor->postProcess($message->getPayload());

            $this->worker->ack();
            $this->writeDebug("ACKED [{$message->getId()}]", array('capl' => array(
                'processor' => get_class($processor),
                'queueName' => $this->queueName
            )));
        } catch (\Exception $e) {
            $this->worker->nack($this->options['requeueOnError']);
            $this->writeError("REJECTED [{$message->getId()}] in ".get_class($processor), array('capl' => array(
                'processor' => get_class($processor),
                'queueName' => $this->queueName,
                'exception' => $e->__toString()
            )));
        }
    }

    private function getKernel($branding, $media, $env, $debug)
    {
        $key = self::getKernelKey($branding, $media, $env, $debug);

        if (isset($this->kernelsMap[$key])) {
            if (time() - $this->kernelsMap[$key]['last_access_time'] > $this->options['kernelTimeout']) {
                unset($this->kernelsMap[$key]);
                $this->writeDebug("Cleaned kernel <comment>$key</comment>");

                return $this->getNewKernel($branding, $media, $env, $debug);
            }
            $this->kernelsMap[$key]['last_access_time'] = time();

            return $this->kernelsMap[$key]['kernel'];
        }

        return $this->getNewKernel($branding, $media, $env, $debug);
    }

    private static function getKernelKey($branding, $media, $env, $debug)
    {
        return $branding.'_'.$media.'_'.$env.($debug ? '_debug' : '');
    }

    private function getNamedLogger($name)
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        $logger = new Logger('capl' /* This will be used by graylog2 filters! */);

        if ($this->config['graylog']['useit']) {
            $handler = new GrayLogHandler(
                $this->config['graylog']['host'],
                Logger::WARNING,
                GrayLogProcessor::GRAYLOG_TYPE_CAPL,
                $this->config['graylog']['slot']
            );
        } else {
            $handler = new CaplStreamHandler($this->options['logDir'], Logger::WARNING);
        }

        $logger->pushHandler($handler);

        return $this->loggers[$name] = $logger;
    }

    private function getNewKernel($branding, $media, $env, $debug)
    {
        $key = self::getKernelKey($branding, $media, $env, $debug);

        $this->kernelsMap[$key]['kernel'] = new $this->kernelClass($env, $debug, $branding, $media);
        $this->kernelsMap[$key]['kernel']->boot();
        $this->kernelsMap[$key]['last_access_time'] = time();

        $this->writeDebug("Spawned a new Kernel");

        return $this->kernelsMap[$key]['kernel'];
    }

    /* Must be public */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $this->writeError($errstr);
    }

    /* Must be public */
    public function shutdown()
    {
        $lastErr = error_get_last();
        if ($lastErr && ($lastErr['type'] & (E_ERROR | E_COMPILE_ERROR | E_CORE_ERROR))) {
            $this->fatalCaplError("PHP Fatal Error! {$lastErr['message']}");
        }
    }
}
