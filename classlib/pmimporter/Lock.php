<?php
namespace pmimporter;

class Lock {
  protected $fp;
  public function __construct($path,$mode =  LOCK_SH) {
    $this->fp = null;
    $fp = fopen($path,"rb");
    if ($fp === false) {
      $fp = fopen($path,"x+b");
      if ($fp === false) {
        $fp = fopen($path,"rb");
        if ($fp === false) die("$path: Unable to create lock!\n");
      }
    }

    if (flock($fp,$mode ) === false) die("$path: Unable to obtain lock!\n");
    $this->fp = $fp;
  }
  public function __destruct() {
    if ($this->fp === null) return;
    flock($this->fp,LOCK_UN);
    fclose($this->fp);
  }
}
