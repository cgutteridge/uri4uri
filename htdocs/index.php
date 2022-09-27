<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(".helpers.php");
require_once(".external.php");
require_once(".graph.php");
require_once(".render.php");

$filepath = __DIR__."/data";
$BASE = '';
$PREFIX = 'http://purl.org/uri4uri';
$PREFIX_OLD = 'http://uri4uri.net';
$ARCHIVE_BASE = '//web.archive.org/web/20220000000000/';

$path = substr($_SERVER['REQUEST_URI'], 0);

if($path == '')
{
  header("Location: $BASE/");
  exit;
}

if($path == "/robots.txt")
{
  header("Content-type: text/plain");
  echo "User-agent: *\n";
  echo "Allow: /\n"; 
  exit;
}

if(str_starts_with($path, '/?') && count($_GET) === 1)
{
  foreach($_GET as $key => $value)
  {
    $target_uri = "$BASE/$key/".urlencode_minimal($value);
    header("Location: $target_uri");
  }
  exit;
}

$page_title = "";
$page_show_title = true;
$page_url = null;
$page_content = "";

if($path == "/aprilfools")
{
  $page_show_title = false;
  $page_content = file_get_contents("ui/aprilfools.html");
  require_once("ui/template.php");
  exit;
}
if($path == "/")
{
  $page_show_title = false;
  $page_content = file_get_contents("ui/homepage.html");
  require_once("ui/template.php");
  exit;
}
if(!preg_match("/^\/(vocab|uri|scheme|suffix|part|field|host|mime|urn|well-known|port|protocol|service)(?:\.(rdf|debug|ttl|html|nt|jsonld))?(?:\/([^\?]*))?(\?.*)?$/", $path, $b))
{
  serve404();
  exit;
}
@list(, $type, $format, $id, $query) = $b;

$decoded_id = rawurldecode($id);
if($type === 'host')
{
  $decoded_id = idn_to_utf8($decoded_id, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
}
if($type !== 'uri')
{
  $decoded_id = strtolower($decoded_id);
}
$reencoded_id = urlencode_minimal($decoded_id);
if(urlencode_utf8($id) !== urlencode_utf8($reencoded_id))
{
  if(empty($format))
  {
    http_response_code(301);
    header("Location: $BASE/$type/$reencoded_id$query");
  }else{
    http_response_code(301);
    header("Location: $BASE/$type.$format/$reencoded_id$query");
  }
  exit;
}
if($type === 'vocab' && !empty($id))
{
  http_response_code(301);
  header("Location: $BASE/$type$query#$id");
  exit;
}
$id = $decoded_id;

if(empty($format))
{
  $wants = 'text/html';

  if(isset($_SERVER['HTTP_ACCEPT']))
  {
    static $weights = array(
      'text/html' => 0,
      'text/turtle' => 0.02,
      'application/ld+json' => 0.015,
      'application/rdf+xml' => 0.01
    );
  
    $opts = explode(',', $_SERVER['HTTP_ACCEPT']);
    $accepts = array();
    foreach($opts as $opt)
    {
      $optparts = explode(';', trim($opt));
      $mime = array_shift($optparts);
      if(!isset($weights[$mime])) continue;
        
      $q = 1;
      foreach($optparts as $optpart)
      {
        @list($k, $v) = explode('=', $optpart);
        if($k === 'q')
        {
          $q = floatval($v);
          break;          
        }          
      }
      $accepts[$mime] = array($q, $weights[$mime]);
    }
  
    if(!empty($accepts))
    {
      uasort($accepts, function($a, $b)
      {
        if($a[0] == $b[0])
        {
          return ($a[1] < $b[1]) ? 1 : -1;
        }
        return ($a[0] < $b[0]) ? 1 : -1;
      });
      foreach($accepts as $mime => $weight)
      {
        $wants = $mime;
        break;
      }
    }
  }

  $format = 'html';
  if($wants == 'text/turtle') $format = 'ttl';
  if($wants == 'application/rdf+xml') $format = 'rdf';
  if($wants == 'application/ld+json') $format = 'jsonld';

  http_response_code(303);
  if($reencoded_id !== '')
  {
    $reencoded_id = "/$reencoded_id";
  }
  header("Location: $BASE/$type.$format$reencoded_id$query");
  exit;
}

if($type === 'vocab') $graph = graphVocab($id);
elseif($id === '') $graph = graphAll($type);
else $graph = graphEntity($type, $id);

if($format == 'html')
{
  http_response_code(200);
  $document_url = $PREFIX.$_SERVER['REQUEST_URI'];
  $doc = $graph->resource($document_url);
  $page_title = $doc->label();
  if($doc->has('foaf:primaryTopic'))
  {
    $page_url = $doc->getString('foaf:primaryTopic');
  }
  ob_start();
  echo "<p><span style='font-weight:bold'>Download data:</span> ";
  $id_href = htmlspecialchars($reencoded_id);
  echo "<a href='$BASE/$type.ttl/$id_href'>Turtle</a>";
  echo " &bull; ";
  echo "<a href='$BASE/$type.nt/$id_href'>N-Triples</a>";
  echo " &bull; ";
  echo "<a href='$BASE/$type.rdf/$id_href'>RDF/XML</a>";
  echo " &bull; ";
  echo "<a href='$BASE/$type.jsonld/$id_href'>JSON-LD</a>";
  echo "</p>";

  $visited = array();
  if($type == 'vocab')
  {
    $title = "uri4uri Vocabulary";

    $sections = array(
      array("Classes", 'rdfs:Class', 'classes'),
      array("Properties", 'rdf:Property', 'properties'),
      array("Datatypes", 'rdfs:Datatype', 'datatypes'),
      array("Concepts", 'skos:Concept', 'concepts'),
    );
    $l = array();
    $skips = array();
    foreach($sections as $s)
    {
      $l[$s[2]] = $graph->allOfType($s[1]);
      $skips []= "<a href='#".$s[2]."'>".$s[0]."</a>";
    }
    addExtraVocabTriples($graph);
    echo "<p><strong style='font-weight:bold'>Jump to:</strong> ".join(" &bull; ", $skips)."</p>";
    echo "<style type='text/css'>.class .class { margin: 4em 0;} .class .class .class { margin: 1em 0; }</style>";
    echo "<div class='class'><div class='class2'>";
    $prefix_length = strlen("$PREFIX/vocab#");
    foreach($sections as $s)
    {
      $resources = array();
      foreach($l[$s[2]] as $resource) 
      { 
        $resources[$resource->toString()] = $resource; 
      }
      ksort($resources);
      echo "<a name='".$s[2]."'><div class='class'><div class='classLabel'>".$s[0]."</div><div class='class2'>";
      foreach($resources as $resource) 
      { 
        echo "<a name='".substr($resource->toString(),$prefix_length)."'></a>";
        renderResource($graph, $resource, $visited); 
      }
      echo "</div></div>";
    }

    echo "</div></div>";
  }
  else
  {
    addVocabTriples($graph);
    addExtraVocabTriples($graph);
    $resource = $graph->resource($page_url);
    if($resource->has('rdf:type'))
    {
      $page_thingy_type = " <span class='classType'>[".$resource->all('rdf:type')->label()->join(", ")."]</span>";
    }
    if($resource->has('dct:description'))
    {
      $page_description = $resource->getString('dct:description');
    }
    renderResource($graph, $resource, $visited, resourceKey($doc));
  }
  $page_content = ob_get_contents();
  ob_end_clean();
  $page_head_content = "  <script type='application/ld+json'>".$graph->serialize('JSONLD')."</script>";
  require_once("ui/template.php");
}
elseif($format == 'rdf')
{
  http_response_code(200);
  header("Content-type: application/rdf+xml");
  echo $graph->serialize('RDFXML');
}
elseif($format == 'nt')
{
  http_response_code(200);
  header("Content-type: text/plain");
  echo $graph->serialize('NTriples');
}
elseif($format == 'ttl')
{
  http_response_code(200);
  header("Content-type: text/turtle");
  echo $graph->serialize('Turtle');
}
elseif($format == 'jsonld')
{
  http_response_code(200);
  header("Content-type: application/ld+json");
  echo $graph->serialize('JSONLD');
}
elseif($format == 'debug')
{
  http_response_code(200);
  header("Content-type: text/plain");
  print_r($graph->toArcTriples());
}
else
{
  echo "Unknown format (this can't happen)"; 
}
exit;

function serve404()
{
  http_response_code(404);
  $page_title = "404 Not Found";
  $page_show_title = true;
  $page_url = null;
  $page_content = "<p>See, it's things like this that are what Ted Nelson was trying to warn you about.</p>";
  require_once("ui/template.php");
}
