<?php
class Lune_Cache_File
{
    protected $_cacheDir;

    public function __construct($cacheDir)
    {
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $this->_cacheDir = $cacheDir;
        }
    }

    public function save($id, $value, $lifeTime = 3600)
    {
        if (is_null($this->_cacheDir)) {
            throw new Lune_Exception('cache dir not writable');
        }

        $data = array('value' => $value, 'expire' => time() + $lifeTime);
        $file = $this->_filePath($id);

        $result = file_put_contents($file, serialize($data));
        if ($result === false) {
            throw new Lune_Exception('unable to write:' . $file);
        }
    }

    public function get($id)
    {
        if (is_null($this->_cacheDir)) {
            throw new Lune_Exception('cache dir not writable');
        }

        $file = $this->_filePath($id);
        if (is_file($file) && is_readable($file)) {
            $data = unserialize(file_get_contents($file));
            if (!isset($data['value'], $data['expire']) ||
                $data['expire'] < time()) {
                // invalid format or expired
                try {
                    $this->remove($id);
                } catch (Lune_Exception $e) {
                    throw $e;
                }
                return null;
            }
            return $data['value'];
        }

        // not cached
        return null;
    }

    public function remove($id)
    {
        if (is_null($this->_cacheDir)) {
            throw new Lune_Exception('cache dir not writable');
        }

        $file = $this->_filePath($id);
        if (file_exists($file)) {
            $result = unlink($file);
            if ($result === false) {
                throw new Lune_Exception('unable to unlink:' . $file);
            }
            return true;
        }

        // not cached
        return false;
    }

    public function clean()
    {
        if (is_null($this->_cacheDir)) {
            throw new Lune_Exception('cache dir not writable');
        }

        $dh = opendir($this->_cacheDir);
        if ($dh === false) {
            throw new Lune_Exception('unable to open cache dir');
        }

        while (($file = readdir($dh)) !== false) {
            if ($file === '.' || $file === '..' ||
                !is_file($this->_cacheDir . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }
            if (preg_match('/^lune_cache_/', $file)) {
                $file = $this->_cacheDir . DIRECTORY_SEPARATOR . $file;
                unlink($file);
            }
        }

        closedir($dh);
    }

    protected function _filePath($id)
    {
        return $this->_cacheDir . DIRECTORY_SEPARATOR
            . 'lune_cache_' . md5("lune_{$id}_cache");
    }
}
