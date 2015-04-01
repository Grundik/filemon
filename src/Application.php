<?php

namespace Filemon;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$LOG_LEVEL = 3;

class Application {

  /**
   * @var EntityManager
   */
  protected $_entityMgr;

  public function __construct() {
    global $CONFIG;
    $config = Setup::createAnnotationMetadataConfiguration(array(APPLICATION_PATH."/src"), true);
    $this->_entityMgr = EntityManager::create($CONFIG['db'], $config);
  }

  public function run() {
    global $LOG_LEVEL;
    $opts = getopt('h::m:v:', array('mode:', 'help', 'path:', 'file:', 'verbose:'));
    if (!isset($opts['mode'])) {
      $opts['mode'] = isset($opts['m'])?$opts['m']:'update';
    }

    if (isset($opts['v'])) {
      $LOG_LEVEL = intval($opts['v']);
    } elseif (isset($opts['verbose'])) {
      $LOG_LEVEL = intval($opts['verbose']);
    }
    if (!$LOG_LEVEL) {
      $LOG_LEVEL = 3;
    }

    switch ($opts['mode']) {
      case 'init':
        break;
      case 'add':
        if (!isset($opts['path'])) {
          throw new \Exception("Path not specified");
        }
        $this->_addPath($opts['path']);
        break;
      case 'del':
      case 'delete':
        if (!isset($opts['path'])) {
          throw new \Exception("Path not specified");
        }
        $this->_deletePath($opts['path']);
        break;
      case 'update':
        $this->_scanPath(isset($opts['path'])?$opts['path']:null);
        break;
      case 'json':
        $this->_toJson(isset($opts['path'])?$opts['path']:null);
        break;
      case 'xml':
        $this->_toXml(isset($opts['path'])?$opts['path']:null);
        break;
      case 'ed2k':
        echo $this->_findFile($opts)->getEd2kLink().PHP_EOL;
        break;
      case 'dc':
        echo $this->_findFile($opts)->getTthLink().PHP_EOL;
        break;
      case 'torrent':
        echo $this->_findFile($opts)->getTorrentLink().PHP_EOL;
        break;
      case 'magnet':
        echo $this->_findFile($opts)->getMagnetLink().PHP_EOL;
        break;
      case 'updatemtime':
        $this->updateMtime(isset($opts['path'])?$opts['path']:null);
        break;
      case 'help':
      default:
        echo "Usage: ".$argv[0]." -mode <mode> [mode options]".PHP_EOL.
             "".PHP_EOL;
        //throw new \Exception("Unknown mode: {$opts['mode']}");
    }
  }

  /**
   *
   * @param array $opts
   * @return \Filemon\Model\File
   * @throws \Exception
   */
  protected function _findFile($opts) {
    if (!isset($opts['file'])) {
      throw new \Exception("File not specified");
    }
    $file = $this->_entityMgr->find('Filemon\\Model\\File', $opts['file']);
    if (!$file) {
      throw new \Exception("File not found");
    }
    return $file;
  }

  protected function _eachPath($path, $callback) {
    $repo = $this->_entityMgr->getRepository('Filemon\\Model\\Root');
    $criteria = array('active'=>1);
    if (null!==$path) {
      $criteria['path'] = $path;
    }
    $roots = $repo->findBy($criteria);
    foreach ($roots as $root) {
      $callback($root);
    }
    $this->_entityMgr->flush();
  }

  protected function _scanPath($path) {
    $this->_eachPath($path, function($root){
      $root->scan($this->_entityMgr);
    });
  }

  protected function updateMtime($path) {
    $folderMgr = $this->_entityMgr->getRepository('Filemon\Model\Folder');

    /* @var $folder \Filemon\Model\Folder */
    foreach ($folderMgr->findBy(array('name'=>$path)) as $folder) {
      $folder->findRootPath();
      $folder->updateMtime();
    }
  }


  protected function _toJson($path) {
    \Filemon\printLine("[");
    $first = true;
    $this->_eachPath($path, function ($root) use ($first) {
      if (!$first) {
        \Filemon\printLine(",");
      }
      $root->toJson(1);
      $first = false;
    });
    \Filemon\printLine("]");
  }

  protected function _toXml($path) {
    \Filemon\printLine('<?xml version="1.0" encoding="utf-8" standalone="yes"?>');
    \Filemon\printLine('<FileListing Version="1" Base="/" Generator="FileMon 0.1">');
    $this->_eachPath($path, function ($root){
      $root->toXml(1);
    });
    \Filemon\printLine('</FileListing>');
  }

  protected function _addPath($path) {
    $root = $this->_entityMgr->getRepository('Filemon\\Model\\Root')->findBy(array('path'=>$path));
    if ($root) {
      throw new \Exception("Path {$path} already exists in database");
    }
    $newroot = new Model\Root();
    $newroot->setPath($path);
    $folder = new Model\Folder();
    $folder->setName($path);
    $newroot->setFolder($folder);
    $this->_entityMgr->persist($newroot);
    $this->_entityMgr->flush();
  }

  protected function _deletePath($path) {
    $root = $this->_entityMgr->getRepository('Filemon\\Model\\Root')->findBy(array('path'=>$path));
    if (!$root) {
      throw new \Exception("Path {$path} does not exists in database");
    }
    $this->_entityMgr->remove($root[0]);
    $this->_entityMgr->flush();
  }

  public function runCli() {
    return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($this->_entityMgr);
  }
}

function printLine($msg, $level=0, $verbosity=3) {
  global $LOG_LEVEL;
  if ($verbosity>$LOG_LEVEL) {
    return;
  }
  if ($level) {
    $pad = str_repeat('  ', $level);
  } else {
    $pad = '';
  }
  echo $pad.$msg.PHP_EOL;
}

function jsonEncode($var) {
  return json_encode($var, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}

function xmlEncode($var) {
  if (is_array($var)) {
    $str = array();
    foreach ($var as $key=>$value) {
      $str[] = $key.'="'.htmlspecialchars($value).'"';
    }
    return join(' ', $str);
  } else {
    return htmlspecialchars($var);
  }
}

function hex2base32($hex) {
  return Base32::encode(hex2bin($hex));
}
