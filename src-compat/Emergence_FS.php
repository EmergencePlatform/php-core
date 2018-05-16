<?php

class Emergence_FS
{
    public static function cacheTree($path, $force = false)
    {
        return 0;
    }

    public static function getTree($path = null, $localOnly = false, $includeDeleted = false, $conditions = array())
    {
        throw new Exception('TODO: implement getTree');
    }

    public static function getTreeFiles($path = null, $localOnly = false, $fileConditions = array(), $collectionConditions = array())
    {
        return static::getTreeFilesFromTree(static::getTree($path, $localOnly, false, $collectionConditions), $fileConditions);
    }

    public static function getTreeFilesFromTree($tree, $conditions = array())
    {
        throw new Exception('TODO: implement getTreeFilesFromTree');
    }

    public static function exportTree($sourcePath, $destinationPath, $options = array())
    {
        throw new Exception('TODO: implement exportTree');
    }

    public static function importFile($sourcePath, $destinationPath)
    {
        throw new Exception('TODO: implement importFile');
    }

    public static function importTree($sourcePath, $destinationPath, $options = array())
    {
        throw new Exception('TODO: implement importTree');
    }

    public static function getTmpDir($prefix = 'etmp-')
    {
        $tmpPath = tempnam('/tmp', $prefix);
        unlink($tmpPath);
        mkdir($tmpPath);
        return $tmpPath;
    }

    public static function getCollectionLayers($path, $localOnly = false)
    {
        throw new Exception('TODO: implement getCollectionLayers');
    }

    public static function findFiles($filename, $useRegexp = false, $scope = null, $localOnly = false)
    {
        throw new Exception('TODO: implement findFiles');
    }

    public static function getAggregateChildren($path)
    {
        $fs = Site::getFilesystem();
        $children = [];

        foreach ($fs->listContents($path) as $child) {
            $children[$child['basename']] =
                $child['type'] == 'file'
                ? new SiteFile($child['basename'], $child)
                : new SiteCollection($child['basename'], $child);
        }

        return $children;
    }

    public static function getNodesFromPattern($patterns, $localOnly = false)
    {
        throw new Exception('TODO: implement getNodesFromPattern');
    }

    public static function matchesExclude($relPath, array $excludes)
    {
        if ($excludes) {
            foreach ($excludes AS $excludePattern) {
                if (preg_match($excludePattern, $relPath)) {
                    return true;
                }
            }
        }

        return false;
    }
}
