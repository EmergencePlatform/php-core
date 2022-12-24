<?php

class Cache
{
    private static $fallbackCache = [];

    public static function rawFetch($key)
    {
        if (function_exists('apcu_fetch')) {
            return apcu_fetch($key);
        } elseif (function_exists('apc_fetch')) {
            return apc_fetch($key);
        } elseif (array_key_exists($key, static::$fallbackCache)) {
            return static::$fallbackCache[$key];
        } else {
            return false;
        }
    }

    public static function rawStore($key, $value, $ttl = 0)
    {
        if (function_exists('apc_store')) {
            return apc_store($key, $value, $ttl);;
        } elseif (function_exists('apcu_store')) {
            return apcu_store($key, $value, $ttl);
        } else {
            static::$fallbackCache[$key] = $value;
            return true;
        }
    }

    public static function rawDelete($key)
    {
        if (function_exists('apcu_delete')) {
            return apcu_delete($key);
        } elseif (function_exists('apc_delete')) {
            return apc_delete($key, $value, $ttl);
        } else {
            unset(static::$fallbackCache[$key]);
            return true;
        }
    }

    public static function rawExists($key)
    {
        if (function_exists('apcu_exists')) {
            return apcu_exists($key);
        } elseif (function_exists('apc_exists')) {
            return apc_exists($key, $value, $ttl);
        } else {
            return array_key_exists($key, static::$fallbackCache);
        }
    }

    public static function rawIncrease($key, $step = 1)
    {
        if (function_exists('apcu_inc')) {
            return apcu_inc($key);
        } elseif (function_exists('apc_inc')) {
            return apc_inc($key, $value, $ttl);
        } else {
            return static::$fallbackCache[$key]++;
        }
    }

    public static function rawDecrease($key, $step = 1)
    {
        if (function_exists('apcu_dec')) {
            return apcu_dec($key);
        } elseif (function_exists('apc_dec')) {
            return apc_dec($key, $value, $ttl);
        } else {
            return static::$fallbackCache[$key]++;
        }
    }

    public static function getKeyPrefix()
    {
        return Site::getConfig('handle').':';
    }

    public static function localizeKey($key)
    {
        return static::getKeyPrefix().$key;
    }

    public static function fetch($key)
    {
        return static::rawFetch(static::localizeKey($key));
    }

    public static function store($key, $value, $ttl = 0)
    {
        return static::rawStore(static::localizeKey($key), $value, $ttl);
    }

    public static function delete($key)
    {
        return static::rawDelete(static::localizeKey($key));
    }

    public static function exists($key)
    {
        return static::rawExists(static::localizeKey($key));
    }

    public static function increase($key, $step = 1)
    {
        return static::rawIncrease(static::localizeKey($key), $step);
    }

    public static function decrease($key, $step = 1)
    {
        return static::rawDecrease(static::localizeKey($key), $step);
    }

    public static function getIterator($pattern)
    {
        // sanity check pattern
        if (!preg_match('/^(.).+\1[a-zA-Z]*$/', $pattern)) {
            throw new Exception('Cache iterator pattern doesn\'t appear to have matching delimiters');
        }

        // modify pattern to insert key prefix and isolate matches to this site
        $prefixPattern = preg_quote(static::getKeyPrefix());
        if ($pattern[1] == '^') {
            $pattern = substr_replace($pattern, $prefixPattern, 2, 0);
        } else {
            $pattern = substr_replace($pattern, '^'.$prefixPattern.'.*', 1, 0);
        }

        return CacheIterator::createFromPattern($pattern);
    }

    public static function deleteByPattern($pattern)
    {
        $count = 0;
        foreach (static::getIterator($pattern) AS $cacheEntry) {
            static::rawDelete($cacheEntry['key']);
            $count++;
        }

        return $count;
    }

    public static function invalidateScript($path)
    {
        if (extension_loaded('Zend OPcache')) {
            opcache_invalidate($path);
        } elseif (extension_loaded('apc')) {
            apc_delete_file($path);
        }
    }
}
