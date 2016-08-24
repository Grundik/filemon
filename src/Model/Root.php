<?php

namespace Filemon\Model;

/** @Entity */
class Root {
  /**
   * @var int
   * @Id
   * @Column(type="integer") @GeneratedValue
   */
  protected $id;

  /**
   * @var string
   * @Column(type="string", unique=true, nullable=false)
   */
  protected $path;

  /**
   * @ManyToOne(targetEntity="Folder", cascade="all")
   */
  protected $folder;

  /**
   * @var boolean
   * @Column(type="boolean")
   */
  protected $active;

  public function __construct() {
    $this->active = true;
  }

  public function setPath($path) {
    $this->path = $path;
  }

  public function getPath() {
    return $this->path;
  }

  /**
   * @return Folder
   */
  public function getFolder() {
    return $this->folder;
  }

  public function setFolder(Folder $folder) {
    $this->folder = $folder;
  }

  protected function _getFolder() {
    $dirname = $this->getPath();
    $mainfolder = $this->getFolder();
    if (!$mainfolder) {
      $mainfolder = new Folder();
      $mainfolder->setName($dirname);
      $this->setFolder($mainfolder);
    }
    $mainfolder->setRootPath($dirname);
    return $mainfolder;
  }

  public function scan(\Doctrine\ORM\EntityManager $em) {
    $this->_getFolder()->scan($em);
    $em->flush();
  }

  public function check($level) {
    $this->_getFolder()->check($level);
  }

  public function toJson($level=1) {
    $this->_getFolder()->toJson($level);
  }

  public function toXml($level=1) {
    $this->_getFolder()->toXml($level);
  }
}
