<?php

namespace Filemon\Model;

abstract class Entry {
  /**
   * @var int
   * @Id
   * @Column(type="integer")
   * @GeneratedValue
   */
  protected $id;

  /**
   * @var string
   * @Column(type="string")
   */
  protected $name;

  /**
   * @var int
   * @Column(type="integer", nullable=true)
   */
  protected $deleted;

  /**
   * @var Folder
   * @ManyToOne(targetEntity="Folder")
   */
  protected $parent;

  protected $root_path;

  protected $_found = false;

  public function setRootPath($root_path) {
    $this->root_path =$root_path;
  }

  public function getName() {
    return $this->name;
  }

  public function setName($name) {
    $this->name = $name;
  }

  public function getId() {
    return $this->id;
  }

  public function setParent(Folder $parent) {
    $this->parent = $parent;
  }

  /**
   * @return Entry
   */
  public function getParent() {
    return $this->parent;
  }

  public function getDeleted() {
    return $this->deleted;
  }

  public function setDeleted($deleted) {
    $this->deleted = $deleted;
  }

  public function getFullName() {
    $path = [];
    $parent = $this->getParent();
    while ($parent) {
      $path[] = $parent->getName();
      $parent = $parent->getParent();
    }
    return join('/', array_reverse($path)).'/'.$this->getName();
  }
}
