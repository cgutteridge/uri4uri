<?php

function substituteLink($href)
{
  global $BASE;
  global $PREFIX;
  global $PREFIX_OLD;
  global $ARCHIVE_BASE;
  global $page_url;
  if(str_starts_with($href, $page_url))
  {
    if(str_ends_with($page_url, '#'))
    {
      return substr($href, strlen($page_url) - 1);
    }
    return substr($href, strlen($page_url));
  }
  if(str_starts_with($href, $PREFIX))
  {
    return $BASE.substr($href, strlen($PREFIX));
  }
  if(str_starts_with($href, $PREFIX_OLD))
  {
    return $ARCHIVE_BASE.$href;
  }
  return $href;
}

function resourceLink($resource, $attributes = '')
{
  $uri = $resource->url();
  $uri_href = substituteLink($uri);
  return '<a title="'.htmlspecialchars(urldecode($uri)).'" href="'.htmlspecialchars($uri_href).'"'.$attributes.'>'.htmlspecialchars($uri).'</a>';
}

function prettyResourceLink($resource, $attributes = '')
{
  $uri = $resource->url();
  $uri_href = substituteLink($uri);
  $label = $uri;
  if($resource->hasLabel()) { $label = $resource->label(); }
  else if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $uri, $b))
  {
    $label = "#".$b[1];
  }
  return '<a title="'.htmlspecialchars(urldecode($uri)).'" href="'.htmlspecialchars($uri_href).'"'.$attributes.'>'.htmlspecialchars($label).'</a>';
}

function resourceKey($resource)
{
  return "{$resource->nodeType()} {$resource->toString()}";
}

function getResourceTypeString($resource)
{
  $types = $resource->all('rdf:type');
  $count = $types->count();
  $types = $types->map(function($r) use ($count)
  {
    if($count > 1 && $r->url() === 'http://www.w3.org/2004/02/skos/core#Concept') return null;
    return prettyResourceLink($r);
  });
  return " <span class='classType'>[".$types->join(", ")."]</span>";
}

function renderResource($graph, $resource, &$visited_nodes, $parent = null, $followed_relations = array())
{
  global $PREFIX;
  $type = $resource->nodeType();
  $resource_key = resourceKey($resource);
  $visited_nodes[$resource_key] = $resource;
  echo "<div class='class'>";
  if($resource->hasLabel())
  {
    $label = $resource->label();
  }
  if(!isset($label) || (string)$label === '')
  {
    if($resource->has('rdf:type'))
    {
      $label = substituteLink($resource->url());
      if($label === '')
      {
        unset($label);
      }
    }else{
      unset($label);
    }
  }
  if(isset($label))
  {
    echo "<div class='classLabel'>".$label;
    if($resource->has('rdf:type'))
    {
      echo getResourceTypeString($resource);
    }
    echo "</div>";
  }
  echo "<div class='class2'>";
  echo "<div class='uri'><span style='font-weight:bold'>URI: </span><span style='font-family:monospace'>".resourceLink($resource)."</span></div>";
  
  static $hidden_properties = array(
    'http://www.w3.org/2000/01/rdf-schema#label' => true,
    'http://www.w3.org/2000/01/rdf-schema#isDefinedBy #relation' => true,
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => true,
    'http://www.w3.org/2004/02/skos/core#exactMatch' => true,
    'http://purl.org/dc/terms/replaces' => true,
    'http://purl.org/uri4uri/vocab#IANARef #inverseRelation' => true,
    'http://www.w3.org/ns/prov#wasDerivedFrom #inverseRelation' => true,
    'http://www.w3.org/2003/06/sw-vocab-status/ns#moreinfo #inverseRelation' => true,
    'http://rdfs.org/ns/void#inDataset #relation' => true
  );
  
  static $atomic_properties = array();
  
  foreach($resource->relations() as $rel)
  {
    if(@$hidden_properties[$rel->toString()]) continue;

    $label = $rel->label();
    if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $rel, $b))
    {
      $label = "#".$b[1];
    }
    $pred = prettyResourceLink($rel, " class='predicate'");
    if($rel->nodeType() == '#inverseRelation') { $pred = "is $pred of"; }
    
    $rel_key = $rel->toString().' '.$rel->nodeType();
    
    if(@$hidden_properties[$rel_key]) continue;
    
    $rel_followed = isset($followed_relations[$rel->toString()]) || isset($followed_relations[$rel_key]);
    
    $res_keys = array();
    $res_map = array();
    foreach($resource->all($rel) as $r2)
    {
      $key = resourceKey($r2);
      if($key === $parent || isset($res_map[$key])) continue;
      $res_keys[] = $key;
      $res_map[$key] = $r2;
    }
    natsort($res_keys);
    $num_resources = count($res_keys);

    $close_element = null;
    foreach($res_keys as $res_key)
    {
      $r2 = $res_map[$res_key];
      $type = $r2->nodeType();
      $value = $r2->toString();
      if($type === '#literal')
      {
        $lang = $r2->language();
        if(!empty($lang))
        {
          $lang = htmlspecialchars($lang);
          $value = "\"<span class='literal' lang='$lang'>".htmlspecialchars($r2)."</span>\" <span class='datatype'>($lang)</span>";
        }else{
          $value = "\"<span class='literal'>".htmlspecialchars($r2)."</span>\"";
        }
      }else if(!str_starts_with($type, '#'))
      {
        $rt = $graph->resource($type);
        $value = "\"<span class='literal'>".htmlspecialchars($r2)."</span>\" <span class='datatype'>[".prettyResourceLink($rt)."]</span>";
      }else{
        global $page_url;
        if(str_starts_with($value, "$page_url#") && !isset($visited_nodes[$res_key]))
        {
          $res_id = substr($value, strlen($page_url) + 1);
        }
        if($rel_followed || isset($visited_nodes[$res_key]) || @$atomic_properties[$rel_key] || ($r2 instanceof Graphite_Resource && $r2->isType('foaf:Document')))
        {
          $value = prettyResourceLink($r2);
          if(isset($res_id))
          {
            $value = "<a name='$res_id'>$value</a>";
          }
        }else{
          $opening = false;
          if($close_element !== 'table')
          {
            if($close_element) echo "</$close_element>";
            echo "<table class='relation'>";
            $close_element = 'table';
            $opening = true;
          }
          echo "<tr>";
          if($opening)
          {
            echo "<th rowspan='$num_resources'>$pred:</th>";
          }
          $followed_inner = $followed_relations;
          $followed_inner[$rel->toString()] = $rel;
          if(isset($res_id))
          {
            echo "<td class='object' id='$res_id'>";
          }else{
            echo "<td class='object'>";
          }
          renderResource($graph, $r2, $visited_nodes, $resource_key, $followed_inner);
          echo "</td></tr>";
          continue;
        }
      }
      if($close_element !== 'ul></div')
      {
        if($close_element) echo "</$close_element>";
        echo "<div class='relation'>$pred: <ul class='value-list'>";
        $close_element = 'ul></div';
      }
      echo "<li>$value</li>";
    }
    if($close_element) echo "</$close_element>";
  }

  echo "</div>";
  echo "</div>";
}
