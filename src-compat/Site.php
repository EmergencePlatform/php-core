<?php

class Site
{
    // config properties
    public static $title = null;
    public static $debug = false;
    public static $production = true;
    public static $defaultPage = 'home.php';
    public static $autoCreateSession = true;
    public static $listCollections = false;
    public static $onInitialized;
    public static $onNotFound;
    public static $onRequestMapped;
    public static $permittedOrigins = array();
    public static $autoPull = true;
    public static $skipSessionPaths = array();
    public static $onSiteCreated;
    public static $onBeforeScriptExecute;
    public static $onBeforeStaticResponse;

    // public properties
    public static $hostname;
    public static $rootPath;
    public static $webmasterEmail = 'root@localhost';
    public static $requestURI;
    public static $requestPath = array();
    public static $pathStack = array();
    public static $resolvedPath = array();
    public static $resolvedNode;
    public static $config; // TODO: deprecated; use Site::getConfig(...)
    public static $initializeTime;

    // protected properties
    protected static $_loadedClasses = array();
    protected static $_rootCollections;
    protected static $_config;

    public static function initialize($rootPath, $hostname = null, array $config)
    {
        static::$initializeTime = microtime(true);

        // prevent caching by default
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        // initialize error handler
        $whoops = static::getWhoops();

        if (static::$debug) {
            $whoops->writeToOutput(true);

            $debugHandler = new \Whoops\Handler\PrettyPageHandler();
            $debugHandler->addDataTableCallback(
                'Routing',
                function () {
                    return [
                        'requestPath' => implode('/', Site::$requestPath),
                        'resolvedPath' => implode('/', Site::$resolvedPath),
                        'resolvedNode' => Site::$resolvedNode ? Site::$resolvedNode->FullPath : null,
                        'pathStack' => implode('/', Site::$pathStack)
                    ];
                }
            );
            $whoops->pushHandler($debugHandler);
        } else {
            $whoops->writeToOutput(true);

            $whoops->pushHandler(
                function ($exception, $inspector, $run) {
                    \Emergence\Logger::crash_error(
                        '{exceptionName}: {exceptionMessage}',
                        [
                            '$frames' => $inspector->getFrames(),
                            'exceptionName' => $inspector->getExceptionName(),
                            'exceptionMessage' => $inspector->getExceptionMessage()
                        ]
                    );
                    echo "An unexpected problem has prevented your request from being handled. The site's crash log and/or webmaster mailbox should contain a report. If you are this website's developer, enable debugging to see error details directly.";
                    return \Whoops\Handler\Handler::QUIT;
                }
            );
        }

        $whoops->register();

        // get site root
        if ($rootPath) {
            static::$rootPath = $rootPath;
        } elseif (!static::$rootPath) {
            throw new Exception('No site root detected');
        }

        // get config
        static::$_config = $config;

        // TODO: deprecate
        static::$config = static::$_config;

        // get hostname
        if ($hostname) {
            static::$hostname = $hostname;
        } elseif (!static::$hostname) {
            if (!empty(static::$config['primary_hostname'])) {
                static::$hostname = static::$config['primary_hostname'];
            } else {
                static::$hostname = 'localhost';
            }
        }

        // get path stack
        if (!empty($_SERVER['REQUEST_URI'])) {
            $path = $_SERVER['REQUEST_URI'];

            if (false !== ($qPos = strpos($path, '?'))) {
                $path = substr($path, 0, $qPos);
            }

            $path = rawurldecode($path);

            static::$pathStack = static::$requestPath = static::splitPath($path);
        }

        // set time zone
        if ($timezone = static::getConfig('time_zone')) {
            date_default_timezone_set($timezone);
        }

        // set useful transaction name and metadata for newrelic
        if (extension_loaded('newrelic')) {
            newrelic_name_transaction(static::getConfig('handle').'/'.implode('/', site::$requestPath));

            if (isset($_SERVER['QUERY_STRING'])) {
                newrelic_add_custom_parameter('request.query', $_SERVER['QUERY_STRING']);
            }
        }

        // register class loader
        spl_autoload_register('Site::loadClass');

        // check virtual system for site config
        static::loadConfig(__CLASS__);

        // load all bootstraps scripts
        foreach (static::getFilesystem()->listContents('php-bootstraps') as $bootstrapFile) {
            if ($bootstrapFile['type'] == 'file' && $bootstrapFile['extension'] == 'php') {
                require_once(static::$rootPath.'/site/'.$bootstrapFile['path']);
            }
        }

        // get site title
        if (!static::$title) {
            static::$title = static::getConfig('label') ?: static::$hostname;
        }

        // configure error display
        ini_set('display_errors', static::$debug);

        // check virtual system for proxy config
        if (class_exists('HttpProxy')) {
            static::loadConfig('HttpProxy');
        }

        if (is_callable(static::$onInitialized)) {
            call_user_func(static::$onInitialized);
        }

        if (class_exists('Emergence\\EventBus')) {
            Emergence\EventBus::fireEvent('initialized', 'Site');
        }
    }

    public static function onSiteCreated($requestData)
    {
        // create initial developer
        if (!empty($requestData['create_user'])) {
            try {
                $userClass = User::getStaticDefaultClass();

                $User = $userClass::create(array_merge($requestData['create_user'], array(
                    'AccountLevel' => 'Developer'
                )));
                $User->setClearPassword($requestData['create_user']['Password']);
                $User->save();
            } catch (Exception $e) {
                // fail silently
            }
        }

        // execute configured method
        if (is_callable(static::$onSiteCreated)) {
            call_user_func(static::$onSiteCreated, $requestData);
        }

        if (class_exists('Emergence\\EventBus')) {
            Emergence\EventBus::fireEvent('siteCreated', 'Site', $requestData);
        }
    }

    public static function handleRequest()
    {
        // handle CORS headers
        if (
            !empty($_SERVER['HTTP_ORIGIN'])
            && $_SERVER['HTTP_ORIGIN'] != 'null'
        ) {
            $hostname = strtolower(parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST));
            if (
                $hostname == strtolower(static::$hostname)
                || (!empty($_SERVER['HTTP_HOST']) && $hostname == strtolower($_SERVER['HTTP_HOST']))
                || static::$permittedOrigins == '*'
                || in_array($hostname, static::$permittedOrigins)
            ) {
                header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
                header('Access-Control-Allow-Credentials: true');
                //header('Access-Control-Max-Age: 86400')
            } else {
                header('HTTP/1.1 403 Forbidden');
                exit();
            }
        }

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS' && isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Methods: '.$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header('Access-Control-Allow-Headers: '.$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
            }

            exit();
        }

        // handle emergence request
        if (static::$pathStack[0] == 'emergence') {
            array_shift(static::$pathStack);
            return Emergence::handleRequest();
        }

        // resolve request path against site-root filesystem
        $pathResult = static::getRequestPathResult(static::$pathStack);
        static::$resolvedNode = $pathResult['resolvedNode'];
        static::$resolvedPath = $pathResult['resolvedPath'];
        static::$pathStack = $pathResult['pathStack'];

        // bail now if no node found
        if (!static::$resolvedNode) {
            return static::respondNotFound();
        }

        // execute requestMapped hook and event handlers
        if (is_callable(static::$onRequestMapped)) {
            call_user_func(static::$onRequestMapped, static::$resolvedNode);
        }

        if (class_exists('Emergence\\EventBus')) {
            Emergence\EventBus::fireEvent('requestMapped', 'Site', array(
                'node' => static::$resolvedNode
            ));
        }

        // if resolved node is a collection, look for _index.php
        if (is_a(static::$resolvedNode, 'SiteCollection')) {
            $indexNode = static::resolvePath(array_merge($pathResult['searchPath'], array('_index.php')));

            if ($indexNode) {
                // switch resolvedNode to the index script
                static::$resolvedNode = $indexNode;
            } elseif (!static::$listCollections) {
                // if listing collections is disabled and no index script was found, treat this as not found
                return static::respondNotFound();
            }
        }

        // handle php script
        if (static::$resolvedNode->MIMEType == 'application/php') {
            // TODO: execute _all.php handlers, cache the list of them for the containing collection
            return static::executeScript(static::$resolvedNode);
        }

        // output response
        if (is_callable(array(static::$resolvedNode, 'outputAsResponse'))) {
            if (is_callable(static::$onBeforeStaticResponse)) {
                call_user_func(static::$onBeforeStaticResponse, static::$resolvedNode);
            }

            if (class_exists('Emergence\\EventBus')) {
                Emergence\EventBus::fireEvent('beforeStaticResponse', 'Site', array(
                    'node' => static::$resolvedNode
                ));
            }

            static::$resolvedNode->outputAsResponse();
        }

        // fallback on notfound handler
        return static::respondNotFound();
    }

    public static function getRequestPathResult($path)
    {
        // results returned at end:
        $resolvedNode = null;
        $resolvedPath = array();

        // normalize args
        if (is_string($path)) {
            $path = static::splitPath($path);
        }

        // rewrite empty path to default page
        if (empty($path[0]) && static::$defaultPage) {
            $path = array(static::$defaultPage);
        }

        // crawl down path stack until a handler is found
        $searchPath = array('site-root');
        while ($handle = array_shift($path)) {
            $foundNode = null;

            // if path component doesn't already end in .php, check for a .php match first
            if (substr($handle, -4) != '.php') {
                array_push($searchPath, $handle.'.php');
                $foundNode = static::resolvePath($searchPath);
                array_pop($searchPath);

                // don't match a collection as a script
                if ($foundNode && !is_a($foundNode, 'SiteFile')) {
                    $foundNode = null;
                }
            }

            // if no script node, try to match provided handle
            if (!$foundNode) {
                array_push($searchPath, $handle);
                $foundNode = static::resolvePath($searchPath);
                array_pop($searchPath);
            }

            // if no node at all was matched by now, end search
            if (!$foundNode) {
                $resolvedNode = null;
                array_unshift($path, $handle);
                break;
            }

            // a node has been matched
            $resolvedNode = $foundNode;
            $resolvedPath[] = $handle;

            // if matching node is a file, end search
            if (is_a($foundNode, 'SiteFile')) {
                break;
            }

            // node is a collection, continue search
            $searchPath[] = $foundNode->Handle;
        }

        return [
            'resolvedNode' => $resolvedNode,
            'resolvedPath' => $resolvedPath,
            'pathStack' => $path,
            'searchPath' => $searchPath
        ];
    }

    public static function executeScript(SiteFile $_SCRIPT_NODE, $_SCRIPT_EXIT = true)
    {
        // create session
        if (empty($GLOBALS['Session']) && static::$autoCreateSession) {
            $resolvedPath = implode('/', static::$resolvedPath) . '/';

            $createSession = true;
            foreach (static::$skipSessionPaths as $skipSessionPath) {
                if (strpos($resolvedPath, $skipSessionPath) === 0) {
                    $createSession = false;
                    break;
                }
            }

            if ($createSession) {
                $GLOBALS['Session'] = UserSession::getFromRequest();
            }
        }

        if (extension_loaded('newrelic')) {
            if (!empty($GLOBALS['Session'])) {
                newrelic_add_custom_parameter('session.token', $GLOBALS['Session']->Handle);
                newrelic_add_custom_parameter('session.person_id', $GLOBALS['Session']->PersonID);
            }

            newrelic_add_custom_parameter('script_path', $_SCRIPT_NODE->FullPath);
        }

        if (is_callable(static::$onBeforeScriptExecute)) {
            call_user_func(static::$onBeforeScriptExecute, $_SCRIPT_NODE);
        }

        if (class_exists('Emergence\\EventBus')) {
            Emergence\EventBus::fireEvent('beforeScriptExecute', 'Site', array(
                'node' => $_SCRIPT_NODE,
                'exit' => $_SCRIPT_EXIT
            ));
        }

        require($_SCRIPT_NODE->RealPath);

        if ($_SCRIPT_EXIT) {
            exit();
        }
    }

    public static function resolvePath($path, $checkParent = true, $checkCache = true)
    {
        // special case: request for root collection
        if (is_string($path) && (empty($path) || $path == '/')) {
            return new Emergence\DAV\RootCollection();
        }

        // parse path
        if (is_array($path)) {
            $path = implode('/', $path);
        }

        // retrieve from filesystem
        $fs = static::getFilesystem();

        if ($fs->has($path)) {
            $entry = $fs->getMetadata($path);
            $entry += League\Flysystem\Util::pathinfo($entry['path']);

            return $entry['type'] == 'file'
                ? new SiteFile($entry['basename'], $entry)
                : new SiteCollection($entry['basename'], $entry);
        }


        return null;
    }

    public static function loadClass($className)
    {
        $fullClassName = $className;

        // skip if already loaded
        if (
            class_exists($className, false)
            || interface_exists($className, false)
            || in_array($className, static::$_loadedClasses)
        ) {
            return;
        }

        // try to load class PSR-0 style
        if (!preg_match('/^Sabre_/', $className)) {
            if ($lastNsPos = strrpos($className, '\\')) {
                $namespace = substr($className, 0, $lastNsPos);
                $className = substr($className, $lastNsPos + 1);
                $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
            } else {
                $fileName = '';
            }

            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className);
            $classNode = static::resolvePath("php-classes/$fileName.php");
        }

        // fall back to emergence legacy class format
        if (!$classNode && empty($namespace)) {
            // try to load class flatly
            $classNode = static::resolvePath("php-classes/$className.class.php");
        }

        if (!$classNode) {
            return;
            //throw new Exception("Unable to load class '$fullClassName'");
        } elseif (!$classNode->MIMEType == 'application/php') {
            throw new Exception("Class file for '$fullClassName' is not application/php");
        }

        // add to loaded class queue
        static::$_loadedClasses[] = $fullClassName;

        // load source code
        require($classNode->RealPath);

        // try to load config
        if (class_exists($fullClassName, false)) {
            static::loadConfig($fullClassName);

            // invoke __classLoaded
            if (method_exists($fullClassName, '__classLoaded')) {
                call_user_func(array($fullClassName, '__classLoaded'));
            }
        }
    }

    public static function loadConfig($className)
    {
        $cacheKey = 'class-config:'.$className;


        // TODO: re-enable cache
        // if (!$configFiles = Cache::fetch($cacheKey)) {
            $fs = static::getFilesystem();
            $configFiles = array();


            // compute file path for given class name
            if ($lastNsPos = strrpos($className, '\\')) {
                $namespace = substr($className, 0, $lastNsPos);
                $className = substr($className, $lastNsPos + 1);
                $path  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
            } else {
                $path = '';
            }

            $path .= str_replace('_', DIRECTORY_SEPARATOR, $className);


            // look for composite config files first
            $collectionContents = $fs->listContents("php-config/{$path}.config.d");

            foreach ($collectionContents as $child) {
                if ($child['type'] == 'file' && $child['extension'] == 'php') {
                    $configFiles[] = static::$rootPath.'/site/'.$child['path'];
                }
            }

            sort($configFiles);


            // look for primary config file
            if (
                (
                    ($legacyPath = "php-config/{$path}.config.php")
                    && $fs->has($legacyPath)
                )
                || (
                    // Fall back on looking for Old_School_Underscore_Namespacing in root
                    empty($namespace)
                    && $path != $className
                    && ($legacyPath = "php-config/$className.config.php")
                    && $fs->has($legacyPath)
                )
            ) {
                $configFiles[] = static::$rootPath.'/site/'.$legacyPath;
            }


            Cache::store($cacheKey, $configFiles);
        // }


        foreach ($configFiles as $configPath) {
            require($configPath);
        }
    }

    public static function respondNotFound($message = 'Page not found')
    {
        if (is_callable(static::$onNotFound)) {
            call_user_func(static::$onNotFound, $message);
        }

        $notFoundStack = array_filter(static::$requestPath);
        array_unshift($notFoundStack, 'site-root');

        // TODO: cache list of _default/_notfound handlers for given containing collection
        $notFoundNode = null;
        while (count($notFoundStack)) { // last iteration is when site-root is all that's left
            if (
                ($notFoundNode = static::resolvePath(array_merge($notFoundStack, array('_default.php')))) ||
                ($notFoundNode = static::resolvePath(array_merge($notFoundStack, array('_notfound.php'))))
            ) {
                // calculate pathStack and resolvedPath relative to each handler
                static::$pathStack = array_slice(static::$requestPath, count($notFoundStack) - 1);
                static::$resolvedPath = array_slice(static::$requestPath, 0, count($notFoundStack) - 1);

                // false so execution continues if handler doesn't terminate
                static::executeScript($notFoundNode, false);
            }

            array_pop($notFoundStack);
        }

        header('HTTP/1.0 404 Not Found');
        die($message);
    }

    public static function respondBadRequest($message = 'Cannot display resource')
    {
        header('HTTP/1.0 400 Bad Request');
        die($message);
    }

    public static function respondUnauthorized($message = 'Access denied')
    {
        header('HTTP/1.0 403 Forbidden');
        die($message);
    }

    public static function getRootCollection($handle)
    {
        if (!empty(static::$_rootCollections[$handle])) {
            return static::$_rootCollections[$handle];
        }

        return static::$_rootCollections[$handle] = SiteCollection::getOrCreateRootCollection($handle);
    }

    public static function splitPath($path)
    {
        return explode('/', ltrim($path, '/'));
    }

    public static function isUsingHttps()
    {
        if (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https'
        ) {
            return true;
        }

        if (!empty($_SERVER['HTTPS'])) {
            return true;
        }

        return false;
    }

    public static function redirect($path, $get = false, $hash = false)
    {
        if (is_array($path)) {
            $path = implode('/', $path);
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            $url = $path;
        } else {
            $url = (static::isUsingHttps() ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?: Site::getConfig('primary_hostname')).'/'.ltrim($path, '/');
        }

        if ($get) {
            $url .= '?'.(is_array($get) ? http_build_query($get) : $get);
        }

        if ($hash) {
            $url .= '#'.$hash;
        }

        header('Location: '.$url);
        exit();
    }

    public static function redirectPermanent($path, $get = false, $hash = false)
    {
        header('HTTP/1.1 301 Moved Permanently');
        return static::redirect($path, $get, $hash);
    }

    public static function getPath($index = null)
    {
        if ($index === null) {
            return static::$requestPath;
        } else {
            return static::$requestPath[$index];
        }
    }

    public static function matchPath($index, $string)
    {
        return 0==strcasecmp(static::getPath($index), $string);
    }

    public static function normalizePath($filename)
    {
        $filename = str_replace('//', '/', $filename);
        $parts = explode('/', $filename);
        $out = array();
        foreach ($parts as $part) {
            if ($part == '.') {
                continue;
            }

            if ($part == '..') {
                array_pop($out);
                continue;
            }

            $out[] = $part;
        }

        return implode('/', $out);
    }

    public static function getVersionedRootUrl($path)
    {
        if (is_string($path)) {
            $path = static::splitPath($path);
        }

        $fsPath = $path;
        array_unshift($fsPath, 'site-root');
        $url = '/'.implode('/', $path);

        $Node = static::resolvePath($fsPath);

        if ($Node) {
            return $url.'?_sha1='.$Node->SHA1;
        } else {
            return $url;
        }
    }

    public static function getConfig($key = null)
    {
        if ($key) {
            return array_key_exists($key, static::$_config) ? static::$_config[$key] : null;
        } else {
            return static::$_config;
        }
    }

    public static function finishRequest($exit = true)
    {
        if ($exit) {
            exit();
        } else {
            fastcgi_finish_request();
        }
    }

    /**
     * Set the active timezone for the site
     *
     * @param string $timezone Time zone name as defined in the IANA time zone database
     *
     * @return void
     */
    public static function setTimezone($timezone)
    {
        date_default_timezone_set($timezone);
        DB::syncTimezone();

        Emergence\EventBus::fireEvent(
            'timezoneSet',
            __CLASS__,
            array(
                'timezone' => $timezone
            )
        );
    }

    /**
     * Get flysystem interface to site's filesystem
     */
    public static function getFilesystem()
    {
        static $fs;

        if (!$fs) {
            $fs = new League\Flysystem\Filesystem(new League\Flysystem\Adapter\Local(static::$rootPath.'/site'));
        }

        return $fs;
    }

    /**
     * Get Whoops instance
     *
     * @return \Whoops\RunInterface
     */
    public static function getWhoops()
    {
        static $whoops;

        if (!$whoops) {
            $whoops = new \Whoops\Run;
        }

        return $whoops;
    }
}
