<?php

if(!function_exists('str_starts_with'))
{
  function str_starts_with($haystack, $needle)
  {
    return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}

if(!function_exists('str_ends_with'))
{
  function str_ends_with($haystack, $needle)
  {
    $needle_len = strlen($needle);
    return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, - $needle_len));
  }
}

function gethostbynamel6($host)
{
  $list = dns_get_record($host, DNS_AAAA);
  if(count($list) < 1) return false;
  return array_map(function($elem)
  {
    return @$elem['ipv6'];
  }, $list);
}

function urlencode_chars($str, $charpattern)
{
  return preg_replace_callback("/[$charpattern]+|%(?:$|[^0-9a-zA-Z](?:$|[^0-9a-zA-Z]))/u", function($matches)
  {
    return rawurlencode($matches[0]);
  }, $str);
}

function urlencode_minimal($str)
{
  return urlencode_chars($str, "^!$&-;=@A-Z_a-z~\u{00A0}-\u{D7FF}\u{F900}-\u{FDCF}\u{FDF0}-\u{FFEF}");
}

function urlencode_utf8($str)
{
  return urlencode_chars($str, "\u{0080}-\u{FFFF}");
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

function is_valid_qname($name)
{
  if(empty($name) || str_starts_with($name, ':')) return false;
  try{
    new DOMElement($name, null, 'about:invalid');
    return true;
  }catch(DOMException $e)
  {
    return false;
  }
}

function parse_str_raw($str)
{
  static $find = array(';', "\e", '.', ' ', '%2E', '%2e', '%20');
  static $replace = array('&', "\e1B", "\e2E", "\e20", "\e2E", "\e2E", "\e20");
  parse_str(str_replace($find, $replace, $str), $fields);
  return $fields;
}

function unescaped_parsed(&$str)
{
  if(!is_string($str)) return;
  $str = preg_replace_callback('/\e(..)/', function($matches)
  {
    return chr(hexdec($matches[1]));
  }, $str);
}

function unparse_url($uri)
{
  $scheme = isset($uri['scheme']) ? "$uri[scheme]:" : '';
  $start = isset($uri['host']) ? '//' : '';
  $host = @$uri['host'];
  $port = isset($uri['port']) ? ":$uri[port]" : '';
  $user = @$uri['user'];
  $pass = isset($uri['pass']) ? ":$uri[pass]" : '';
  $pass = ($user || $pass) ? "$pass@" : '';
  $path = @$uri['path'];
  $query = isset($uri['query']) ? "?$uri[query]" : '';
  $fragment = isset($uri['fragment']) ? "#$uri[fragment]" : '';
  return "$scheme$start$user$pass$host$port$path$query$fragment";
}

function uri_to_iri($uri)
{
  if(isset($uri['host']))
  {
    $host = idn_to_utf8($uri['host'], IDNA_ALLOW_UNASSIGNED, INTL_IDNA_VARIANT_UTS46, $idna_info);
    if($host === false)
    {
      $host = $idna_info['result'];
    }
    $uri['host'] = $host;
  }
  return preg_replace_callback("/((?:%[89a-fA-F][0-9a-fA-F])+)/", function($matches)
  {
    return rawurldecode($matches[0]);
  }, unparse_url($uri));
}

function uri_to_ascii($uri)
{
  if(isset($uri['host']))
  {
    $host = idn_to_ascii($uri['host'], IDNA_ALLOW_UNASSIGNED, INTL_IDNA_VARIANT_UTS46, $idna_info);
    if($host === false)
    {
      $host = $idna_info['result'];
    }
    $uri['host'] = $host;
  }
  return preg_replace_callback("/([\x80-\xFF]+)/", function($matches)
  {
    return rawurlencode($matches[0]);
  }, unparse_url($uri));
}
