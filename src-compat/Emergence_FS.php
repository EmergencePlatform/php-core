<?php

/**
 * This class is used to shim methods that return arrays containing file hashes. Pre-computed
 * hashes are not currently available outside the VFS implementation, and this allows them to
 * be returned without being calculated unless/until they're used
 */
class Emergence_FS_Deferred_SHA1
{
    private $path;
    private $hash;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function __toString()
    {
        if (!$this->hash) {
            $this->hash = sha1_file(SiteFile::getRealPathByID($this->path));
        }

        return $this->hash;
    }
}


class Emergence_FS
{
    public static function cacheTree($path, $force = false)
    {
        return 0;
    }

    public static function getTree($path = null, $localOnly = false, $includeDeleted = false, $conditions = [])
    {
        throw new Exception('TODO: implement getTree');
    }

    public static function getTreeFiles($path = null, $localOnly = false, $fileConditions = [], $collectionConditions = [])
    {
        // check if any parameters not implemented (yet) by this compatibility shim are used
        if ($localOnly) {
            throw new Exception('getTreeFiles shim does not implement $localOnly');
        }

        if (!empty($fileConditions)) {
            $unsupportedConditions = array_diff(array_keys($fileConditions), ['Type']);

            if (count($unsupportedConditions)) {
                throw new Exception('getTreeFiles shim does not implement $fileConditions: '.implode(', ', $unsupportedConditions));
            }
        }

        if (!empty($collectionConditions)) {
            throw new Exception('getTreeFiles shim does not implement $collectionConditions');
        }


        // prepare filters
        if (!empty($fileConditions['Type'])) {
            $typeExtensions = array_flip(SiteFile::$extensionMIMETypes);

            if (empty($typeExtensions[$fileConditions['Type']])) {
                throw new Exception('getTreeFiles only supports filtering by file types listed in SiteFile::$extensionMIMETypes');
            }

            $fileExtension = $typeExtensions[$fileConditions['Type']];
        } else {
            $fileExtension = null;
        }


        // normalize path
        if (is_array($path)) {
            $path = implode('/', $path);
        }


        // get files
        $files = [];

        foreach (Site::getFilesystem()->listContents($path, true) as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            if ($fileExtension && $entry['extension'] != $fileExtension) {
                continue;
            }

            $files[$entry['path']] = [
                'ID' => $entry['path'],
                'CollectionID' => $entry['dirname'],
                'SHA1' => new Emergence_FS_Deferred_SHA1($entry['path']),
                'Site' => 'Local'
            ];
        }

        return $files;
    }

    public static function getTreeFilesFromTree($tree, $conditions = [])
    {
        throw new Exception('TODO: implement getTreeFilesFromTree');
    }

    public static function exportTree($sourcePath, $destinationPath, $options = [])
    {
        throw new Exception('TODO: implement exportTree');
    }

    public static function importFile($sourcePath, $destinationPath)
    {
        throw new Exception('TODO: implement importFile');
    }

    public static function importTree($sourcePath, $destinationPath, $options = [])
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
        // check if any parameters not implemented (yet) by this compatibility shim are used
        if ($localOnly) {
            throw new Exception('getTreeFiles shim does not implement $localOnly');
        }


        // search scope for matching files
        $files = [];

        foreach (Site::getFilesystem()->listContents($scope, true) as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            if ($useRegexp) {
                if (!preg_match('#'.str_replace('#', '\#', $filename).'#i', $entry['basename'])) {
                    continue;
                }
            } else {
                if ($entry['basename'] != $filename) {
                    continue;
                }
            }

            $files[$entry['path']] = new SiteFile($entry['basename'], $entry);
        }

        return $files;
    }

    public static function getAggregateChildren($path)
    {
        if (is_array($path)) {
            $path = implode('/', $path);
        }

        $fs = Site::getFilesystem();
        $children = [];

        if (is_array($path)) {
            $path = implode('/', $path);
        }

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
            foreach ($excludes as $excludePattern) {
                if (preg_match($excludePattern, $relPath)) {
                    return true;
                }
            }
        }

        return false;
    }
}
