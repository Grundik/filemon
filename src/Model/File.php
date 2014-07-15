<?php

namespace Filemon\Model;

/** @Entity */
class File extends Entry {

  /**
   * @var bigint
   * @Column(type="integer")
   */
  protected $size;

  /**
   * @var int
   * @Column(type="integer")
   */
  protected $mtime;

  /**
   * @var int
   * @Column(type="integer")
   */
  protected $ctime;

  /**
   * @var string
   * @Column(type="string", length=32)
   */
  protected $md5;

  /**
   * @var string
   * @Column(type="string", length=64)
   */
  protected $sha256;

  /**
   * @var string
   * @Column(type="string", length=64)
   */
  protected $sha3;

  /**
   * @var string
   * @Column(type="string", length=39)
   */
  protected $tth;

  /**
   * @var string
   * @Column(type="string", length=8)
   */
  protected $crc32;

  public function setSize($size) {
    $this->size = $size;
  }

  public function getSize() {
    return $this->size;
  }

  public function setMtime($mtime) {
    $this->mtime = $mtime;
  }

  public function setCtime($ctime) {
    $this->ctime = $ctime;
  }

  public function hash() {
    $hashtypes = array(
      'CRC32' => 'crc32',
      'MD5'   => 'md5',
      'SHA1'  => 'sha1',
      'TTH'   => 'tth',
      'SHA-256'  => 'sha256',
      'SHA3-256' => 'sha3',
    );
    $cwd = getcwd();
    chdir($this->root_path);

    $result = null;
    $status = null;
    echo "...hashing".PHP_EOL;
    exec('rhash -TMCH --sha256 --sha3-256 --bsd -- '.escapeshellarg($this->getName()), $result, $status);
    if (!$status) {
      foreach ($result as $line) {
        if (preg_match('@^(\\S+)\s.* = (.+)$@', $line, $matches)) {
          $hashtype = $matches[1];
          $hash = $matches[2];
          if (isset($hashtypes[$hashtype])) {
            $this->{$hashtypes[$hashtype]} = strtoupper($hash);
          }
        }
      }
    }

    chdir($cwd);
  }

  public function update(File $entry) {
    if ($this->mtime == $entry->mtime && $this->getSize() == $entry->getSize()) {
      return;
    }
    $this->hash();
  }
}
