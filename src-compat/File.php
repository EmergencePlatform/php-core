<?php

class File
{
    public static $magicPath = null;

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

        // dd(compact('filePath', 'extension'));
        return $extension;
    }

    public static function getExtensionFromContents($fileContents)
    {
        $extension = static::getFileInfoFromContent($fileContents, FILEINFO_EXTENSION);

        // dd(compact('extension'));
        return $extension;
    }

    public static function getFileInfo($filePath, $options = FILEINFO_NONE)
    {
        // get mime type
        $finfo = static::getFileInfoResource($options);

        if (!$finfo || !($fileInfo = finfo_file($finfo, $filePath))) {
            throw new Exception('Unable to load file info');
        }

        finfo_close($finfo);

        return $fileInfo;
    }

    public static function getFileInfoFromContents($fileContents, $options = FILEINFO_NONE)
    {
        // get mime type
        $finfo = static::getFileInfoResource($options);

        if (!$finfo || !($fileInfo = finfo_buffer($finfo, $fileContents))) {
            throw new Exception('Unable to load file info');
        }

        finfo_close($finfo);

        return $fileInfo;
    }

    protected static function getFileInfoResource($options = FILEINFO_NONE)
    {
        $magicPath = static::$magicPath ? static::$magicPath : getenv('MAGIC');
        // $magicPath = preg_replace('/\.(mgc|mime)$/i', '', $magicPath);
        return finfo_open($options);
    }
}
