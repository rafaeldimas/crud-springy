<?php
/**
 * Session treatment class.
 *
 * @copyright 2007 Fernando Val
 * @author    Fernando Val <fernando.val@gmail.com>
 * @license   https://github.com/fernandoval/Springy/blob/master/LICENSE MIT
 *
 * @version   2.1.2.22
 */

namespace Springy;

class Session
{
    /// Flag de controle de sessão iniciada
    private static $started = false;
    /// Session ID
    private static $sid = null;
    /// Session engine type
    private static $type = 'file';
    /// Session cookie name
    private static $name = 'SPRINGYSID';
    /// Session expiration time
    private static $expires = 120;

    /// MemcacheD server address
    private static $mcAddr = '127.0.0.1';
    /// MemcacheD server address
    private static $mcPort = 11211;

    /// Database config name
    private static $database = 'default';
    /// The sessions table name
    private static $dbTable = '_sessions';

    /// The session data
    private static $data = [];

    const ST_STANDARD = 'file';
    const ST_MEMCACHED = 'memcached';
    const ST_DATABASE = 'database';

    /**
     * Loads the session configurations.
     */
    private static function _confLoad()
    {
        $config = Configuration::get('system', 'session');

        // The session engine type
        if (!isset($config['type'])) {
            throw new \Exception('Undefined session type.', 500);
        } elseif ($config['type'] !== self::ST_STANDARD && $config['type'] !== self::ST_MEMCACHED && $config['type'] !== self::ST_DATABASE) {
            throw new \Exception('Invalid session type.', 500);
        }
        self::$type = $config['type'];

        // MemcacheD
        if (self::$type === self::ST_MEMCACHED && isset($config['memcached'])) {
            if (isset($config['memcached']['address'])) {
                self::$mcAddr = $config['memcached']['address'];
            }
            if (isset($config['memcached']['port'])) {
                self::$mcPort = $config['memcached']['port'];
            }
        }

        // Database
        if (self::$type === self::ST_DATABASE && isset($config['database'])) {
            if (isset(self::$config['database']['server'])) {
                self::$database = $config['database']['server'];
            }
            if (isset(self::$config['database']['table'])) {
                self::$dbTable = $config['database']['table'];
            }
        }

        // Session expiration time
        if (isset($config['expires'])) {
            self::$expires = $config['expires'];
        }

        // Session name
        if (isset($config['name'])) {
            self::$name = $config['name'];
        }
    }

    /**
     * Starts the session ID for MemcacheD or dabasese stored sessions.
     */
    private static function _startSessionID()
    {
        if (is_null(self::$sid)) {
            if (!(self::$sid = Cookie::get(session_name()))) {
                self::$sid = substr(md5(uniqid(mt_rand(), true)), 0, 26);
            }
        }

        Cookie::set(session_name(), self::$sid, 0, '/', Configuration::get('system', 'session.domain'), false, false);
    }

    /**
     * Starts the database stored session.
     */
    private static function _startDBSession()
    {
        self::_startSessionID();

        // Expires old sessions
        $db = new DB(self::$database);
        $exp = self::$expires;
        if ($exp == 0) {
            $exp = 86400;
        }
        $db->execute('DELETE FROM ' . self::$dbTable . ' WHERE `updated_at` <= ?', [date('Y-m-d H:i:s', time() - ($exp * 60))]);

        // Load from database
        $db->execute('SELECT `session_value` FROM ' . self::$dbTable . ' WHERE id = ?', [self::$sid]);
        if ($db->affectedRows()) {
            $res = $db->fetchNext();
            self::$data = unserialize($res['session_value']);
        } else {
            $sql = 'INSERT INTO ' . self::$dbTable . '(`id`, `session_value`, `updated_at`) VALUES (?, NULL, NOW())';
            $db->execute($sql, [self::$sid]);
            self::$data = [];
        }

        register_shutdown_function(['\Springy\Session', '_saveDbSession']);
        self::$started = true;
    }

    /**
     * Starts the Memcached stored session.
     */
    private static function _startMCSession()
    {
        self::_startSessionID();

        $memcached = new Memcached();
        $memcached->addServer(self::$mcAddr, self::$mcPort);

        if (!(self::$data = $memcached->get('session_' . self::$sid))) {
            self::$data = [];
        }

        register_shutdown_function(['\Springy\Session', '_saveMcSession']);
        self::$started = true;
    }

    /**
     * Starts the session engine.
     */
    public static function start($name = null)
    {
        if (self::$started) {
            return true;
        }

        self::_confLoad();

        if (!is_null($name)) {
            self::$name = $name;
        }
        session_name(self::$name);

        // Verifica se há um cookie com o nome de sessão setado
        if ($id = Cookie::get(session_name())) {
            // Verifica se há algum caracter inválido no id da sessão
            if (preg_match('/([^A-Za-z0-9\-]+)/', $id)) {
                $id = substr(md5(uniqid(mt_rand(), true)), 0, 26);
                session_id($id);
            }
        } else {
            // Se não há um cookie ou o cookie está vazio, apaga o cookie por segurança
            Cookie::delete(session_name());
        }

        if (self::$type === self::ST_MEMCACHED) {
            self::_startMCSession();
        } elseif (self::$type === self::ST_DATABASE) {
            self::_startDBSession();
        } else {
            session_set_cookie_params(0, '/', Configuration::get('system', 'session.domain'), false, false);
            self::$started = session_start();
            self::$data = isset($_SESSION['_ffw_']) ? $_SESSION['_ffw_'] : [];
            self::$sid = session_id();
        }

        return self::$started;
    }

    /**
     * Saves the session data in database table.
     */
    public static function _saveDbSession()
    {
        $value = serialize(self::$data);
        $sql = 'UPDATE `' . self::$dbTable . '` SET `session_value` = ?, `updated_at` = NOW() WHERE `id` = ?';
        $db = new DB();
        $db->execute($sql, [$value, self::$sid]);
    }

    /**
     * Saves session data in Memcached service.
     */
    public static function _saveMcSession()
    {
        $memcached = new Memcached();
        $memcached->addServer(self::$mcAddr, self::$mcPort);
        $memcached->set('session_' . self::$sid, self::$data, self::$expires * 60);
    }

    /**
     * Define o id da sessão.
     */
    public static function setSessionId($sid)
    {
        self::$sid = $sid;
        if (self::$type != self::ST_DATABASE) {
            session_id($sid);
        }
    }

    /**
     * Informa se a variável de sessão está definida.
     */
    public static function defined($var)
    {
        self::start();

        return isset(self::$data[$var]);
    }

    /**
     * Coloca um valor em variável de sessão.
     */
    public static function set($var, $value)
    {
        self::start();
        self::$data[$var] = $value;
        if (self::$type == self::ST_STANDARD) {
            $_SESSION['_ffw_'][$var] = $value;
        }
    }

    /**
     * Pega o valor de uma variável de sessão.
     */
    public static function get($var)
    {
        self::start();

        return self::$data[$var] ?? null;
    }

    /**
     * Retorna todos os dados armazenados na sessão.
     */
    public static function getAll()
    {
        self::start();

        if (!empty(self::$data)) {
            return self::$data;
        }
    }

    /**
     * Pega o ID da sessão.
     */
    public static function getId()
    {
        self::start();

        return self::$sid;
    }

    /**
     * Remove uma variável de sessão.
     */
    public static function unregister($var)
    {
        self::start();
        unset(self::$data[$var]);
        if (self::$type == self::ST_STANDARD && isset($_SESSION) && isset($_SESSION['_ffw_']) && isset($_SESSION['_ffw_'][$var])) {
            unset($_SESSION['_ffw_'][$var]);
        }
    }
}
