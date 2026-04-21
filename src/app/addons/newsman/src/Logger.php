<?php

namespace Tygh\Addons\Newsman;

use Tygh\Registry;

class Logger
{
    /** @var Config */
    protected $config;

    /** @var bool */
    protected $cleanupDone = false;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $message
     */
    public function debug($message)
    {
        $this->log(Config::LOG_DEBUG, $message);
    }

    /**
     * @param string $message
     */
    public function info($message)
    {
        $this->log(Config::LOG_INFO, $message);
    }

    /**
     * @param string $message
     */
    public function notice($message)
    {
        $this->log(Config::LOG_NOTICE, $message);
    }

    /**
     * @param string $message
     */
    public function warning($message)
    {
        $this->log(Config::LOG_WARNING, $message);
    }

    /**
     * @param string $message
     */
    public function error($message)
    {
        $this->log(Config::LOG_ERROR, $message);
    }

    /**
     * @param \Throwable $exception
     */
    public function logException($exception)
    {
        $this->error(sprintf(
            '%s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }

    /**
     * @param int    $level
     * @param string $message
     */
    public function log($level, $message)
    {
        $severity = $this->config->getLogSeverity();

        if ($severity <= Config::LOG_NONE || $level < $severity) {
            return;
        }

        $logFile = $this->getLogFilePath();
        $date = date('Y-m-d H:i:s');
        $levelName = $this->getLevelName($level);
        $line = sprintf("[%s] %s: %s\n", $date, $levelName, $message);

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        if (!$this->cleanupDone) {
            $this->cleanupDone = true;
            $this->cleanupOldLogs();
        }
    }

    /**
     * @return string
     */
    public function getLogFilePath()
    {
        $logDir = $this->getLogDir();

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        return $logDir . 'newsman_' . date('Y-m-d') . '.log';
    }

    /**
     * @return string
     */
    public function getLogDir()
    {
        return Registry::get('config.dir.var') . 'newsman_logs/';
    }

    /**
     * @return array
     */
    public function getLogFiles()
    {
        $dir = $this->getLogDir();
        $files = array();
        $pattern = $dir . 'newsman_*.log';

        foreach (glob($pattern) as $file) {
            $files[] = array(
                'path' => $file,
                'name' => basename($file),
                'size' => filesize($file),
                'mtime' => filemtime($file),
            );
        }

        usort($files, function ($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        return $files;
    }

    public function cleanupOldLogs()
    {
        $retentionDays = $this->config->getLogCleanDays();
        if ($retentionDays <= 0) {
            return;
        }

        $dir = $this->getLogDir();
        $cutoff = time() - ($retentionDays * 86400);
        $pattern = $dir . 'newsman_*.log';

        foreach (glob($pattern) as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }

        // Also clean legacy single log file if it exists
        $legacyLog = $dir . 'newsman.log';
        if (file_exists($legacyLog) && filemtime($legacyLog) < $cutoff) {
            @unlink($legacyLog);
        }
    }

    /**
     * @param int $level
     * @return string
     */
    public function getLevelName($level)
    {
        $names = array(
            Config::LOG_DEBUG   => 'DEBUG',
            Config::LOG_INFO    => 'INFO',
            Config::LOG_NOTICE  => 'NOTICE',
            Config::LOG_WARNING => 'WARNING',
            Config::LOG_ERROR   => 'ERROR',
        );

        return isset($names[$level]) ? $names[$level] : 'LOG';
    }
}
