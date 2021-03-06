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
   * @Column(type="string", length=40)
   */
  protected $sha1;

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

  /**
   * @var string
   * @Column(type="string", length=32)
   */
  protected $aich;

  /**
   * @var string
   * @Column(type="string", length=32)
   */
  protected $ed2k;

  /**
   * @var string
   * @Column(type="string", length=32)
   */
  protected $btih;

  public function setSize($size) {
    $this->size = $size;
  }

  public function getSize() {
    return $this->size;
  }

  public function setMtime($mtime) {
    $this->mtime = $mtime;
  }

  public function getMtime() {
    return $this->mtime;
  }

  public function setCtime($ctime) {
    $this->ctime = $ctime;
  }

  public function calculateHash() {
    $hashtypes = array(
      'CRC32' => 'crc32',
      'MD5'   => 'md5',
      'SHA1'  => 'sha1',
      'TTH'   => 'tth',
      'SHA-256'  => 'sha256',
      'SHA3-256' => 'sha3',
      'ED2K'  => 'ed2k',
      'AICH'  => 'aich',
      'BTIH'  => 'btih',
    );
    if (!$this->root_path) {
      throw new \Exception('Internal error: root path not set');
    }
    $cwd = getcwd();
    if (!chdir($this->root_path)) {
      \Filemon\printLine("Unable chdir into {$this->root_path}");
    }

    $result = null;
    $status = null;
    $hashes = null;
    \Filemon\printLine("...hashing", 0, 4);
    exec('rhash -TMCHAE --sha256 --sha3-256 --bsd --btih -- '.escapeshellarg($this->getName()), $result, $status);
    if (!$status) {
      foreach ($result as $line) {
        if (preg_match('@^(\\S+)\s.* = (.+)$@', $line, $matches)) {
          $hashtype = $matches[1];
          $hash = $matches[2];
          if (isset($hashtypes[$hashtype])) {
            $hashes[$hashtypes[$hashtype]] = strtoupper($hash);
          }
        }
      }
    }

    chdir($cwd);
    return $hashes;
  }

  public function hash() {
    foreach ($this->calculateHash() as $hashType=>$hashValue) {
      $this->{$hashType} = $hashValue;
    }
  }

  public function checkHash() {
    $diff = array();
    foreach ($this->calculateHash() as $hashType=>$hashValue) {
      $savedHash = $this->{$hashType};
      if ($savedHash !== $hashValue) {
        $diff[] = "{$hashType} mismatch ({$savedHash} -> {$hashValue}";
      }
    }
    return $diff;
  }

  public function update(File $entry) {
    $changes = array();
    if ($this->mtime != $entry->mtime) {
      $changes[] = 'mtime changed '.date('c', $this->mtime).' -> '.date('c', $entry->mtime);
    }
    if ($this->getSize() != $entry->getSize()) {
      $changes[] = 'size changed '.$this->getSize().' -> '.$entry->getSize();
    }
    if (!$this->btih) {
      $changes[] = 'no BTIH calculated';
    }
    if (!$changes) {
      return false;
    }
    \Filemon\printLine("...rehashing due to ".join(', ', $changes), 0, 4);
    $this->hash();
    $this->setMtime($entry->mtime);
    $this->setCtime($entry->ctime);
    $this->setSize($entry->getSize());
    return true;
  }

  public function check(File $entry, $level) {
    $changes = array();
    if ($this->mtime != $entry->mtime) {
      $changes[] = 'mtime '.date('c', $this->mtime).' -> '.date('c', $entry->mtime);
    }
    if ($this->getSize() != $entry->getSize()) {
      $changes[] = 'size '.$this->getSize().' -> '.$entry->getSize();
    }
    if (!$this->btih) {
      $changes[] = 'no BTIH calculated';
    }
    if (!$changes) {
      return false;
    }
    if ($level>0) {
      $changes = array_merge($changes, $this->checkHash());
    }
    \Filemon\printLine("File {$this->root_path} changed: ".join(', ', $changes), 0, 1);
    return true;
  }

  public function doUpdate($oldInstance, Folder $container, \Doctrine\ORM\EntityManager $em) {
    $isUpdated = false;
    $this->setRootPath($container->root_path);
    if ($oldInstance) {
      \Filemon\printLine("known file {$this->getName()}", 0, 5);
      $isUpdated = $this->update($oldInstance);
    } else {
      \Filemon\printLine("new file {$this->getName()}", 0, 4);
      $container->addFile($this);
      $this->hash();
      $isUpdated = true;
    }
    return $isUpdated;
  }

  public function doCheck($oldInstance, $level) {
    if ($oldInstance) {
      \Filemon\printLine("known file {$this->getName()}", 0, 5);
      $this->check($oldInstance, $level);
    } else {
      \Filemon\printLine("new file {$this->getName()}", 0, 4);
    }
  }

  public function toJson($level=1) {
    \Filemon\printLine('{', $level, 0);
    $level++;
    \Filemon\printLine('"name": '.\Filemon\jsonEncode($this->getName()).',', $level, 0);
    \Filemon\printLine('"type": "file", "size": '.$this->size.',', $level, 0);
    if ($this->deleted) {
      \Filemon\printLine('"deleted": '.$this->deleted.',', $level, 0);
    }
    \Filemon\printLine('"mtime": '.$this->mtime.', "ctime": '.$this->ctime.',', $level, 0);
    \Filemon\printLine('"tth": "'.$this->tth.'", '.
                       '"md5": "'.$this->md5.'", '.
                       '"crc32": "'.$this->crc32.'", '.
                       '"sha1": "'.$this->sha1.'", '.
                       '"sha256": "'.$this->sha256.'", '.
                       '"sha3-256": "'.$this->sha3.'", '.
                       '"aich": "'.$this->aich.'", '.
                       '"btih": "'.$this->btih.'", '.
                       '"ed2k": "'.$this->ed2k.'"', $level, 0);
    \Filemon\printLine('}', $level-1, 0);
  }

  public function toXml($level=1, $inDeletedFolder=false) {
    $data = array(
      'Name' => $this->getName(),
      'Size' => $this->getSize(),
      'TTH'  => $this->tth,
      'CTime'=> date('c', $this->ctime),
      'MTime'=> date('c', $this->mtime),
      'MD5'  => $this->md5,
      'CRC32'=> $this->crc32,
      'SHA1' => $this->sha1,
      'SHA256' => $this->sha256,
      'SHA3-256' => $this->sha3,
      'AICH' => $this->aich,
      'BTIH' => $this->btih,
      'ED2K' => $this->ed2k,
    );
    $prefix = '<File ';
    $suffix = ' />';
    if ($this->deleted) {
      $data['Deleted'] = date('c', $this->deleted);
      if (!$inDeletedFolder) {
        $prefix = "<!-- $prefix";
        $suffix = "$suffix -->";
      }
    }
    \Filemon\printLine($prefix.\Filemon\xmlEncode($data).$suffix, $level, 0);
  }

  public function getEd2kLink() {
    return 'ed2k://|file|'.str_replace('+', '%20', urlencode($this->getName())).'|'.$this->getSize().'|'.$this->ed2k.'|h='.$this->aich.'|/';
  }
  public function getTthLink() {
    return 'magnet:?xt=urn:tree:tiger:'.$this->tth.'&xl='.$this->getSize().'&dn='.str_replace('+', '%20', urlencode($this->getName()));
  }
  public function getTorrentLink() {
    return 'magnet:?xt=urn:btih:'.$this->btih.'&xl='.$this->getSize().'&dn='.str_replace('+', '%20', urlencode($this->getName()));
  }
  public function getMagnetLink() {
    return 'magnet:?dn='.str_replace('+', '%20', urlencode($this->getName())).'&xl='.$this->getSize().
             '&xt=urn:tree:tiger:'.$this->tth.
             '&xt=urn:btih:'.$this->btih.
             '&xt=urn:ed2k:'.$this->ed2k.
             '&xt=urn:ed2khash:'.$this->ed2k.
             '&xt=urn:sha1:'.\Filemon\hex2base32($this->sha1).
             '&xt=urn:aich:'.$this->aich.
             '&xt=urn:md5:'.$this->md5
           ;
  }
}
