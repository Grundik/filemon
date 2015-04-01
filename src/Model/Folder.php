<?php

namespace Filemon\Model;
use Doctrine\Common\Collections\ArrayCollection;

/** @Entity */
class Folder extends Entry {

  /**
   * @OneToMany(targetEntity="Folder", mappedBy="parent", cascade="all")
   * @OrderBy({"name" = "ASC"})
   * @var Folder[]
   */
  protected $childs;

  /**
   * @OneToMany(targetEntity="File", mappedBy="parent", cascade="all")
   * @OrderBy({"name" = "ASC"})
   * @var File[]
   */
  protected $files;

  public function __construct() {
    $this->childs = new ArrayCollection();
    $this->files = new ArrayCollection();
  }

  public function addFile(File $file) {
    $this->files[] = $file;
    $file->setParent($this);
  }

  public function addChild(Folder $folder) {
    $this->childs[] = $folder;
    $folder->setParent($this);
  }

  public function setChilds($childs) {
    $this->childs = $childs;
  }

  public function getChilds() {
    return $this->childs;
  }

  public function setFiles($files) {
    $this->files = $files;
  }

  public function getFiles() {
    return $this->files;
  }

  protected function _getDirStat($path) {
    if (!file_exists($path) || !is_dir($path) || !is_readable($path)) {
      \Filemon\printLine("$path is not readable directory");
      return null;
    }
    $dir = dir($path);
    if (!$dir) {
      return null;
    }
    $files = array();
    $folders = array();
    $direntries = array();
    while (false !== ($entry = $dir->read())) {
      if ('.'==$entry[0]) {
        continue;
      }
      $direntries[] = $entry;
    }
    $dir->close();
    $cwd = getcwd();
    chdir($path);
    sort($direntries, SORT_STRING);
    foreach ($direntries as $entry) {
      $stat = @stat($entry);
      if (false===$stat) {
        \Filemon\printLine("...cannot stat file {$entry}, skipping");
        continue;
      }
      if (is_dir($entry)) {
        $file = new Folder();
        $folders[] = $file;
      } else {
        $file = new File();
        $file->setSize($stat['size']);
        $file->setMtime($stat['mtime']);
        $file->setCtime($stat['ctime']);
        $files[] = $file;
      }
      $file->setName($entry);
    }
    chdir($cwd);
    return array($files, $folders);
  }

  /**
   *
   * @param Entry[] $haystack
   * @param Entry $entry
   * @return Entry
   */
  protected function _findEntry($haystack, $entry) {
    foreach ($haystack as $v) {
      if ($v->getName() === $entry->getName()) {
        return $v;
      }
    }
    return null;
  }

  public function doUpdate($oldInstance, Folder $container, \Doctrine\ORM\EntityManager $em) {
    $isUpdated = false;
    if ($oldInstance) {
      \Filemon\printLine("known folder {$this->getName()}", 0, 5);
    } else {
      \Filemon\printLine("new folder {$this->getName()}", 0, 4);
      $container->addChild($this);
      $isUpdated = true;
    }
    $this->setRootPath($container->root_path.'/'.$this->getName());
    return $this->scan($em) || $isUpdated;
  }

  protected function _updateList($current, $saved, \Doctrine\ORM\EntityManager $em) {
    $isUpdated = false;
    $scanTime = 0;
    foreach ($current as $entry) {
      $k = $this->_findEntry($saved, $entry);
      if (!$k) {
        $k = $entry;
        $entry = null;
        $em->persist($k);
      }
      $k->_found = true;
      $t = time();
      $isUpdated = $k->doUpdate($entry, $this, $em) || $isUpdated;
      $scanTime += time()-$t;
      if ($k->getDeleted()) {
        \Filemon\printLine("...undeleted", 0, 4);
        $k->setDeleted(null);
        $em->flush($k);
        $isUpdated = true;
      }
      if ($scanTime>5*60) {
        \Filemon\printLine("...saving intermediate status", 0, 6);
        $em->flush($this);
        $scanTime = 0;
        $isUpdated = false;
      }
    }
    $now = time();
    foreach ($saved as $f) {
      if (!$f->_found && !$f->getDeleted()) {
        $type = $f instanceof Folder ? 'folder' : 'file';
        \Filemon\printLine("Lost $type {$f->getName()}", 0, 4);
        $f->setDeleted($now);
        $em->flush($f);
        $isUpdated = true;
      }
    }
    return $isUpdated;
  }

  public function scan(\Doctrine\ORM\EntityManager $em) {
    if (!$this->root_path) {
      throw new \Exception('Internal error: root path not set');
    }
    $folders = $this->getChilds();
    $files = $this->getFiles();
    $entries = $this->_getDirStat($this->root_path);
    if (null===$entries) {
      return false;
    }
    if ($this->_updateList($entries[0], $files, $em)) {
      \Filemon\printLine("...saving status", 0, 6);
      $em->flush($this);
    }
    if ($this->_updateList($entries[1], $folders, $em)) {
      \Filemon\printLine("...saving status", 0, 6);
      $em->flush($this);
    }
    return false;
  }

  public function toJson($level=1) {
    $first = true;
    \Filemon\printLine('{', $level, 0);
    $level++;
    \Filemon\printLine('"name": '.\Filemon\jsonEncode($this->getName()).',', $level, 0);
    \Filemon\printLine('"type": "folder",', $level, 0);
    if ($this->deleted) {
      \Filemon\printLine('"deleted": '.$this->deleted.',', $level, 0);
    }
    \Filemon\printLine('"items": [', $level, 0);
    foreach ($this->getChilds() as $folder) {
      if (!$first) {
        \Filemon\printLine(',', $level, 0);
      }
      $folder->toJson($level);
      $first = false;
    }
    foreach ($this->getFiles() as $file) {
      if (!$first) {
        \Filemon\printLine(',', $level, 0);
      }
      $file->toJson($level);
      $first = false;
    }
    \Filemon\printLine(']', $level, 0);
    \Filemon\printLine('}', $level-1, 0);
  }

  public function toXml($level=1, $inDeletedFolder=false) {
    $prefix = '<Directory ';
    $suffix = '</Directory>';
    $data = array(
      'Name' => $this->getName(),
    );
    if ($this->deleted) {
      $data['Deleted'] = date('c', $this->deleted);
      if (!$inDeletedFolder) {
        $prefix = '<!-- '.$prefix;
        $suffix = $suffix.' -->';
      }
      $inDeletedFolder = true;
    }
    \Filemon\printLine($prefix.\Filemon\xmlEncode($data).'>', $level, 0);
    foreach ($this->getChilds() as $folder) {
      $folder->toXml($level+1, $inDeletedFolder);
    }
    foreach ($this->getFiles() as $file) {
      $file->toXml($level+1, $inDeletedFolder);
    }
    \Filemon\printLine($suffix, $level, 0);
  }

  public function updateMtime() {
    $dirFiles = $this->_getDirStat($this->root_path);
    foreach ($this->files as $file) {
      /* @var $dirFile File*/
      $dirFile = $this->_findEntry($dirFiles[0], $file);
      if (!$dirFile) {
        return;
      }
      if ($dirFile->getSize()==$file->getSize() && $dirFile->getMtime()!=$file->getMtime()) {
        $filePath = $this->root_path.'/'.$file->getName();
        \Filemon\printLine("Touching $filePath", 3, 0);
        //touch($filePath, $file->getMtime());
      }
    }
  }

  public function findRootPath() {
    //$rootMgr = $this->_entityMgr->getRepository('Filemon\\Model\\Root');
    /* @var $root \Filemon\Model\Root */
    //$root = $rootMgr->find($this->getId());
    $path = array();
    $dir = $this;
    while ( $dir->parent_id ) {
      $path[] = $dir->getName();
      $dir = $dir->getParent();
    }
    print_r($path);
  }
}
