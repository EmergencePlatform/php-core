<?php

namespace Emergence;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

use Site;
use Emergence\Mailer\Mailer;


class Logger extends \Psr\Log\AbstractLogger
{
    public static $dump;

    public static $logLevelsWrite = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING
    ];

    public static $logLevelsEmail = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL
    ];


    public static function getDump()
    {
        static $dump;

        if ($dump === null) {
            if (static::$dump !== null) {
                $dump = static::$dump;
            } else {
                $config = Site::getConfig('logger');
                $dump = !empty($config['dump']);
            }
        }

        return $dump;
    }

    public static function getRoot()
    {
        static $root;

        if ($root === null) {
            $config = Site::getConfig('logger');

            if (!empty($config['root'])) {
                $root = rtrim($config['root'], '/');
            } else {
                $root = false;
            }
        }

        return $root;
    }

    public static function getTargetConfig($name)
    {
        static $targets;

        if ($targets === null) {
            $config = Site::getConfig('logger');
            $targets = !empty($config['targets']) ? $config['targets'] : [];
        }

        return isset($targets[$name]) ? $targets[$name] : null;
    }


    // handle logging
    protected $name;
    protected $path;

    public function __construct($name = 'general')
    {
        $config = static::getTargetConfig($name) ?: $name;

        if (is_string($config)) {
            if ($config[0] != '/' && ($root = static::getRoot())) {
                $config = $root.'/'.$config.'.log';
            }

            $config = [
                'path' => $config
            ];
        }


        $this->name = $name;

        if (!empty($config['path'])) {
            $this->path = $config['path'];
        }
    }

    private static $instances = [];
    public static function getLogger($target = 'general')
    {
        if (!isset(static::$instances[$target])) {
            static::$instances[$target] = new static($target);
        }

        return static::$instances[$target];
    }

    public static function setLogger(LoggerInterface $logger, $target = 'general')
    {
        static::$instances[$target] = $logger;
    }

    public function log($level, $message, array $context = array())
    {
        if (static::getDump()) {
            dump([
                "{$this->name}.{$level}" => [
                    '$message' => $message,
                    '$context' => $context
                ]
            ]);
        }

        if (in_array($level, static::$logLevelsWrite)) {
            file_put_contents(
                $this->path,
                date('Y-m-d H:i:s')." [$level] $message\n\t"
                    ."context: ".trim(str_replace(PHP_EOL, "\n\t", print_r($context, true)))."\n"
                    ."\tbacktrace:\n\t\t".implode("\n\t\t", static::buildBacktraceLines())
                    ."\n\n",
                FILE_APPEND
            );
        }

        if (Site::$webmasterEmail && in_array($level, static::$logLevelsEmail)) {
            Mailer::send(
                Site::$webmasterEmail,
                "$level logged on $_SERVER[HTTP_HOST]",
                '<dl>'
                    .'<dt>Timestamp</dt><dd>'.date('Y-m-d H:i:s').'</dd>'
                    .'<dt>Level</dt><dd>'.$level.'</dd>'
                    .'<dt>Message</dt><dd>'.htmlspecialchars($message).'</dd>'
                    .'<dt>Context</dt><dd><pre>'.htmlspecialchars(print_r($context, true)).'</pre></dd>'
                    .'<dt>Context</dt><dd><pre>'.htmlspecialchars(implode("\n", static::buildBacktraceLines())).'</pre></dd>'
            );
        }
    }

    public static function buildBacktraceLines()
    {
        $backtrace = debug_backtrace();
        $lines = array();

        // trim call to this method
        array_shift($backtrace);

        // build friendly output lines from backtrace frames
        while ($frame = array_shift($backtrace)) {
            if (!empty($frame['file']) && strpos($frame['file'], \Site::$rootPath.'/data/') === 0) {
                $fileNode = \SiteFile::getByID(basename($frame['file']));

                if ($fileNode) {
                    $frame['file'] = 'emergence:'.$fileNode->FullPath;
                }
            }

            // ignore log-routing frames
            if (
                !empty($frame['file']) &&
                (
                    $frame['file'] == 'emergence:_parent/php-classes/Psr/Log/AbstractLogger.php' ||
                    $frame['file'] == 'emergence:_parent/php-classes/Emergence/Logger.php'
                ) ||
                (empty($frame['file']) && !empty($frame['class']) && $frame['class'] == 'Psr\Log\AbstractLogger') ||
                (!empty($frame['class']) && $frame['class'] == 'Emergence\Logger' && !empty($frame['function']) && $frame['function'] == '__callStatic')
            ) {
                continue;
            }

            $lines[] =
                (!empty($frame['class']) ? "$frame[class]$frame[type]" : '')
                .$frame['function']
                .(!empty($frame['args']) ? '('.implode(',', array_map(function($arg) {
                    return is_string($arg) || is_numeric($arg) ? var_export($arg, true) : gettype($arg);
                }, $frame['args'])).')' : '')
                .(!empty($frame['file']) ? " called at $frame[file]:$frame[line]" : '');
        }

        return $lines;
    }

    public static function interpolate($message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{' . $key . '}'] = static::toLogString($value);
        }

        return strtr($message, $replace);
    }

    protected static function toLogString($value)
    {
        if (is_array($value)) {
            $formatted = [];
            foreach ($value as $key => $value) {
                $value = static::toLogString($value);

                if (is_string($key)) {
                    $formatted[] = "{$key} = {$value}";
                }
            }

            return '[ '.implode(', ', $formatted).' ]';
        } elseif (is_bool($value)) {
            // return $value ? '✔' : '✘';
            return $value ? 'T' : 'F';
        } elseif ($value === null) {
            return '∅';
        } elseif ($value instanceof KeyedDiff) {
            $newValues = $value->getNewValues();
            $oldValues = $value->getOldValues();
            $diff = [];

            foreach ($newValues as $key => $newValue) {
                $diff[$key] = sprintf(
                    '%s → %s',
                    $oldValues && isset($oldValues[$key])
                        ? static::toLogString($oldValues[$key])
                        : '⁇',
                    static::toLogString($newValue)
                );
            }

            return static::toLogString($diff);
        }

        return (string)$value;
    }

    // permit log messages for the default logger instance to be called statically by prefixing them with general_
    public static function __callStatic($method, $arguments)
    {
        if (
            preg_match('/^([^_]+)_(.*)$/', $method, $matches)
            && ($logger = static::getLogger($matches[1]))
            && method_exists($logger, $matches[2])
        ) {
            call_user_func_array([$logger, $matches[2]], $arguments);
        } else {
            throw new \Exception('Undefined logger method');
        }
    }
}