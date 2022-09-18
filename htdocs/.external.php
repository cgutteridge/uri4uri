<?php

function processSparqlQuery($graph, $sparql)
{
  $lines = explode("\n", $sparql);
  foreach($lines as &$line)
  {
    $line = trim($line);
  }
  foreach($graph->ns as $prefix => $ns)
  {
    array_unshift($lines, "PREFIX $prefix: <$ns>");
  }
  return implode("\n", $lines);
}

function addWikidataResult($graph, $sparql)
{
  $sparql = processSparqlQuery($graph, $sparql);
  $url = 'https://query.wikidata.org/sparql?query='.rawurlencode($sparql);
  $graph->load($url);
}

function addDBPediaResult($graph, $sparql)
{
  $sparql = processSparqlQuery($graph, $sparql);
  $url = 'https://dbpedia.org/sparql/?query='.rawurlencode($sparql);
  $graph->load($url);
}

function get_updated_json_file($file, &$renew)
{
  if(file_exists($file))
  {
    if(time() - filemtime($file) >= (48 * 60 + rand(-120, 120)) * 60)
    {
      touch($file);
      $renew = true;
    }else{
      $renew = false;
    }
    $data = json_decode(file_get_contents($file), true);
    if($data === null)
    {
      $renew = true;
    }else{
      return $data;
    }
  }
  return array();
}

function flush_output()
{
  header('Content-Encoding: none');
  header('Content-Length: '.ob_get_length());
  header('Connection: close');
  ob_end_flush();
  ob_flush();
  flush();
}

function get_stream_context()
{
  return stream_context_create(array('http' => array('user_agent' => 'uri4uri PHP/'.PHP_VERSION, 'header' => 'Connection: close\r\n')));
}

function update_iana_records($file, $assignments, $id_element, $combine_id)
{
  libxml_set_streams_context(get_stream_context());
  $xml = new DOMDocument;
  $xml->preserveWhiteSpace = false;
  if($xml->load("https://www.iana.org/assignments/$assignments/$assignments.xml") === false)
  {
    return;
  }
  $xpath = new DOMXPath($xml);
  $xpath->registerNamespace('reg', 'http://www.iana.org/assignments');
  
  $records = array();
  foreach($xpath->query('//reg:record') as $record)
  {
    foreach($xpath->query("reg:$id_element/text()", $record) as $id_item)
    {
      $id = trim($id_item->wholeText);
      $record_data = array();
      $record_data['id'] = $id;
      if($combine_id)
      {
        foreach($xpath->query("ancestor::reg:registry[. != /reg:registry]/@id", $record) as $registry_id)
        {
          $registry = $registry_id->nodeValue;
          $id = "$registry/$id";
        }
      }
      foreach($xpath->query('reg:status/text()', $record) as $status_item)
      {
        $record_data['type'] = strtolower(trim($status_item->wholeText));
        break;
      }
      foreach($xpath->query('reg:description/text()', $record) as $desc_item)
      {
        $record_data['name'] = trim($desc_item->wholeText);
        break;
      }
      $refs = array();
      foreach($xpath->query('reg:xref', $record) as $xref)
      {
        $type = $xpath->query('@type', $xref)->item(0)->nodeValue;
        $data = $xpath->query('@data', $xref)->item(0)->nodeValue;
        if($type === 'rfc')
        {
          $refs["http://www.rfc-editor.org/rfc/$data.txt"] = strtoupper($data);
        }else if($type === 'person')
        {
          foreach($xpath->query("//reg:person[@id = '$data']") as $person)
          {
            $name = null;
            foreach($xpath->query('reg:name/text()') as $name_item)
            {
              $name = $name_item->wholeText;
              break;
            }
            $uri = str_replace('&', '@', trim($xpath->query('reg:uri/text()', $person)->item(0)->wholeText));
            $refs[$uri] = $name;
            break;
          }
        }else if($type === 'uri')
        {
          $refs[$data] = null;
        }
      }
      $record_data['refs'] = $refs;
      foreach($xpath->query('reg:file[@type="template"]/text()', $record) as $template_item)
      {
        $template = trim($template_item->wholeText);
        $record_data['template'] = "http://www.iana.org/assignments/$assignments/$template";
        break;
      }
      $records[strtolower($id)] = $record_data;
      break;
    }
  }
  
  ksort($records);
  
  if(file_exists($file))
  {
    file_put_contents($file, json_encode($records, JSON_UNESCAPED_SLASHES));
  }
}

function get_schemes()
{
  static $cache_file = __DIR__.'/data/schemes.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      update_iana_records($cache_file, 'uri-schemes', 'value', false);
    }, $cache_file);
  }
  
  return $data;
}

function get_mime_types()
{
  static $cache_file = __DIR__.'/data/mime.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      update_iana_records($cache_file, 'media-types', 'name', true);
    }, $cache_file);
  }
  
  return $data;
}

function get_special_domains()
{
  static $cache_file = __DIR__.'/data/specialdn.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      update_iana_records($cache_file, 'special-use-domain-names', 'name', false);
    }, $cache_file);
  }
  
  return $data;
}

function get_tlds()
{
  static $cache_file = __DIR__.'/data/tld.json';
  
  $data = get_updated_json_file($cache_file, $renew);
  if($renew)
  {
    ob_start();
    register_shutdown_function(function($cache_file)
    {
      flush_output();
      libxml_set_streams_context(get_stream_context());
      $html = new DOMDocument;
      if(@$html->loadHTMLFile('https://www.iana.org/domains/root/db.html') === false)
      {
        return;
      }
      $xpath = new DOMXPath($html);
      
      $domains = array();
      foreach($xpath->query('//table[@id="tld-table"]/tbody/tr') as $domain_item)
      {
        $cells = iterator_to_array($xpath->query('td', $domain_item));
        if(count($cells) === 3)
        {
          foreach($xpath->query('.//a', $cells[0]) as $link)
          {
            $domain_data = array();
            $name = trim($link->textContent);
            $domain_data['name'] = $name;
            $id = ltrim($name, ".");
            $domain_data['id'] = $id;
            $domain_data['url'] = $link->getAttribute('href');
            $domain_data['type'] = trim($cells[1]->textContent);
            $domain_data['sponsor'] = trim($cells[2]->textContent);
            $domains[$id] = $domain_data;
            break;
          }
        }
      }
      ksort($domains);
      
      if(file_exists($cache_file))
      {
        file_put_contents($cache_file, json_encode($domains, JSON_UNESCAPED_SLASHES));
      }
    }, $cache_file);
  }
  
  return $data;
}
