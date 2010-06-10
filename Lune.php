<?php
/**
 * Lune - a PHP minimal framework
 *
 * PHP version 5
 *
 * @author    YAMAOKA Hiroyuki
 * @copyright 2009-2010 YAMAOKA Hiroyuki
 * @link      http://github.com/hiroy/lune
 * @license   http://opensource.org/licenses/bsd-license.php New BSD License
 */

class Lune
{
    // configuration
    public static $templateDir = 'templates';
    public static $inputEncoding;
    public static $outputEncoding;
    public static $layoutTemplate;
    public static $layoutContentVarName = 'lune_content_for_layout';
    public static $httpVersion;

    // internally used
    public static $invokedCallbackName;
    protected static $_routes = array(
        'GET' => array(), 'POST' => array(),
        'PUT' => array(), 'DELETE' => array());

    public static function run()
    {
        // path info
        $pathInfo = self::pathInfo();
        $pathInfoPieces = explode('/', strtolower(trim($pathInfo, '/')));

        // routes
        $requestMethod = self::requestMethod();
        $routes = array();
        if (isset(self::$_routes[$requestMethod])) {
            $routes = self::$_routes[$requestMethod];
        }

        // init
        $urlParams = array();
        $req = new Lune_Request();
        $res = new Lune_Response();

        while (count($pathInfoPieces) > 0) {

            $urlPath = '/' . implode('/', $pathInfoPieces);

            if (isset($routes[$urlPath])) {

                $callback = $routes[$urlPath];
                if (is_callable($callback)) {

                    if (is_string($callback) &&
                        strpos('::', $callback) === false) {
                        // function name
                        self::$invokedCallbackName = $callback;
                    }

                    $req->init(array_reverse($urlParams));
                    try {
                        call_user_func($callback, $req, $res);
                    } catch (Exception $e) {
                        // uncaught exception
                        $res->status(500);
                    }
                }
            }
            // not found
            $urlParams[] = array_pop($pathInfoPieces);
        }
        // not found at last
        $res->status(404);
    }

    public static function route($urlPath, $callback)
    {
        self::routeGet($urlPath, $callback);
    }

    public static function routeGet($urlPath, $callback)
    {
        self::$_routes['GET'][$urlPath] = $callback;
    }

    public static function routePost($urlPath, $callback)
    {
        self::$_routes['POST'][$urlPath] = $callback;
    }

    public static function routePut($urlPath, $callback)
    {
        self::$_routes['PUT'][$urlPath] = $callback;
    }

    public static function routeDelete($urlPath, $callback)
    {
        self::$_routes['DELETE'][$urlPath] = $callback;
    }

    public static function env($name)
    {
        $value = getenv($name);
        if ($value === false) {
            $value = null;
        }
        return $value;
    }

    public static function uri()
    {
        $uri = self::env('REQUEST_URI');
        if (is_null($uri)) {
            // IIS
            return self::env('URI');
        }
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return $uri;
    }

    public static function pathInfo()
    {
        $uri = self::uri();
        $scriptName = self::env('SCRIPT_NAME');
        $trimPattern = '';
        if (preg_match('/^' . preg_quote($scriptName, '/') . '/', $uri)) {
            // without mod_rewrite
            $trimPattern = preg_quote($scriptName, '/');
        } else {
            // with mod_rewrite, hiding a file name
            $trimPattern = preg_quote(dirname($scriptName), '/');
        }
        return preg_replace("/^{$trimPattern}/", '', $uri);
    }

    public static function requestMethod()
    {
        $method = strtoupper(self::env('REQUEST_METHOD'));
        if ($method === 'POST' && isset($_POST['_method'])) {
            $pseudoMethod = strtoupper($_POST['_method']);
            if ($pseudoMethod === 'PUT' || $pseudoMethod === 'DELETE') {
                $method = $pseudoMethod;
            }
        }
        return $method;
    }

    public static function template($templateFile)
    {
        return self::$templateDir . DIRECTORY_SEPARATOR . $templateFile;
    }
}

class Lune_Request
{
    protected $_params;
    protected $_urlParams;
    protected $_cookies;

    public function __set($name, $value)
    {
        $this->_params[$name] = $value;
    }

    public function __get($name)
    {
        // only a single value will return
        return $this->param($name);
    }

    public function __isset($name)
    {
        return isset($this->_params[$name]);
    }

    public function init($urlParams)
    {
        $this->_params = $this->_unmagicQuotes($_POST + $_GET);
        $this->_urlParams = $urlParams;
        $this->_cookies = $this->_unmagicQuotes($_COOKIE);
        if (!is_null(Lune::$inputEncoding)) {
            mb_convert_variables(mb_internal_encoding(),
                Lune::$inputEncoding, $this->_params);
        }
    }

    public function param($name, $multiple = false)
    {
        if (isset($this->_params[$name])) {
            if ($multiple === is_array($this->_params[$name])) {
                return $this->_params[$name];
            }
        }
        return null;
    }

    public function urlParam($index)
    {
        if (isset($this->_urlParams[$index])) {
            return $this->_urlParams[$index];
        }
        return null;
    }

    public function cookie($name)
    {
        if (isset($this->_cookies[$name])) {
            return $this->_cookies[$name];
        }
        return null;
    }

    protected function _unmagicQuotes($var)
    {
        if (is_array($var)) {
            return array_map(array($this, __METHOD__), $var);
        }
        if (get_magic_quotes_gpc()) {
            $var = stripslashes($var);
        }
        return str_replace(chr(0), '', $var);
    }
}

class Lune_Response
{
    protected $_vars = array();
    protected $_headers = array();
    protected $_cookies = array();
    protected $_body = '';

    public function __construct()
    {
        ob_start();
    }

    public function __destruct()
    {
        // send headers
        foreach ($this->_headers as $name => $value) {
            if (ctype_digit((string) $name)) {
                // status
                header($value);
            } else {
                header("{$name}: {$value}");
            }
        }
        // send cookies
        foreach ($this->_cookies as $c) {
            setcookie($c['name'], $c['value'], $c['expire'],
                $c['path'], $c['domain'], $c['secure'], $c['http_only']);
        }
        // output
        echo $this->_body;
        ob_end_flush();
    }

    public function __set($name, $value)
    {
        $this->_vars[$name] = $value;
    }

    public function __get($name)
    {
        if (isset($this->_vars[$name])) {
            return $this->_vars[$name];
        }
        return null;
    }

    public function __isset($name)
    {
        return isset($this->_vars[$name]);
    }

    public function __unset($name)
    {
        if (isset($this->_vars[$name])) {
            unset($this->_vars[$name]);
        }
    }

    public function output($text, $convertEncoding = true)
    {
        if ($convertEncoding && !is_null(Lune::$outputEncoding)) {
            $text = mb_convert_encoding(
                $text, Lune::$outputEncoding, mb_internal_encoding());
        }
        $this->_body .= $text;
    }

    public function render($template = null, $convertEncoding = true)
    {
        if (is_null($template)) {
            if (!is_null(Lune::$invokedCallbackName)) {
                $template = Lune::$invokedCallbackName . '.php';
            } else {
                throw new Lune_Exception('template not defined');
            }
        }

        $text = '';
        try {
            $text = $this->fetch($template);
        } catch (Lune_Exception $e) {
            // template file not found
            $this->status(404);
        }
        $this->output($text, $convertEncoding);
    }

    public function fetch($template)
    {
        if (is_null($template)) {
            throw new Lune_Exception("template not set");
        }

        $layoutTemplate = null;
        if (!is_null(Lune::$layoutTemplate)) {
            // layout template
            $layoutTemplate = Lune::template(Lune::$layoutTemplate);
            if (!is_file($layoutTemplate) ||
                !is_readable($layoutTemplate)) {
                throw new Lune_Exception(
                    "{$layoutTemplate}: not readable");
            }
        }

        $template = Lune::template($template);
        if (!is_file($template) || !is_readable($template)) {
            throw new Lune_Exception("{$template}: not readable");
        }

        extract($this->_vars, EXTR_SKIP);

        ob_start();
        ob_implicit_flush(false);
        include $template;
        $result = ob_get_clean();

        if (!is_null($layoutTemplate)) {
            // layout
            ${Lune::$layoutContentVarName} = $result;
            ob_start();
            ob_implicit_flush(false);
            include $layoutTemplate;
            $result = ob_get_clean();
        }

        return $result;
    }

    public function redirect($url, $statusCode = 302)
    {
        if (substr($url, 0, 1) === '/') {
            $uri = Lune::uri();
            $pathInfo = Lune::pathInfo();
            $base = substr($uri, 0, strlen($uri) - strlen($pathInfo));
            $url = $base . $url;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->status($statusCode);
        $this->header('Location', $url);
        $this->output('<html><head><meta http-equiv="refresh" content="0;'
            . 'url=' . htmlentities($url, ENT_QUOTES) . '"></head></html>');
    }

    public function contentType($type, $charset = null)
    {
        if (!is_null($charset)) {
            $type .= ';charset=' . $charset;
        }
        $this->header('Content-Type', $type);
    }

    public function contentTypeHtml($charset = null)
    {
        $this->contentType('text/html', $charset);
    }

    public function status($code)
    {
        static $messages = array(
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',
            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',  // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is no longer used but still reserved
            307 => 'Temporary Redirect',
            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded'
        );
        if (isset($messages[$code])) {
            $httpVersion = Lune::$httpVersion;
            if ($httpVersion !== '1.1') {
                // HTTP/1.0
                $messages[302] = 'Moved Temporarily';
            }
            $message = $messages[$code];
            $this->header('Status', "{$code} {$message}");
            $this->header(
                null, "HTTP/{$httpVersion} {$code} {$message}");
        }
    }

    public function header($name, $value)
    {
        if (is_null($name)) {
            $this->_headers[] = $value;
        } else {
            $this->_headers[$name] = $value;
        }
    }

    public function cookie($name, $value, $expire = null,
        $path = '/', $domain = '', $secure = false, $httpOnly = false)
    {
        $this->_cookies[] = array(
            'name'      => $name,
            'value'     => $value,
            'expire'    => $expire,
            'path'      => $path,
            'domain'    => $domain,
            'secure'    => $secure ? true : false,
            'http_only' => $httpOnly
        );
    }

    public static function escapeHtml($var)
    {
        if (is_array($var)) {
            return array_map(array(__CLASS__, __METHOD__), $var);
        }
        if (is_scalar($var)) {
            $var = htmlspecialchars(
                $var, ENT_QUOTES, mb_internal_encoding());
        }
        return $var;
    }
}

if (!function_exists('h')) {
    function h($str)
    {
        return Lune_Response::escapeHtml($str);
    }
}

class Lune_Exception extends Exception
{
}

if (count(debug_backtrace()) === 0) {
    // direct access
    $res = new Lune_Response();
    $res->status(403);
}
