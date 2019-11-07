<?php

class File
{
    public static $magicPath = null;// '/usr/share/misc/magic.mgc'

    public static function getMIMEType($filePath)
    {
        $mimeInfo = static::getFileInfo($filePath, FILEINFO_MIME);

        // split mime type
        $p = strpos($mimeInfo, ';');

        return $p ? substr($mimeInfo, 0, $p) : $mimeInfo;
    }

    public static function getMIMETypeFromContents($fileContents)
    {
        $mimeInfo = static::getFileInfoFromContents($fileContents, FILEINFO_MIME);

        // split mime type
        $p = strpos($mimeInfo, ';');

        return $p ? substr($mimeInfo, 0, $p) : $mimeInfo;
    }

    public static function getExtension($filePath)
    {
        $extension = static::getFileInfo($filePath, FILEINFO_EXTENSION);

        dd(compact('filePath', 'extension'));
    }

    public static function getExtensionFromContents($fileContents)
    {
        $extension = static::getFileInfoFromContent($fileContents, FILEINFO_EXTENSION);

        dd(compact('extension'));
        return $extension;
    }

    public static function getFileInfo($filePath, $options = FILEINFO_NONE)
    {
        // get mime type
        $finfo = finfo_open($options, static::$magicPath);

        if (!$finfo || !($fileInfo = finfo_file($finfo, $filePath))) {
            throw new Exception('Unable to load file info');
        }

        finfo_close($finfo);

        return $fileInfo;
    }

    public static function getFileInfoFromContents($fileContents, $options = FILEINFO_NONE)
    {
        // get mime type
        $finfo = finfo_open(FILEINFO_MIME, static::$magicPath);

        if (!$finfo || !($fileInfo = finfo_buffer($finfo, $fileContents))) {
            throw new Exception('Unable to load file info');
        }

        finfo_close($finfo);

        return $fileInfo;
    }
}