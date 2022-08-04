<?php

/**
 * Fastcache storage handler
 *
 * @version 1.0.1.5
 * @package com_multicache
 * @copyright Copyright (C) Multicache.org 2015. All rights reserved.
 * @license GNU GENERAL PUBLIC LICENSE see LICENSE.txt - 
 * @author Wayne DSouza <consulting@OnlineMarketingConsultants.in> - http://OnlineMarketingConsultants.in
 */
defined('JPATH_PLATFORM') or die();

JLoader::register('MulticacheUrlArray', JPATH_ADMINISTRATOR . '/components/com_multicache/lib/multicacheurlarray.php');
JLoader::register('JCacheStoragetemp', JPATH_ROOT . '/administrator/components/com_multicache/lib/storagetemp.php');
/* In speed tests require_once() loads faster than Jloader::register */
// require_once(JPATH_ADMINISTRATOR . '/components/com_multicache/lib/multicacheurlarray.php');
// require_once(JPATH_ROOT . '/administrator/components/com_multicache/lib/storagetemp.php');
// avoid solo loading errors
if (class_exists('JCacheStoragetemp'))
{

    class MulticacheFastcache extends JCacheStoragetemp
    {
    
    }
}
else
{

    class MulticacheFastcache extends JCacheStorage
    {
    
    }
}

jimport('joomla.application.component.model');

class JCacheStorageFastcache extends MulticacheFastcache
{

    protected $_root;

    protected static $_db = null;

    protected static $_dbadmin = null;

    protected $_persistent = false;

    protected $_compress = 0;

    protected $_multicacheurlarray = null;

    protected $_gzip_factor = 0;

    protected $_config = null;

    protected $_groups_excluded = null;

    public function __construct($options = array())
    {

        parent::__construct($options);
        
        if (! defined("FASTCACHEVARMULTICACHEPERSIST"))
        {
            $this->defFastcacheVars();
        }
        if (! isset($this->_groups_excluded))
        {
            $this->_groups_excluded = $this->getExcludedGroups();
        }
        if (self::$_db === null)
        {
            $this->getConnection();
        }
        $this->_root = $options['cachebase'];
    
    }

    protected function defFastcacheVars()
    {

        /*
         * if(!defined('MEMCACHE_COMPRESSED'))
         * {
         * define('MEMCACHE_COMPRESSED' , 2);
         * }
         */
        if ($this->_config === null)
        {
            $this->_config = JFactory::getConfig();
        }
        if ($this->_multicacheurlarray === null && class_exists('MulticacheUrlArray'))
        {
            
            $this->_multicacheurlarray = MulticacheUrlArray::$urls; // moving to static property to test time for load
        }
        if (class_exists('JCacheStoragetemp'))
        {
            define('FASTCACHEVARMULTICACHESTORAGETEMP', true);
        }
        else
        {
            define('FASTCACHEVARMULTICACHESTORAGETEMP', false);
        }
        
        define('FASTCACHEVARMULTICACHEPERSIST', $this->_config->get('multicache_persist', true));
        define('FASTCACHEVARMULTICACHECOMPRESS', $this->_config->get('multicache_compress', false) == false ? 0 : MEMCACHE_COMPRESSED);
        define('FASTCACHEVARMULTICACHEGZIPFACTOR', $this->_config->get('gzip_factor', false));
        define('FASTCACHEVARMULTICACHESERVERHOST', $this->_config->get('multicache_server_host', 'localhost'));
        define('FASTCACHEVARMULTICACHESERVERPORT', $this->_config->get('multicache_server_port', 11211));
        define('FASTCACHEVARMULTICACHEDISTRIBUTION', $this->_config->get('multicachedistribution', 0));
        define('FASTCACHEVARMULTICACHECACHETIME', $this->_config->get('cachetime', 15));
        define('FASTCACHEVARMULTICACHEFORCELOCKINGOFF', $this->_config->get('force_locking_off', true));
        define('FASTCACHEVARMULTICACHE_DEBUG', $this->_multicacheurlarray['fastcache-debug']);
        if (isset($this->_multicacheurlarray[strtolower(JURI::current())]))
        {
            define('FASTCACHEVARMULTICACHELOWERURLISSET', true);
        }
        else
        {
            define('FASTCACHEVARMULTICACHELOWERURLISSET', false);
        }
        if (isset($this->_multicacheurlarray[JURI::current()]))
        {
            define('FASTCACHEVARMULTICACHEUAURLISSET', true);
        }
        else
        {
            define('FASTCACHEVARMULTICACHEUAURLISSET', false);
        }
        if ((extension_loaded('memcache') && class_exists('Memcache')))
        {
            define('FASTCACHEVARMULTICACHE_MEMCACHEREADY', true);
        }
        else
        {
            define('FASTCACHEVARMULTICACHE_MEMCACHEREADY', false);
        }
    
    }

    protected function getExcludedGroups()
    {

        Return array(
            'com_content',
            'mod_menu',
            'mod_roksprocket',
            'mod_custom',
            'mod_breadcrumbs',
            'com_tags'
        );
    
    }

    protected function getConnection()
    {

        if (! FASTCACHEVARMULTICACHE_MEMCACHEREADY || ! FASTCACHEVAR_CACHEHANDLER)
        {
            if (! defined('MULTICACHE_MEMCACHE_READY_TESTED'))
            {
                define('MULTICACHE_MEMCACHE_READY_TESTED', false);
            }
            return false;
        }
        
        // $this->_persistent = $this->_config->get('multicache_persist', true);
        $this->_persistent = FASTCACHEVARMULTICACHEPERSIST;
        // $this->_compress = $this->_config->get('multicache_compress', false) == false ? 0 : MEMCACHE_COMPRESSED;
        $this->_compress = FASTCACHEVARMULTICACHECOMPRESS;
        
        // $this->_gzip_factor = $this->_config->get('gzip_factor', false);
        $this->_gzip_factor = FASTCACHEVARMULTICACHEGZIPFACTOR;
        
        $server = array();
        // $server['host'] = $this->_config->get('multicache_server_host', 'localhost');
        $server['host'] = FASTCACHEVARMULTICACHESERVERHOST;
        // $server['port'] = $this->_config->get('multicache_server_port', 11211);
        $server['port'] = FASTCACHEVARMULTICACHESERVERPORT;
        
        self::$_db = new Memcache();
        self::$_db->addServer($server['host'], $server['port'], $this->_persistent);
        // compression on fastlz - default is 1.3 or 23% herein 20% and thres @ 2000:: in simulations 0 gzip factor is joomla default render
        if ($this->_gzip_factor)
        {
            self::$_db->setCompressThreshold(2000, $this->_gzip_factor);
        }
        
        $memcachetest = @self::$_db->connect($server['host'], $server['port']);
        
        if ($memcachetest == false)
        {
            // throw new RuntimeException('Could not connect to memcache server', 404);
            if (! defined('MULTICACHE_MEMCACHE_READY_TESTED'))
            {
                define('MULTICACHE_MEMCACHE_READY_TESTED', false);
            }
        }
        if (! defined('MULTICACHE_MEMCACHE_READY_TESTED'))
        {
            define('MULTICACHE_MEMCACHE_READY_TESTED', true);
        }
        return;
    
    }

    public function get($id, $group, $checkTime = true)
    {
        
        // $modemem = $this->_config->get('multicachedistribution', 0);
        $modemem = FASTCACHEVARMULTICACHEDISTRIBUTION;
        $c_h = MULTICACHE_MEMCACHE_READY_TESTED;
        
        if ($modemem == 3)
        :
            // hammered pagespeed allows all variants for a particular url. strtolower
            if (! $this->groupExcluded($group) && ($group != "page" || FASTCACHEVARMULTICACHELOWERURLISSET) && $c_h)
            :
                $cache_id = $this->_getCacheId($id, $group);
                $back = self::$_db->get($cache_id);
                return $back;
            
            
            else
            :
                $back = $this->getfilecache($id, $group, $checkTime);
                Return $back;
            endif;
        
        
        elseif ($modemem == 2)
        :
            if (FASTCACHEVARMULTICACHEUAURLISSET && $c_h)
            :
                $cache_id = $this->_getCacheId($id, $group);
                $back = self::$_db->get($cache_id);
                return $back;
            
            
            else
            :
                $back = $this->getfilecache($id, $group, $checkTime);
                Return $back;
            endif;
        
        
        elseif ($modemem == 1)
        :
            if (! JFactory::getApplication()->isAdmin() && ($group != "page" || FASTCACHEVARMULTICACHEUAURLISSET) && $c_h)
            :
                $cache_id = $this->_getCacheId($id, $group);
                $back = self::$_db->get($cache_id);
                return $back;
            
            
            else
            :
                $back = $this->getfilecache($id, $group, $checkTime);
                Return $back;
            endif;
        
        
        else
        :
            if (($group != "page" || FASTCACHEVARMULTICACHEUAURLISSET) && $c_h)
            :
                if (FASTCACHEVARMULTICACHESTORAGETEMP)
                :
                    $cache_id = $this->_getCacheIdb($id, $group);
                
                
                else
                :
                    $cache_id = $this->_getCacheId($id, $group);
                endif;
                
                $back = self::$_db->get($cache_id);
                return $back;
            
            
            else
            :
                $back = $this->getfilecache($id, $group, $checkTime);
                Return $back;
            endif;
        endif;
        //
        
        $cache_id = $this->_getCacheId($id, $group);
        $back = self::$_db->get($cache_id);
        return $back;
    
    }

    public function store($id, $group, $data)
    {
        
        // $modemem = $this->_config->get('multicachedistribution', 0);
        $modemem = FASTCACHEVARMULTICACHEDISTRIBUTION;
        $c_h = MULTICACHE_MEMCACHE_READY_TESTED;
        if ($modemem == 3)
        :
            if (! $this->groupExcluded($group) && ($group != "page" || FASTCACHEVARMULTICACHELOWERURLISSET) && $c_h)
            :
            
            
            else
            :
                $status = $this->putinfilecache($id, $group, $data);
                Return;
            endif;
        
        
        elseif ($modemem == 2)
        :
            if (FASTCACHEVARMULTICACHEUAURLISSET && $c_h)
            :
            
            
            else
            :
                $status = $this->putinfilecache($id, $group, $data);
                Return;
            endif;
        
        
        elseif ($modemem == 1)
        :
            if (! JFactory::getApplication()->isAdmin() && ($group != "page" || FASTCACHEVARMULTICACHEUAURLISSET) && $c_h)
            :
            
            
            else
            :
                $status = $this->putinfilecache($id, $group, $data);
                Return;
            endif;
        
        
        else
        :
            if (($group != "page" || FASTCACHEVARMULTICACHEUAURLISSET) && $c_h)
            :
            
            
            else
            :
                $status = $this->putinfilecache($id, $group, $data);
                Return;
            endif;
        endif;
        
        if ($modemem == 0 && FASTCACHEVARMULTICACHESTORAGETEMP)
        {
            $cache_id = $this->_getCacheIdb($id, $group);
        }
        else
        {
            $cache_id = $this->_getCacheId($id, $group);
        }
        
        // $lifetime = (int) $this->_config->get('cachetime', 15);
        $lifetime = (int) FASTCACHEVARMULTICACHECACHETIME;
        if ($this->_lifetime == $lifetime)
        {
            $this->_lifetime = $lifetime * 60;
        }
        // $this->_compress = $this->_config->get('multicache_compress', false) == false ? 0 : MEMCACHE_COMPRESSED;
        
        $this->_compress = FASTCACHEVARMULTICACHECOMPRESS;
        
        $result = self::$_db->add($cache_id, $data, $this->_compress, $this->_lifetime);
        
        if (! $result)
        {
            if (FASTCACHEVARMULTICACHE_DEBUG)
            {
                $errormessage = sprintf(JText::_('LIB_FASTCACHE_COM_MULTICACHE_ADDFAILED_TRYING_RESET_STORE'), $id, $group, $cache_id, $this->_lifetime);
                $this->loaderrorlogger($errormessage);
            }
            if (! self::$_db->replace($cache_id, $data, $this->_compress, $this->_lifetime))
            {
                $result = self::$_db->set($cache_id, $data, $this->_compress, $this->_lifetime);
                if (FASTCACHEVARMULTICACHE_DEBUG && ! $result)
                {
                    
                    $errormessage = sprintf(JText::_('LIB_FASTCACHE_COM_MULTICACHE_RESETANDSTORE_FAILEDASWELL'), $id, $group, $cache_id);
                    $this->loaderrorlogger($errormessage);
                }
            }
        }
        
        return true;
    
    }

    public function getAll()
    {

        parent::getAll();
        
        JLoader::import('stat', JPATH_ADMINISTRATOR . '/components/com_multicache/models');
        $comp = JModelLegacy::getInstance('stat', 'MulticacheModel');
        $comp->prepareStat();
        $Allkeys = $comp->getAllKeys();
        
        $sizeobject = $this->getSizeObject();
        $data = $sizestandards = array();
        foreach ($sizeobject as $obj)
        {
            
            $gp = $obj->mgroup;
            $sz = $obj->sz;
            $sizestandards[$gp] = $sz;
        }
        if (FASTCACHEVARMULTICACHE_DEBUG)
        {
            $message = JText::_('LIB_FASTCACHE_GET_ALL_CALLED_MESSAGE');
            $this->loaderrorlogger($message, 'message');
        }
        if (empty($Allkeys))
        {
            if (FASTCACHEVARMULTICACHE_DEBUG)
            {
                $message = JText::_('LIB_FASTCACHE_MULTICACHE_GETALL_NO_KEYS_RECEIVED');
                $this->loaderrorlogger($message);
            }
            // start proc added July 10: its better to split this into a file handler subroutine
            $data = array();
            $this->loadfileStorageClass();
            $options = array(
                'cachebase' => $this->_root
            );
            $f_c_data_raw = new JCacheStorageFile($options);
            foreach ($f_c_data_raw->getAll() as $key => $obj)
            {
                $obj->group = $obj->group . '_file_cache';
                $f_c_data[$key . '_filecache'] = $obj;
            }
            
            if (! empty($data) && ! empty($f_c_data))
            {
                $data = array_merge($data, $f_c_data);
            }
            elseif (empty($data) && ! empty($f_c_data))
            {
                $data = $f_c_data;
            }
            Return $data;
            // end proc
            Return false;
        }
        $secret = $this->_hash;
        $countelements = array();
        
        foreach ($Allkeys as $key)
        {
            $narray = explode('-', $key);
            if ($narray !== false && $narray[0] == $secret && $narray[1] == 'cache')
            {
                $group = $narray[2];
                $countelements[$group] = isset($countelements[$group]) ? ++ $countelements[$group] : 1;
                if (! isset($data[$group]))
                {
                    $item = new JCacheStorageHelper($group);
                }
                else
                {
                    $item = $data[$group];
                }
                $item->updateSize(((int) $sizestandards[$group]) / 1024);
                
                $data[$group] = $item;
            }
        }
        if ($memcachedtest)
        {
            $this->endmemcachedinstance();
        }
        
        $this->loadfileStorageClass();
        $options = array(
            'cachebase' => $this->_root
        );
        $f_c_data_raw = new JCacheStorageFile($options);
        foreach ($f_c_data_raw->getAll() as $key => $obj)
        {
            $obj->group = $obj->group . '_file_cache';
            $f_c_data[$key . '_filecache'] = $obj;
        }
        
        if (! empty($data) && ! empty($f_c_data))
        {
            $data = array_merge($data, $f_c_data);
        }
        elseif (empty($data) && ! empty($f_c_data))
        {
            $data = $f_c_data;
        }
        Return $data;
    
    }

    public function gc()
    {

        $this->loadfileStorageClass();
        $options = array(
            'cachebase' => $this->_root
        );
        $f_c_data_obj = new JCacheStorageFile($options);
        Return $f_c_data_obj->gc();
    
    }

    public function remove($id, $group)
    {

        $c_h = MULTICACHE_MEMCACHE_READY_TESTED;
        $this->loadfileStorageClass();
        $options = array(
            'cachebase' => $this->_root
        );
        
        $f_remove = new JCacheStorageFile($options);
        $flag_f = $f_remove->remove($id, $group);
        
        $cache_id = $this->_getCacheId($id, $group);
        
        if ($group == 'page' && $c_h)
        {
            
            $flag = 0;
            $cache_id_arr = $this->_getAlternateCacheId($id, $group);
            foreach ($cache_id_arr as $cache_id_a)
            {
                if (! empty($cache_id_a))
                {
                    $success_flag = self::$_db->delete($cache_id_a);
                    $flag = $flag || $success_flag;
                }
            }
            $flag = $flag || $flag_f;
            Return $flag;
        }
        //
        if (isset($cache_id) && $c_h)
        {
            
            return self::$_db->delete($cache_id);
        }
        if (FASTCACHEVARMULTICACHE_DEBUG)
        {
            $emessage = sprintf(JText::_('LIB_FASTCACHE_COM_MULTICACHE_REMOVEFAILED_ON_GROUP'), $id, $group);
            $this->loaderrorlogger($emessage, 'notice');
        }
        Return false;
    
    }

    public function clean($group, $mode = null)
    {

        $c_h = MULTICACHE_MEMCACHE_READY_TESTED;
        // @copyright Copyright (C) 2015 OnlineMarketingConsultants.in Coder:WD
        if (stristr($group, '_file_cache'))
        {
            $this->loadfileStorageClass();
            $group = str_ireplace('_file_cache', '', $group);
            $options = array(
                'cachebase' => $this->_root
            );
            $f_clean = new JCacheStorageFile($options);
            $f_clean->clean($group, $mode);
            Return;
        }
        if ($mode == 'both')
        {
            $this->loadfileStorageClass();
            $options = array(
                'cachebase' => $this->_root
            );
            $f_clean = new JCacheStorageFile($options);
            $f_clean->clean($group, $mode);
        }
        if (FASTCACHEVARMULTICACHE_DEBUG)
        {
            
            $e_message = sprintf(JText::_('LIB_FASTCACHE_COM_MULTICACHE_CACHE_CLEAN_CALLED'), $group, $mode);
            $this->loaderrorlogger($e_message, 'message');
        }
        if (! $c_h)
        {
            Return true;
        }
        $memcachedtest = $this->startmemcachedinstance();
        if ($memcachedtest)
        {
            $Allkeys = self::$_dbadmin->getAllKeys();
        }
        else
        {
            JLoader::import('stat', JPATH_ADMINISTRATOR . '/components/com_multicache/models');
            $comp = JModelLegacy::getInstance('stat', 'MulticacheModel');
            $comp->prepareStat();
            $Allkeys = $comp->getAllKeys();
            // $Allkeys = $this->pluckAllKeys();
        }
        
        if (empty($Allkeys))
        {
            Return True;
        }
        
        if ($mode == 'notgroup')
        {
            
            foreach ($Allkeys as $key)
            {
                if (strstr($key, $group))
                :
                
                
                else
                :
                    if ($memcachedtest)
                    :
                        self::$_dbadmin->delete($key, 0);
                    
                    
                    else
                    :
                        self::$_db->delete($key, 0);
                    endif;
                endif;
            }
        }
        else
        {
            foreach ($Allkeys as $key)
            {
                if (strstr($key, $group))
                :
                    if ($memcachedtest)
                    :
                        self::$_dbadmin->delete($key, 0);
                    
                    
                    else
                    :
                        self::$_db->delete($key, 0);
                    endif;
                






                    endif;
            }
        }
        $this->endmemcachedinstance();
        
        Return true;
    
    }

    /**
     * Test to see if the cache storage is available.
     *
     * @return boolean True on success, false otherwise.
     *        
     * @since 12.1
     */
    public static function isSupported()
    {

        if ((extension_loaded('memcache') && class_exists('Memcache')) != true)
        {
            return false;
        }
        
        $config = JFactory::getConfig();
        $host = $config->get('multicache_server_host', 'localhost');
        $port = $config->get('multicache_server_port', 11211);
        
        $memcache = new Memcache();
        $memcachetest = @$memcache->connect($host, $port);
        
        if (! $memcachetest)
        {
            return false;
        }
        else
        {
            return true;
        }
    
    }

    public function lock($id, $group, $locktime)
    {

        $c_h = MULTICACHE_MEMCACHE_READY_TESTED;
        if (FASTCACHEVARMULTICACHEFORCELOCKINGOFF || ! $c_h /*$this->_config->get('force_locking_off',true)*/ )
        {
            Return false;
        }
        // Return false;
        $returning = new stdClass();
        $returning->locklooped = false;
        
        $looptime = $locktime * 10;
        
        $cache_id = $this->_getCacheId($id, $group);
        
        $data_lock = self::$_db->add($cache_id . '_lock', 1, false, $locktime);
        
        if ($data_lock === false)
        {
            
            $lock_counter = 0;
            
            // Loop until you find that the lock has been released.
            // That implies that data get from other thread has finished
            while ($data_lock === false)
            {
                
                if ($lock_counter > $looptime)
                {
                    $returning->locked = false;
                    $returning->locklooped = true;
                    break;
                }
                
                usleep(100);
                $data_lock = self::$_db->add($cache_id . '_lock', 1, false, $locktime);
                $lock_counter ++;
            }
        }
        $returning->locked = $data_lock;
        
        return $returning;
    
    }

    public function unlock($id, $group = null)
    {
        // Return false;
        $cache_id = $this->_getCacheId($id, $group) . '_lock';
        
        return self::$_db->delete($cache_id);
    
    }

    protected function lockindex()
    {

        Return false;
    
    }

    protected function unlockindex()
    {

        Return false;
    
    }

    protected function putinfilecache($id, $group, $data)
    {

        $written = false;
        $path = $this->_getFilePath($id, $group);
        if (file_exists($path))
        {
            // echo "file exists in $path";
            Return true;
        }
        $die = '<?php die("Access Denied"); ?>#x#';
        
        $data = $die . $data;
        
        $_fileopen = @fopen($path, "wb");
        
        if ($_fileopen)
        {
            $len = strlen($data);
            @fwrite($_fileopen, $data, $len);
            $written = true;
        }
        
        if ($written && ($data == file_get_contents($path)))
        {
            return true;
        }
        else
        {
            return false;
        }
    
    }

    protected function _getFilePath($id, $group)
    {

        if (FASTCACHEVARMULTICACHEDISTRIBUTION == 0 && FASTCACHEVARMULTICACHESTORAGETEMP /*$this->_config->get('multicachedistribution', 0) == 0*/ )
        {
            $name = $this->_getCacheIdb($id, $group);
        }
        else
        {
            $name = $this->_getCacheId($id, $group);
        }
        
        $dir = $this->_root . '/' . $group;
        
        // If the folder doesn't exist try to create it
        if (! is_dir($dir))
        {
            
            // Make sure the index file is there
            $indexFile = $dir . '/index.html';
            @mkdir($dir) && file_put_contents($indexFile, '<!DOCTYPE html><title></title>');
        }
        
        // Make sure the folder exists
        if (! is_dir($dir))
        {
            return false;
        }
        return $dir . '/' . $name . '.php';
    
    }

    protected function getfilecache($id, $group, $checkTime = true)
    {

        $data = false;
        
        $path = $this->_getFilePath($id, $group);
        
        if ($checkTime == false || ($checkTime == true && $this->_checkExpire($id, $group) === true))
        {
            if (file_exists($path))
            {
                $data = file_get_contents($path);
                if ($data)
                {
                    // Remove the initial die() statement
                    $data = str_replace('<?php die("Access Denied"); ?>#x#', '', $data);
                }
            }
            
            return $data;
        }
        else
        {
            return false;
        }
    
    }

    protected function _checkExpire($id, $group)
    {

        $path = $this->_getFilePath($id, $group);
        
        // Check prune period
        if (file_exists($path))
        {
            $time = @filemtime($path);
            if (($time + $this->_lifetime) < $this->_now || empty($time))
            {
                @unlink($path);
                return false;
            }
            return true;
        }
        return false;
    
    }

    protected function loaderrorlogger($emessage = null, $type = null)
    {

        if (! defined('LOGGER_READY'))
        {
            jimport('joomla.log.log');
            JLog::addLogger(array(
                'text_file' => 'fastcache.multicache-library.errors.php'
            ), JLog::ALL, array(
                'fastcache_multicache'
            ));
            define('LOGGER_READY', TRUE);
        }
        if (! empty($emessage))
        {
            if (isset($type) && $type == 'message')
            {
                JLog::add(JText::_($emessage), JLog::INFO);
            }
            elseif (isset($type) && $type == 'error')
            {
                JLog::add(JText::_($emessage), JLog::ERROR);
            }
            elseif (isset($type) && $type == 'notice')
            {
                JLog::add(JText::_($emessage), JLog::NOTICE);
            }
            else
            {
                JLog::add(JText::_($emessage), JLog::WARNING);
            }
        }
    
    }

    protected function getSizeObject()
    {

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName('mgroup'));
        $query->select(' AVG(' . $db->quoteName('size') . ') As sz ');
        $query->from($db->quoteName('#__multicache_itemscache'));
        $query->where($db->quoteName('mgroup') . '  !=  ' . $db->quote(''));
        $query->group($db->quoteName('mgroup'));
        $db->setQuery($query);
        Return $db->loadObjectlist();
    
    }

    protected function startmemcachedinstance()
    {

        $config = JFactory::getConfig();
        
        if (! (class_exists('Memcached') && extension_loaded('memcached')))
        {
            if (FASTCACHEVARMULTICACHE_DEBUG)
            {
                
                $errormessage = JText::_('LIB_FASTCACHE_MEMCACHED_NOTLOADED_STARTMEMCACHEDINSTANCE');
                $this->loaderrorlogger($errormessage);
            }
            Return False;
        }
        if (self::$_dbadmin === null)
        :
            $server = array();
            $server['host'] = $config->get('multicache_server_host', 'localhost');
            $server['port'] = $config->get('multicache_server_port', 11211);
            self::$_dbadmin = new Memcached();
            $memcachedtest = self::$_dbadmin->addServer($server['host'], $server['port']);
        



                endif;
        if (! $memcachedtest)
        {
            if (FASTCACHEVARMULTICACHE_DEBUG)
            {
                $errormessage = 'LIB_FASTCACHE_COM_MULTICACHE_MEMCACHED_TEST_FAILED_STARTMEMCACHEDINSTANCE';
                
                $this->loaderrorlogger($errormessage);
            }
            Return False;
        }
        
        Return $memcachedtest;
    
    }

    protected function endmemcachedinstance()
    {

        if (self::$_dbadmin != null)
        :
            self::$_dbadmin->quit();
            self::$_dbadmin = null;
        





        endif;
    
    }

    protected function loadfileStorageClass()
    {

        if (! class_exists('JCacheStorageFile'))
        {
            JLoader::register('JCacheStorageFile', dirname(__FILE__) . '/file.php');
        }
    
    }

    protected function groupExcluded($group = null)
    {

        if (! isset($group))
        {
            Return false;
        }
        
        foreach ($this->_groups_excluded as $groupexc)
        {
            if (stristr($group, $groupexc))
            {
                Return true;
            }
        }
        Return false;
    
    }

}