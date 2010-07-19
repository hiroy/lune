<?php
/**
 * Lune_Session - a cookie-stored session class
 *
 * Usage:
 *   $session = new Lune_Session('your secret key');
 *   $session->foo = 'bar';
 *   $session->save();
 *
 * PHP version 5
 *
 * @author    YAMAOKA Hiroyuki
 * @copyright 2010 YAMAOKA Hiroyuki
 * @link      http://github.com/hiroy/lune
 * @license   http://opensource.org/licenses/bsd-license.php New BSD License
 */
class Lune_Session
{
    const COOKIE_MAX_LENGTH = 4096;

    protected $_secretKey;
    protected $_vars = array();
    protected $_isAvailable = false;
    protected $_cookieName;
    protected $_cookieParams;

    public function __construct($secretKey)
    {
        $this->_secretKey = $secretKey;
        $this->_isAvailable = true;

        // use session settings
        $this->_cookieName = session_name();
        $this->_cookieParams = session_get_cookie_params();
        $this->_restore();
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

    public function save()
    {
        if (!$this->_isAvailable) {
            return;
        }
        $expire = time() + $this->cookieParam['lifetime'];
        $cookieData = base64_encode(serialize($this->_vars))
                    . '--' . $this->_digest($this->_vars);
        if (strlen($cookieData) > self::COOKIE_MAX_LENGTH) {
            throw new Exception('The session data is too large.');
        }
        $this->_sendCookie($cookieData, $expire);
    }

    public function setLifetime($seconds)
    {
        $this->_cookieParams['lifetime'] = $seconds;
    }

    public function destroy()
    {
        $this->_isAvailable = false;
        $this->_vars = array();
        $this->_sendCookie('', time() - 3600);
    }

    protected function _restore()
    {
        if (!isset($_COOKIE[$this->cookieName])) {
            // cookie not exists
            return;
        }
        $cookieData = $_COOKIE[$this->_cookieName];
        $dataList = explode('--', $cookieData);
        if (count($dataList) !== 2) {
            // invalid cookie value
            $this->destroy();
            return;
        }
        $vars = unserialize(base64_decode($dataList[0]));
        if ($vars === false || !is_array($vars)) {
            // broken cookie value
            $this->destroy();
            return;
        }
        if ($this->_digest($vars) !== $dataList[1]) {
            // tampered
            $this->destroy();
            return;
        }
        $this->_vars = $vars;
    }

    protected function _digest($data)
    {
        $serializedData = serialize($data);
        return hash_hmac('sha1', $serializedData, $this->_secretKey);
    }

    protected function _sendCookie($value, $expire)
    {
        $name = $this->_cookieName;
        $params = $this->_cookieParams;

        if (empty($params['domain']) && empty ($params['secure'])) {
            setcookie($name, $value, $expire, $params['path']);
        } elseif (empty($params['secure'])) {
            setcookie($name, $value, $expire,
                $params['path'], $params['domain']);
        } else {
            setcookie($name, $value, $expire,
                $params['path'], $params['domain'], $params['secure']);
        }
    }
}
