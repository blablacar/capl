<?php

namespace Comuto\Component\Capl\Command;

use Comuto\Component\Capl\Monolog\Processor\CaplProcessor;
use Symfony\Component\Yaml\Yaml;

class CommandHelper
{
    private static $kernelClassMap = array(
        'test'=>'Comuto\Component\Capl\Tests\Stub\TestKernel',
        'amqp'=>'\AppKernel'
    );

    const WORKER_STRATEGY_NS = 'Comuto\Component\Capl\Worker\\';

    public static function parseConfig($file)
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not exist', $file));
        }

        $yaml = Yaml::parse($file);
        foreach (array('amqp_host', 'amqp_port', 'amqp_login', 'amqp_password', 'amqp_vhost', 'graylog_host', 'use_graylog', 'graylog_slot') as $key) {
            if (!isset($yaml['parameters'][$key])) {
                throw new \InvalidArgumentException(sprintf('Key "%s" needs to be defined in configuration file', $key));
            }
        }

        return array(
            'amqp' => array(
                'host'     => $yaml['parameters']['amqp_host'],
                'port'     => $yaml['parameters']['amqp_port'],
                'login'    => $yaml['parameters']['amqp_login'],
                'password' => $yaml['parameters']['amqp_password'],
                'vhost'    => $yaml['parameters']['amqp_vhost'],
            ),
            'graylog' => array(
                'host'    => $yaml['parameters']['graylog_host'],
                'slot'    => $yaml['parameters']['graylog_slot'],
                'useit'   => $yaml['parameters']['use_graylog']
            ),
        );
    }

    public static function getWorkerClass($strategyType)
    {
        $strategyClass = self::WORKER_STRATEGY_NS . ucfirst(strtolower($strategyType));
        $strategyClass .= 'Strategy';

        if (!class_exists($strategyClass, true)) {
            throw new \RuntimeException("Strategy class $strategyClass not found");
        }

        return $strategyClass;
    }

    public static function getKernelClass($strategyType)
    {
        if (!isset(self::$kernelClassMap[$strategyType])) {
            throw new \RuntimeException("Unrecognized Capl strategy '$strategyType'");
        }

        $kernelClass = self::$kernelClassMap[$strategyType];
        if (!class_exists($kernelClass, true)) {
            throw new \RuntimeException("Kernel class $kernelClass not found");
        }

        return $kernelClass;
    }

    /**
     * Block common used signals
     */
    public static function startSignalHandler()
    {
        pcntl_sigprocmask(SIG_BLOCK, array(SIGTERM, SIGINT, SIGQUIT));
    }

    /**
     * Unblock common used signals
     */
    public static function stopSignalHandler()
    {
        pcntl_sigprocmask(SIG_UNBLOCK, array(SIGTERM, SIGINT, SIGQUIT));
    }
}
