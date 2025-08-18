<?php
/**
 * Gets the given assets url with modification time parameter
 *
 * @param  string $path the assets path relative to public/assets folder
 * @return string the full url
 */
function assets($path)
{
  return asset($path).'?v='.filemtime(public_path('/assets/'.$path));
}

function moment(){
  return date('Y-m-d H:i:s');
}

function formatDateTime($date = null, $time = false, $seconds = false, $br = false){
  $date = $date ?? moment();
  $format = 'Y.m.d.';
  if($time){
    $format.= $br ? "<b\\r>" : " ";
    $format.= $seconds ? "H:i:s" : "H:i";
  }
  return date($format, strtotime($date));
}