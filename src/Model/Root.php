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

  public function scan(\Doctrine\ORM\EntityManager $em) {
    $dirname = $this->getPath();
    $mainfolder = $this->getFolder();
    if (!$mainfolder) {
      $mainfolder = new Folder();
      $mainfolder->setName($basename);
      $this->setFolder($mainfolder);
    }
    $mainfolder->setRootPath($dirname);
    $mainfolder->scan($em);
  }
}
