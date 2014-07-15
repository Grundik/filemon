<?php

namespace Filemon\Model;
use Doctrine\Common\Collections\ArrayCollection;

/** @Entity */
class Folder extends Entry {

  /**
   * @OneToMany(targetEntity="Folder", mappedBy="parent", cascade="all")
   * @OrderBy({"name" = "ASC"})
   */
  protected $childs;

  /**
   * @OneToMany(targetEntity="File", mappedBy="parent", cascade="all")
   * @OrderBy({"name" = "ASC"})
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
      $stat = stat($entry);
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
      echo "old folder {$this->getName()}".PHP_EOL;
    } else {
      echo "new folder {$this->getName()}".PHP_EOL;
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
      }
      $k->_found = true;
      $t = time();
      $isUpdated = $k->doUpdate($entry, $this, $em) || $isUpdated;
      $scanTime += time()-$t;
      if ($k->getDeleted()) {
        echo "...undeleted".PHP_EOL;
        $k->setDeleted(null);
        $isUpdated = true;
      }
      if ($scanTime>5*60) {
        echo "...saving intermediate status".PHP_EOL;
        $em->flush();
        $isUpdated = false;
      }
    }
    $now = time();
    foreach ($saved as $f) {
      if (!$f->_found && !$f->getDeleted()) {
        $type = $f instanceof Folder ? 'folder' : 'file';
        echo "Lost $type {$f->getName()}".PHP_EOL;
        $f->setDeleted($now);
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
      echo "...saving status".PHP_EOL;
      $em->flush();
    }
    if ($this->_updateList($entries[1], $folders, $em)) {
      echo "...saving status".PHP_EOL;
      $em->flush();
    }
    return false;
  }

  public function toJson($level=1) {
    $first = true;
    \Filemon\printLine('{', $level);
    $level++;
    \Filemon\printLine('"name": '.\Filemon\jsonEncode($this->getName()).',', $level);
    \Filemon\printLine('"type": "folder",', $level);
    if ($this->deleted) {
      \Filemon\printLine('"deleted": '.$this->deleted.',', $level);
    }
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
    \Filemon\printLine(']', $level);
    \Filemon\printLine('}', $level-1);
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
    \Filemon\printLine($prefix.\Filemon\xmlEncode($data).'>', $level);
    foreach ($this->getChilds() as $folder) {
      $folder->toXml($level+1, $inDeletedFolder);
    }
    foreach ($this->getFiles() as $file) {
      $file->toXml($level+1, $inDeletedFolder);
    }
    \Filemon\printLine($suffix, $level);
  }
}
