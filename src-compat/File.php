<?php

class File
{
    public static $magicPath = null;// '/usr/share/misc/magic.mgc'

    public static function getMIMEType($filename)
    {
        // get mime type
        $finfo = static::getFileInfoResource(FILEINFO_MIME);

        if (!$finfo || !($mimeInfo = finfo_file($finfo, $filename))) {
            throw new Exception('Unable to load file info');
        }

        finfo_close($finfo);

        // split mime type
        $p = strpos($mimeInfo, ';');

        return $p ? substr($mimeInfo, 0, $p) : $mimeInfo;
    }

    public static function getMIMETypeFromContents($fileContents)
    {
        // get mime type
        $finfo = static::getFileInfoResource(FILEINFO_MIME);

        if (!$finfo || !($mimeInfo = finfo_buffer($finfo, $fileContents))) {
            throw new Exception('Unable to load file info');
        }

        finfo_close($finfo);

        // split mime type
        $p = strpos($mimeInfo, ';');

        return $p ? substr($mimeInfo, 0, $p) : $mimeInfo;
    }

    public static function getFileInfoResource($options = FILEINFO_NONE)
    {
        try {
            // try with configured magicPath
            $finfo = finfo_open($options, static::$magicPath);
        } catch (Exception $e) {
            try {
                // try with environmental MAGIC if present
                $finfo = finfo_open($options);
            } catch (Exception $e) {
                // try with environmental MAGIC unset
                putenv('MAGIC');
                $finfo = finfo_open($options);
            }
        }

        return $finfo;
    }
}