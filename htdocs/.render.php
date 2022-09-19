<?php

function substituteLink($uri)
{
  global $BASE;
  global $PREFIX;
  global $PREFIX_OLD;
  global $ARCHIVE_BASE;
  if(substr($uri, 0, strlen($PREFIX)) === $PREFIX)
  {
    return $BASE.substr($uri, strlen($PREFIX));
  }
  if(substr($uri, 0, strlen($PREFIX_OLD)) === $PREFIX_OLD)
  {
    return $ARCHIVE_BASE.$uri;
  }
  return $uri;
}

function resourceLink($resource, $attributes = '')
{
  $uri = $resource->url();
  $uri_href = substituteLink($uri);
  return "<a title='".htmlspecialchars(urldecode($uri))."' href='".htmlspecialchars($uri_href)."'$attributes>".htmlspecialchars($uri)."</a>";
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
  return "<a title='".htmlspecialchars(urldecode($uri))."' href='".htmlspecialchars($uri_href)."'$attributes>".htmlspecialchars($label)."</a>";
}

function renderResource($graph, $resource, &$visited_nodes, $parent = null, $followed_relations = array())
{
  global $PREFIX;
  $type = $resource->nodeType();
  $visited_nodes[$resource->toString()] = $resource;
  echo "<div class='class'>";
  if($resource->hasLabel())
  {
    echo "<div class='classLabel'>".$resource->label();
    if($resource->has('rdf:type'))
    {
      echo " <span class='classType'>[".$resource->all('rdf:type')->map(function($r) { return prettyResourceLink($r); })->join(", ")."]</span>";
    }
    echo "</div>";
  }
  echo "<div class='class2'>";
  echo "<div class='uri'><span style='font-weight:bold'>URI: </span><span style='font-family:monospace'>".resourceLink($resource)."</span></div>";
  
  static $hidden_properties = array(
    'http://www.w3.org/2000/01/rdf-schema#label' => true,
    'http://www.w3.org/2000/01/rdf-schema#isDefinedBy' => true,
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => true,
    'http://www.w3.org/2004/02/skos/core#exactMatch' => true,
    'http://purl.org/dc/terms/replaces' => true,
    'http://purl.org/uri4uri/vocab#IANARef #inverseRelation' => true
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
    if($rel->nodeType() == '#inverseRelation') { $pred = "is \"$pred\" of"; }
    
    $rel_key = $rel->toString().' '.$rel->nodeType();
    
    if(@$hidden_properties[$rel_key]) continue;
    
    $rel_followed = isset($followed_relations[$rel->toString()]) || isset($followed_relations[$rel_key]);
    
    $res_keys = array();
    $res_map = array();
    foreach($resource->all($rel) as $r2)
    {
      $key = $r2->toString();
      if($key === $parent) continue;
      $res_keys[] = $key;
      $res_map[$key] = $r2;
    }
    natsort($res_keys);

    $close_element = null;
    foreach($res_keys as $key)
    {
      $r2 = $res_map[$key];
      $type = $r2->nodeType();
      if($type == '#literal')
      {
        $value = "\"<span class='literal'>".htmlspecialchars($r2)."</span>\"";
      }else if(substr($type, 0, 4) == 'http')
      {
        $rt = $graph->resource($type);
        $value = "\"<span class='literal'>".htmlspecialchars($r2)."</span>\" <span class='datatype'>[".prettyResourceLink($rt)."]</span>";
      }else if($rel_followed || isset($visited_nodes[$r2->toString()]) || @$atomic_properties[$rel_key] || ($r2 instanceof Graphite_Resource && $r2->isType('foaf:Document')))
      {
        $value = prettyResourceLink($r2);
      }else{
        if($close_element !== 'table')
        {
          if($close_element) echo "</$close_element>";
          echo "<table class='relation'>";
          $close_element = 'table';
        }
        echo "<tr>";
        echo "<th>$pred:</th>";
        $followed_inner = $followed_relations;
        $followed_inner[$rel->toString()] = $rel;
        echo "<td class='object'>";
        renderResource($graph, $r2, $visited_nodes, $resource->toString(), $followed_inner);
        echo "</td></tr>";
        continue;
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
