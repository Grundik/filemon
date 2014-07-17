<?php

namespace Filemon;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

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
    $opts = getopt('h::m', array('mode:', 'help', 'path:', 'file:'));
    if (!isset($opts['mode'])) {
      $opts['mode'] = isset($opts['m'])?$opts['m']:'update';
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
        if (!isset($opts['file'])) {
          throw new \Exception("File not specified");
        }
        $file = $this->_entityMgr->find('Filemon\\Model\\File', $opts['file']);
        if (!$file) {
          throw new \Exception("File not found");
        }
        echo $file->getEd2kLink();
        break;
      default:
        throw new \Exception("Unknown mode: {$opts['mode']}");
    }
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

function printLine($msg, $level=0) {
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
