<?php

namespace Comuto\Component\Capl\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * This stream handler is used by Capl
 *
 * It is based on the Monolog STreamHandler, but it handles
 * a map of files which are written to.
 * The file is not determined at construction time, but at
 * write time, it may be in $record['context']['queueName'] otherwise
 * a default file name is used.
 */
class CaplStreamHandler extends AbstractProcessingHandler
{
    private $directory;
    private $streams = array();
    private $stream;

    public function __construct($directory, $level = Logger::DEBUG, $bubble = true)
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException("Directory $dir doesnt exist or is not writable");
        }

        $this->directory = $directory;

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if (!isset($record['context']['capl']['processor'])) {
            $fileName = 'capl';
        } else {
            $fileName = 'v3-worker-'.ltrim(strrchr($record['context']['capl']['processor'], '\\'), '\\');
        }

        $stream = $this->directory."/$fileName";

        if (isset($this->streams[md5($stream)])) {
            $this->stream = $this->streams[md5($stream)];
        } else {
            $this->stream = @fopen($stream, 'a');
            if (!is_resource($this->stream)) {
                $this->stream = null;
                throw new \UnexpectedValueException("The file '$stream' could not be opened");
            }
        }

        fwrite($this->stream, (string) $record['formatted']);

        $this->streams[md5($stream)] = $this->stream;
    }

    public function close()
    {
        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->stream  = null;
        $this->streams = array();
    }
}
