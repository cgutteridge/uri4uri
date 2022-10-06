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
  if(str_starts_with($href, '_:'))
  {
    return '#_'.substr($href, 2);
  }
  return $href;
}

function resourceLink($resource, $attributes = '')
{
  $uri = $resource->url();
  $uri_href = substituteLink($uri);
  return '<a title="'.htmlspecialchars(urldecode($uri)).'" href="'.htmlspecialchars($uri_href).'"'.$attributes.'>'.htmlspecialchars($uri).'</a>';
}

function prettyResourceLink($graph, $resource, $attributes = '')
{
  $uri = $resource->url();
  $uri_href = substituteLink($uri);
  if($resource->hasLabel())
  {
    $label = $resource->label();
  }else if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $uri, $b))
  {
    $label = "#".$b[1];
  }else{
    $label = $graph->shrinkURI($uri);
  } 
  return '<a title="'.htmlspecialchars(urldecode($uri)).'" href="'.htmlspecialchars($uri_href).'"'.$attributes.'>'.htmlspecialchars($label).'</a>';
}

function resourceKey($resource)
{
  return "{$resource->nodeType()} {$resource->toString()}";
}

function getResourceTypeString($graph, $resource)
{
  $types = $resource->all('rdf:type');
  static $hidden_types = array(
    'http://www.w3.org/2002/07/owl#Thing',
    'http://www.w3.org/2002/07/owl#NamedIndividual'
  );
  static $lone_types = array(
    'http://www.w3.org/2004/02/skos/core#Concept',
    'http://www.w3.org/2002/07/owl#Class'
  );
  $types = $types->map(function($r) use ($hidden_types)
  {
    if(in_array($r->url(), $hidden_types)) return null;
    return $r;
  });
  $count = $types->count();
  $types = $types->map(function($r) use ($graph, $count, $lone_types)
  {
    if($count > 1 && in_array($r->url(), $lone_types)) return null;
    return prettyResourceLink($graph, $r);
  });
  return " <small class='classType'>[".$types->join(", ")."]</small>";
}

function renderResource($graph, $resource, &$visited_nodes, $parent = null, $followed_relations = array())
{
  global $PREFIX;
  global $PREFIX_OLD;
  $type = $resource->nodeType();
  $resource_key = resourceKey($resource);
  $visited_nodes[$resource_key] = $resource;
  echo "<figure>";
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
    echo "<figcaption>".$label;
    if($resource->has('rdf:type'))
    {
      echo getResourceTypeString($graph, $resource);
    }
    echo "</figcaption>";
  }
  echo "<ul>";
  echo "<li class='uri'><span style='font-weight:bold'>URI: </span><span style='font-family:monospace'>".resourceLink($resource)."</span></li>";
  
  static $hidden_properties = array(
    'http://www.w3.org/2000/01/rdf-schema#label' => true,
    'http://www.w3.org/2000/01/rdf-schema#isDefinedBy #relation' => true,
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => true,
    'http://www.w3.org/2004/02/skos/core#exactMatch #inverseRelation' => true,
    'http://purl.org/dc/terms/replaces #inverseRelation' => true,
    'http://purl.org/uri4uri/vocab#IANARef #inverseRelation' => true,
    'http://www.w3.org/ns/prov#wasDerivedFrom #inverseRelation' => true,
    'http://www.w3.org/2003/06/sw-vocab-status/ns#moreinfo #inverseRelation' => true,
    'http://rdfs.org/ns/void#inDataset #relation' => true,
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#first #inverseRelation' => true,
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest #inverseRelation' => true
  );
  
  static $atomic_properties = array(
    'http://www.w3.org/2000/01/rdf-schema#subClassOf' => true,
    'http://www.w3.org/2000/01/rdf-schema#domain' => true,
    'http://www.w3.org/2000/01/rdf-schema#range' => true,
    'http://schema.org/domainIncludes' => true,
    'http://schema.org/rangeIncludes' => true,
    'http://www.w3.org/2002/07/owl#unionOf' => true,
    'http://www.w3.org/2002/07/owl#disjointWith' => true
  );
  
  foreach($resource->relations() as $rel)
  {
    if(@$hidden_properties[$rel->toString()]) continue;

    $label = $rel->label();
    if(preg_match('/^http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#_(\d+)$/', $rel, $b))
    {
      $label = "#".$b[1];
    }
    $pred = prettyResourceLink($graph, $rel, " class='predicate'");
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
          $value = "\"<span class='literal' lang='$lang'>".nl2br(htmlspecialchars($r2), false)."</span>\" <small class='datatype'>($lang)</small>";
        }else{
          $value = "\"<span class='literal'>".nl2br(htmlspecialchars($r2), false)."</span>\"";
        }
      }else if(!str_starts_with($type, '#'))
      {
        $rt = $graph->resource($type);
        $value = "\"<span class='literal'>".nl2br(htmlspecialchars($r2))."</span>\" <small class='datatype'>[".prettyResourceLink($graph, $rt)."]</small>";
      }else{
        global $page_url;
        if(str_starts_with($value, "$page_url#") && !isset($visited_nodes[$res_key]))
        {
          $res_id = substr($value, strlen($page_url) + 1);
        }else if(str_starts_with($value, '_:'))
        {
          $res_id = '_'.substr($value, 2);
        }
        if(str_starts_with($value, $PREFIX_OLD)) continue;
        if($rel_followed || isset($visited_nodes[$res_key]) || (!str_starts_with($value, '_:') && (@$atomic_properties[$rel->toString()] || @$atomic_properties[$rel_key] || ($r2 instanceof Graphite_Resource && $r2->isType('foaf:Document')))))
        {
          $value = prettyResourceLink($graph, $r2);
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
      if($close_element !== 'ul></li')
      {
        if($close_element) echo "</$close_element>";
        echo "<li class='relation'>$pred: <ul class='value-list'>";
        $close_element = 'ul></li';
      }
      echo "<li>$value</li>";
    }
    if($close_element) echo "</$close_element>";
  }

  echo "</ul>";
  echo "</figure>";
}
