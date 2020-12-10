<?php

class File
{
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
        return finfo_open($options);
    }
}