<?php
namespace pmimporter;

abstract class Shifter{
  static protected function down($input,$pad,$off,$bits) {
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
  static protected function up($input,$pad,$off,$bits) {
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
  static protected function nibbleDown($input,$pad0,$off,$bits) {
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
  static protected function nibbleUp($input,$pad0,$off,$bits) {
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
}
