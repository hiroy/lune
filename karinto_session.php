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

class karinto_session
{
    const cookie_max_length = 4096;
    protected $_secret_key = 'your app key';
    protected $_vars = array();
    protected $_available = false;
    protected $_cookie_name;
    protected $_cookie_params;

    public function __construct($secret_key)
    {
        $this->_secret_key = $secret_key;
        $this->_available = true;
        // use session settings
        $this->_cookie_name = session_name();
        $this->_cookie_params = session_get_cookie_params();
        $this->_restore();
    }

    public function __destruct()
    {
        if ($this->_available) {
            $this->_save();
        }
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

    public function lifetime($seconds)
    {
        $this->_cookie_params['lifetime'] = $seconds;
    }

    public function destroy()
    {
        $this->_available = false;
        $this->_vars = array();
        $this->_cookie('', time() - 3600);
    }

    protected function _restore()
    {
        if (!isset($_COOKIE[$this->_cookie_name])) {
            // cookie not exists
            return;
        }
        $cookie_data = $_COOKIE[$this->_cookie_name];
        $data_list = explode('--', $cookie_data);
        if (count($data_list) !== 2) {
            // invalid cookie value
            $this->destroy();
            return;
        }
        $vars = @unserialize(base64_decode($data_list[0]));
        if ($vars === false || !is_array($vars)) {
            // broken cookie value
            $this->destroy();
            return;
        }
        if ($this->_digest($vars) !== $data_list[1]) {
            // tampered
            $this->destroy();
            return;
        }
        $this->_vars = $vars;
    }

    protected function _save()
    {
        $expire = time() + $this->_cookie_params['lifetime'];
        $digest = $this->_digest($this->_vars);
        $cookie_data = base64_encode(serialize($this->_vars)) . '--' . $digest;

        if (strlen($cookie_data) > self::cookie_max_length) {
            throw new karinto_exception('session data is too large');
        }

        $this->_cookie($cookie_data, $expire);
    }

    protected function _digest($data)
    {
        $serialized_data = serialize($data);
        return hash_hmac('sha1', $serialized_data, $this->_secret_key);
    }

    protected function _cookie($value, $expire)
    {
        $name = $this->_cookie_name;
        $params = $this->_cookie_params;

        if (empty($params['domain']) && empty($params['secure'])) {
            setcookie($name, $value, $expire, $params['path']);
        } else if (empty($params['secure'])) {
            setcookie($name, $value, $expire,
                $params['path'], $params['domain']);
        } else {
            setcookie($name, $value, $expire,
               $params['path'], $params['domain'], $params['secure']);
        }
    }
}

