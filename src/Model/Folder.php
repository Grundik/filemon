<?php

namespace Filemon\Model;
use Doctrine\Common\Collections\ArrayCollection;

/** @Entity */
class Folder extends Entry {

  /**
   * @OneToMany(targetEntity="Folder", mappedBy="parent", cascade="all")
   */
  protected $childs;

  /**
   * @OneToMany(targetEntity="File", mappedBy="parent", cascade="all")
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
      echo "$path is not readable directory".PHP_EOL;
      return null;
    }
    $cwd = getcwd();
    chdir($path);
    $dir = dir($path);
    if (!$dir) {
      return null;
    }
    $entries = array();
    while (false !== ($entry = $dir->read())) {
      if ('.'==$entry[0]) {
        continue;
      }
      $stat = stat($entry);
      if (is_dir($entry)) {
        $file = new Folder();
      } else {
        $file = new File();
        $file->setSize($stat['size']);
        $file->setMtime($stat['mtime']);
        $file->setCtime($stat['ctime']);
      }
      $file->setName($entry);
      $entries[] = $file;
    }
    $dir->close();
    chdir($cwd);
    return $entries;
  }

  protected function _findEntry($haystack, $entry) {
    foreach ($haystack as $k=>$v) {
      if ($v->getName()==$entry->getName()) {
        return $k;
      }
    }
    return null;
  }

  public function scan(\Doctrine\ORM\EntityManager $em) {
    $folders = $this->getChilds();
    $files = $this->getFiles();
    $entries = $this->_getDirStat($this->root_path);
    if (null===$entries) {
      return;
    }
    foreach ($entries as $entry) {
      $found = null;
      if ($entry instanceof Folder) {
        $k = $this->_findEntry($folders, $entry);
        if (null===$k) {
          echo "new folder {$entry->getName()}".PHP_EOL;
          $this->addChild($entry);
        } else {
          echo "old folder {$entry->getName()}".PHP_EOL;
          $entry = $folders[$k];
        }
        $entry->setRootPath($this->root_path.'/'.$entry->getName());
        $entry->scan($em);
      } elseif ($entry instanceof File) {
        $entry->setRootPath($this->root_path);
        $k = $this->_findEntry($files, $entry);
        if (null===$k) {
          echo "new file {$entry->getName()}".PHP_EOL;
          $this->addFile($entry);
          $entry->hash();
        } else {
          echo "old file {$entry->getName()}".PHP_EOL;
          $entry = $files[$k];
          $entry->update($entry);
        }
      }
      $entry->_found = true;
      if ($entry->getDeleted()) {
        echo "...undeleted".PHP_EOL;
        $entry->setDeleted(null);
      }
    }
    $now = time();
    foreach ($folders as $f) {
      if (!$f->_found && !$f->getDeleted()) {
        echo "Lost folder {$f->getName()}".PHP_EOL;
        $f->setDeleted($now);
      }
    }
    foreach ($files as $f) {
      if (!$f->_found && !$f->getDeleted()) {
        echo "Lost file {$f->getName()}".PHP_EOL;
        $f->setDeleted($now);
      }
    }
    $em->flush();
  }

  public function toJson($level=1) {
    $first = true;
    \Filemon\printLine('{', $level);
    $level++;
    \Filemon\printLine('"name": '.\Filemon\jsonEncode($this->getName()).',', $level);
    \Filemon\printLine('"type": "folder",', $level);
    \Filemon\printLine('"items": [', $level);
    foreach ($this->getChilds() as $folder) {
      if (!$first) {
        \Filemon\printLine(',', $level);
      }
      $folder->toJson($level);
      $first = false;
    }
    foreach ($this->getFiles() as $file) {
      if (!$first) {
        \Filemon\printLine(',', $level);
      }
      $file->toJson($level);
      $first = false;
    }
    \Filemon\printLine('}', $level-1);
  }
}
