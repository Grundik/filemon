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
    $opts = getopt('h::m', array('mode:', 'help', 'path:'));
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
      default:
        throw new \Exception("Unknown mode: {$opts['mode']}");
    }
  }

  protected function _scanPath($path) {
    $repo = $this->_entityMgr->getRepository('Filemon\\Model\\Root');
    $criteria = array('active'=>1);
    if (null!==$path) {
      $criteria['path'] = $path;
    }
    $roots = $repo->findBy($criteria);
    foreach ($roots as $root) {
      $root->scan($this->_entityMgr);
    }
    $this->_entityMgr->flush();
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
