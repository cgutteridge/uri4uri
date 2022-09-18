<?php

if(!function_exists('str_starts_with'))
{
  function str_starts_with($haystack, $needle)
  {
    return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}

function urlencode_minimal($str)
{
  return preg_replace_callback("/[^!$&-;=@A-Z_a-z~\u{00A0}-\u{D7FF}\u{F900}-\u{FDCF}\u{FDF0}-\u{FFEF}]+/u", function($matches)
  {
    return rawurlencode($matches[0]);
  }, $str);
}

function urlencode_utf8($str)
{
  return preg_replace_callback("/[\u{0080}-\u{FFFF}]+/u", function($matches)
  {
    return rawurlencode($matches[0]);
  }, $str);
}

function parse_url_fixed($uri)
{
  $has_query = strpos($uri, '?') !== false;
  $has_fragment = strpos($uri, '#') !== false;
  
  if(!$has_query && !$has_fragment)
  {
    // Fix "a:0" treated as host+port
    $uri = "$uri?";
  }
  
  $result = parse_url($uri);
  
  if(isset($result['host']) && isset($result['port']) && !isset($result['scheme']) && substr($uri, 0, 2) !== '//')
  {
    // Fix "a:0/" treated as host+port
    $result['scheme'] = $result['host'];
    unset($result['host']);
    $result['path'] = "$result[port]$result[path]"; // 0 will be trimmed however
    unset($result['port']);
  }
  
  if($has_query && !isset($result['query']))
  {
    // Include empty but existing query
    $result['query'] = '';
  }
  
  if($has_fragment && !isset($result['fragment']))
  {
    // Include empty but existing fragment
    $result['fragment'] = '';
  }
  
  return $result;
}
