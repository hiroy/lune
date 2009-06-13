<?php
/**
 * karinto - the micro-framework for PHP web apps
 *
 * PHP version 5
 *
 * @author    YAMAOKA Hiroyuki
 * @copyright 2008-2009 YAMAOKA Hiroyuki
 * @link      http://github.com/hiroy/karinto
 * @license   http://opensource.org/licenses/bsd-license.php New BSD License
 */

class karinto
{
    // configuration
    public static $template_dir = 'templates';
    public static $function_dir;
    public static $input_encoding;
    public static $output_encoding;
    public static $layout_template;
    public static $layout_content_var_name = 'karinto_content_for_layout';
    public static $http_version = '1.1';

    // used internally
    public static $routes_get = array();
    public static $routes_post = array();
    public static $routes_put = array();
    public static $routes_delete = array();
    public static $invoked_function_name;

    public static function route($url_path, $function)
    {
        self::route_get($url_path, $function);
    }

    public static function route_get($url_path, $function)
    {
        self::$routes_get[$url_path] = $function;
    }

    public static function route_post($url_path, $function)
    {
        self::$routes_post[$url_path] = $function;
    }

    public static function route_put($url_path, $function)
    {
        self::$routes_put[$url_path] = $function;
    }

    public static function route_delete($url_path, $function)
    {
        self::$routes_delete[$url_path] = $function;
    }

    public static function run()
    {
        // path_info
        $path_info = self::path_info();
        $path_info_pieces = explode('/', strtolower(trim($path_info, '/')));

        // routes
        $routes = array();
        switch (self::request_method()) {
        case 'GET':
            $routes = self::$routes_get;
            break;
        case 'POST':
            $routes = self::$routes_post;
            break;
        case 'PUT':
            $routes = self::$routes_put;
            break;
        case 'DELETE':
            $routes = self::$routes_delete;
            break;
        }

        // function_dir?
        $use_function_dir = strlen(self::$function_dir) > 0;

        // init
        $url_params = array();
        $req = new karinto_request();
        $res = new karinto_response();

        while (count($path_info_pieces) > 0) {
            $url_path = '/' . implode('/', $path_info_pieces);
            if (isset($routes[$url_path])) {
                $function = $routes[$url_path];
                if ($use_function_dir && !function_exists($function)) {
                    // try to load a file
                    $file = self::$function_dir .
                        DIRECTORY_SEPARATOR . $function . '.php';
                    if (is_file($file) && is_readable($file)) {
                        include_once $file;
                    }
                }
                if (function_exists($function)) {
                    // invoke
                    self::$invoked_function_name = $function;
                    $url_params = array_reverse($url_params);
                    $req->init($url_params);
                    try {
                        $function($req, $res);
                    } catch (Exception $e) {
                        // uncaught exception
                        $res->status(500);
                    }
                    return;
                }
            }
            // not found
            $url_params[] = array_pop($path_info_pieces);
        }
        // not found at last
        $res->status(404);
    }

    public static function env($name)
    {
        $value = getenv($name);
        if ($value === false) {
            $value = null;
        }
        return str_replace(chr(0), '', $value);
    }

    public static function uri()
    {
        if (getenv('REQUEST_URI') === false) {
            // IIS
            return self::env('URI');
        }
        $uri = self::env('REQUEST_URI');
        $delim_pos = strpos($uri, '?');
        if ($delim_pos !== false) {
            $uri = substr($uri, 0, $delim_pos);
        }
        return $uri;
    }

    public static function path_info()
    {
        $uri = self::uri();
        $script_name = self::env('SCRIPT_NAME');
        $trim_pattern = '';
        if (preg_match('/^' . preg_quote($script_name, '/') . '/', $uri)) {
            // without mod_rewrite
            $trim_pattern = preg_quote($script_name, '/');
        } else {
            // with mod_rewrite, hiding a file
            $trim_pattern = preg_quote(dirname($script_name), '/');
        }
        return preg_replace("/^{$trim_pattern}/", '', $uri);
    }

    public static function request_method()
    {
        $method = strtoupper(self::env('REQUEST_METHOD'));
        if ($method === 'POST' &&
            isset($_POST['_method']) &&
            is_string($_POST['_method'])) {
            switch (strtoupper($_POST['_method'])) {
            case 'PUT':
                $method = 'PUT';
                break;
            case 'DELETE':
                $method = 'DELETE';
                break;
            }
        }
        return $method;
    }

    public static function template($template_file)
    {
        return self::$template_dir . DIRECTORY_SEPARATOR . $template_file;
    }
}

class karinto_request
{
    protected $_params = array();
    protected $_url_params = array();
    protected $_cookies = array();

    public function __set($name, $value)
    {
        $this->_params[$name] = $value;
    }

    public function __get($name)
    {
        // only a single value is returned
        return $this->param($name);
    }

    public function __isset($name)
    {
        return isset($this->_params[$name]);
    }

    public function init(array $url_params)
    {
        $this->_params = $this->_unmagic_quotes($_POST + $_GET);
        $this->_url_params = $url_params;
        $this->_cookies = $this->_unmagic_quotes($_COOKIE);
        if (!is_null(karinto::$input_encoding)) {
            mb_convert_variables(mb_internal_encoding(),
                karinto::$input_encoding, $this->_params);
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

    public function url_param($index)
    {
        if (isset($this->_url_params[$index])) {
            return $this->_url_params[$index];
        }
        return null;
    }

    public function put_raw_params()
    {
        if (karinto::request_method() !== 'PUT') {
            return null;
        }
        if (isset($_POST['_method']) &&
            is_string($_POST['_method']) &&
            strtoupper($_POST['_method']) === 'PUT') {
            // raw POST data (not converted)
            $put_params = $_POST;
            unset($put_params['_method']);
        } else {
            parse_str(file_get_contents('php://input', $put_params));
        }
        return $put_params;
    }

    public function delete_raw_params()
    {
        if (karinto::request_method() !== 'DELETE') {
            return null;
        }
        if (isset($_POST['_method']) &&
            is_string($_POST['_method']) &&
            strtoupper($_POST['_method']) === 'DELETE') {
            // raw POST data (not converted)
            $delete_params = $_POST;
            unset($delete_params['_method']);
        } else {
            parse_str(file_get_contents('php://input', $delete_params));
        }
        return $delete_params;
    }

    public function cookie($name)
    {
        if (isset($this->_cookies[$name])) {
            return $this->_cookies[$name];
        }
        return null;
    }

    protected function _unmagic_quotes($var)
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

class karinto_response
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

    public function output($text, $convert_encoding = true)
    {
        if ($convert_encoding && !is_null(karinto::$output_encoding)) {
            $text = mb_convert_encoding(
                $text, karinto::$output_encoding, mb_internal_encoding());
        }
        $this->_body .= $text;
    }

    public function render($template = null, $convert_encoding = true)
    {
        $text = '';
        if (is_null($template) &&
            !is_null(karinto::$invoked_function_name)) {
            $template = karinto::$invoked_function_name . '.php';
        }
        try {
            $text = $this->fetch($template);
        } catch (karinto_exception $e) {
            // template file not found
            $this->status(404);
        }
        $this->output($text, $convert_encoding);
    }

    public function fetch($template, $html_escape = true)
    {
        if (is_null($template)) {
            throw new karinto_exception("template not set");
        }

        $layout_template = null;
        if (!is_null(karinto::$layout_template)) {
            // layout template
            $layout_template = karinto::template(karinto::$layout_template);
            if (!is_file($layout_template) ||
                !is_readable($layout_template)) {
                throw new karinto_exception(
                    "{$layout_template}: not readable");
            }
        }

        $template = karinto::template($template);
        if (!is_file($template) || !is_readable($template)) {
            throw new karinto_exception("{$template}: not readable");
        }
        if ($html_escape) {
            extract($this->escape_html($this->_vars), EXTR_SKIP);
        } else {
            extract($this->_vars, EXTR_SKIP);
        }
        ob_start();
        ob_implicit_flush(false);
        include $template;
        $result = ob_get_clean();

        if (!is_null($layout_template)) {
            // layout
            ${karinto::$layout_content_var_name} = $result;
            ob_start();
            ob_implicit_flush(false);
            include $layout_template;
            $result = ob_get_clean();
        }

        return $result;
    }

    public function redirect($url, $status_code = 302)
    {
        if (substr($url, 0, 1) === '/') {
            $uri = karinto::uri();
            $path_info = karinto::path_info();
            $base = substr($uri, 0, strlen($uri) - strlen($path_info));
            $url = $base . $url;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->status($status_code);
        $this->header('Location', $url);
        $this->output('<html><head><meta http-equiv="refresh" content="0;'
            . 'url=' . htmlentities($url, ENT_QUOTES) . '"></head></html>');
    }

    public function content_type($type, $charset = null)
    {
        if (!is_null($charset)) {
            $type .= ';charset=' . $charset;
        }
        $this->header('Content-Type', $type);
    }

    public function content_type_html($charset = null)
    {
        $this->content_type('text/html', $charset);
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
            $http_version = karinto::$http_version;
            if ($http_version !== '1.1') {
                // HTTP/1.0
                $messages[302] = 'Moved Temporarily';
            }
            $message = $messages[$code];
            $this->header('Status', "{$code} {$message}");
            $this->header(
                null, "HTTP/{$http_version} {$code} {$message}");
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
        $path = '/', $domain = '', $secure = false, $http_only = false)
    {
        $this->_cookies[] = array(
            'name'      => $name,
            'value'     => $value,
            'expire'    => $expire,
            'path'      => $path,
            'domain'    => $domain,
            'secure'    => $secure ? true : false,
            'http_only' => $http_only
        );
    }

    public function escape_html($value)
    {
        if (is_array($value)) {
            return array_map(array($this, __METHOD__), $value);
        }
        if (is_scalar($value)) {
            $value = htmlspecialchars(
                $value, ENT_QUOTES, mb_internal_encoding());
        }
        return $value;
    }
}

class karinto_exception extends Exception
{
}

if (count(debug_backtrace()) === 0) {
    // direct access
    $res = new karinto_response();
    $res->status(403);
}

