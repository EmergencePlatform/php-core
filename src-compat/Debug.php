<?php

class Debug
{
    public static $log = [];

    public static function dump($var, $exit = true, $title = null)
    {
        if (Site::$debug) {
            if ($title) {
                $var = [$title => $var];
            }

            dump($var);
        }

        if ($exit) {
            exit();
        }
        return $var;
    }

    public static function dumpVar($var, $exit = true, $title = null)
    {
        if (Site::$debug) {
            if ($title) {
                $var = [$title => $var];
            }

            dump($var);
        }

        if ($exit) {
            exit();
        }
        return $var;
    }

    public static function logMessage($message, $source = null)
    {
        return static::log(['message' => $message], $source);
    }

    public static function log($entry, $source = null): void
    {
        if (!Site::$debug) {
            return;
        }

        static::$log[] = array_merge($entry, [
            'source' => $source ?? static::_detectSource()
            ,'time' => sprintf('%f', microtime(true))
        ]);
    }

    protected static function _detectSource(): string
    {
        $backtrace = debug_backtrace();

        while ($trace = array_shift($backtrace)) {
            if (!empty($trace['class'])) {
                if ($trace['class'] == self::class) {
                    continue;
                }
                return $trace['class'];
            }
            if (!empty($trace['file'])) {
                return basename($trace['file']);
            }
        }

        return basename($_SERVER['SCRIPT_NAME']);
    }

    protected static $_traceHandle;
    public static function writeTrace($message, $data = []): void
    {
        if (!static::$_traceHandle) {
            $filePath = Site::$rootPath.'/site-data/trace-logs/';
            $dateStamp = date('Y-m-d-His');
            $increment = 1;

            if (!is_dir($filePath)) {
                mkdir($filePath, 0777, true);
            }

            do {
                $fileName = $dateStamp.'.'.$increment.'.log';
                $increment++;
            } while (file_exists($filePath.$fileName));

            static::$_traceHandle = fopen($filePath.$fileName, 'w');
        }

        fwrite(static::$_traceHandle, round(microtime(true) * 1000)."\t$message\t".json_encode($data)."\n");
    }
}
