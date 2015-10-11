<?php
namespace pmimporter;

abstract class Shifter{
  static public function down($input,$pad,$off,$bits) {
    $output = "";
    $pad = str_repeat($pad,$off);
    $len = (1<<$bits) - $off;
    $xbits = $bits+4;
    $zbits = $bits;
    for ($ox=0; $ox < 16; $ox++) {
      for ($oz=0; $oz < 16; $oz++) {
        $output .= substr($input,($ox << $xbits) | ($oz << $zbits) | $off,$len).$pad;
      }
    }
    return $output;
  }
  static public function up($input,$pad,$off,$bits) {
    $output = "";
    $pad = str_repeat($pad,$off);
    $len = (1<<$bits) - $off;
    $xbits = $bits+4;
    $zbits = $bits;
    for ($ox=0; $ox < 16; $ox++) {
      for ($oz=0; $oz < 16; $oz++) {
        $output .= $pad.substr($input,($ox << $xbits) | ($oz << $zbits),$len).$pad;
      }
    }
    return $output;
  }
  static public function nibbleDown($input,$pad0,$off,$bits) {
    $output = "";
    $pad = str_repeat($pad0,$off);
    $len = (1<<$bits) - $off;
    $xbits = $bits+4;
    $zbits = $bits;
    for ($ox=0; $ox < 16; $ox++) {
      for ($oz=0; $oz < 16; $oz++) {
        $index = ($ox<<10)|($oz<<6)|$off;
        for ($oy = $off; $oy < $len ; $oy++) {
          $output .= chr((ord($input{$index++}) >> 4) | ((ord($input{$index}) & 0xf)<<4));
        }
        $output .=  chr((ord($input{$index}) >> 4) | ((ord($pad0) & 0xf)<<4));
        $output .= $pad;
      }
    }
    return $output;
  }
  static public function nibbleUp($input,$pad0,$off,$bits) {
    $output = "";
    $pad = str_repeat($pad0,$off);
    $len = (1<<$bits) - $off;
    $xbits = $bits+4;
    $zbits = $bits;
    //echo "off=$off bits=$bits len=$len xbits=$xbits zbits=$zbits\n";//##DEBUG
    for ($ox=0; $ox < 16; $ox++) {
      for ($oz=0; $oz < 16; $oz++) {
        $output .= $pad;
        $index = ($ox<<10)|($oz<<6);
        $output .= chr(((ord($input{$index++}) & 0xf) << 4) | (ord($pad0) & 0xf));;
        for ($oy = 1; $oy < $len ; $oy++) {
          $output .= chr((ord($input{$index++}) >> 4) | ((ord($input{$index}) & 0xf)<<4));
          //echo "ox=$ox oz=$oz oy=$oy len=$len ".strlen($output)."\n";
        }
      }
    }
    return $output;
  }
  static public function entities($data,$xoff =0, $yoff = 0, $zoff =0) {
    if ($xoff == 0 && $yoff == 0 && $zoff == 0) return $data;
    $output = [];
    foreach ($data as $s) {
      $d = clone $s;
      if (isset($d->Pos) && count($d->Pos) == 3) {
        if ($yoff !== 0) {
          $y = $d->Pos[1]->getValue() + $yoff;
          if ($y < 0 || $y > PM_MAX_HEIGHT) continue;
          $d->Pos[1]->setValue($y);
        }
        if ($xoff !== 0) $d->Pos[0]->setValue($d->Pos[0]->getValue() + $xoff);
        if ($zoff !== 0) $d->Pos[3]->setValue($d->Pos[3]->getValue() + $zoff);
      }
      $output[] = $d;
    }
    return $output;
  }
  static public function tiles($data,$xoff = 0, $yoff = 0, $zoff) {
    if ($xoff == 0 && $yoff == 0 && $zoff == 0) return $data;
    $output = [];
    foreach ($data as $s) {
      $d = clone $s;
      if ($yoff !== 0 && isset($d->y)) {
        $y = $d->y->getValue() + $yoff;
        if ($y < 0 || $y > PM_MAX_HEIGHT) continue;
        $d->y->setValue($y);
      }
      if ($xoff !== 0 && isset($d->x)) $d->x->setValue($d->x->getValue() + $xoff);
      if ($zoff !== 0 && isset($d->z)) $d->z->setValue($d->z->getValue() + $zoff);
      $output[] = $d;
    }
    return $output;
  }
}
