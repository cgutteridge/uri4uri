<?php

require_once("../lib/arc2/ARC2.php");
require_once("../lib/Graphite/Graphite.php");

function initGraph()
{
  global $PREFIX;
  global $PREFIX_OLD;

  $graph = new Graphite();
  $graph->ns('uriv', "$PREFIX/vocab#");
  $graph->ns('uri', "$PREFIX/uri/");
  $graph->ns('scheme', "$PREFIX/scheme/");
  $graph->ns('host', "$PREFIX/host/");
  $graph->ns('suffix', "$PREFIX/suffix/");
  $graph->ns('uripart', "$PREFIX/part/");
  $graph->ns('field', "$PREFIX/field/");
  $graph->ns('mime', "$PREFIX/mime/");
  $graph->ns('urnns', "$PREFIX/urn/");
  $graph->ns('wellknown', "$PREFIX/well-known/");
  $graph->ns('port', "$PREFIX/port/");
  $graph->ns('service', "$PREFIX/service/");
  $graph->ns('protocol', "$PREFIX/protocol/");
  $graph->ns('olduri', "$PREFIX_OLD/uri/");
  $graph->ns('olduriv', "$PREFIX_OLD/vocab#");
  $graph->ns('oldscheme', "$PREFIX_OLD/scheme/");
  $graph->ns('oldhost', "$PREFIX_OLD/domain/");
  $graph->ns('oldsuffix', "$PREFIX_OLD/suffix/");
  $graph->ns('oldmime', "$PREFIX_OLD/mime/");
  $graph->ns('vs', 'http://www.w3.org/2003/06/sw-vocab-status/ns#');
  $graph->ns('dbr', 'http://dbpedia.org/resource/');
  $graph->ns('dbo', 'http://dbpedia.org/ontology/');
  $graph->ns('dbp', 'http://dbpedia.org/property/');
  $graph->ns('prov', 'http://www.w3.org/ns/prov#');
  $graph->ns('vann', 'http://purl.org/vocab/vann/');
  $graph->ns('schema', 'http://schema.org/');
  $graph->ns('void', 'http://rdfs.org/ns/void#');
  $graph->ns('hydra', 'http://www.w3.org/ns/hydra/core#');
  $graph->ns('wd', 'http://www.wikidata.org/entity/');
  $graph->ns('wdt', 'http://www.wikidata.org/prop/direct/');
  $graph->ns('rfc', 'https://www.rfc-editor.org/info/rfc');
  $graph->ns('xyz', 'http://sparql.xyz/facade-x/data/');
  
  return $graph;
}

function encodeIdentifier($id)
{
  if($id === '') return '#';
  return urlencode_minimal($id);
}

function xsdDateType($value)
{
  if(
    DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $value) !== false || DateTime::createFromFormat('Y-m-d\TH:i:s.u', $value) !== false ||
    DateTime::createFromFormat('Y-m-d\TH:i:sP', $value) !== false || DateTime::createFromFormat('Y-m-d\TH:i:s', $value) !== false)
  {
    return 'xsd:dateTime';
  }else if(DateTime::createFromFormat('Y-m-dP', $value) !== false || DateTime::createFromFormat('Y-m-d', $value) !== false)
  {
    return 'xsd:date';
  }else if(DateTime::createFromFormat('Y-mP', $value) !== false || DateTime::createFromFormat('Y-m', $value) !== false)
  {
    return 'xsd:gYearMonth';
  }else if(DateTime::createFromFormat('YP', $value) !== false || DateTime::createFromFormat('Y', $value) !== false)
  {
    return 'xsd:gYear';
  }
  return 'literal';
}

abstract class Triples
{
  protected $link_old = false;
  protected $vocab_full = false; 
  protected $entity_type = null; 
  protected $entity_types = array();
  protected $entity_notation_types = array();
  abstract protected function add($graph, $uri, $queries = false);
  
  protected function source()
  {
    return array();
  }
  
  protected function unmapId($id)
  {
    return $id;
  }
  
  protected function normalizeId($id)
  {
    return strtolower($id);
  }
  
  protected function label($id)
  {
    return (string)$id;
  }
  
  protected function addMatching($graph, $subject, $predicate, $object)
  {
    return false;
  }
  
  protected function addBaseTypes($graph, $subject)
  {
    $graph->addCompressedTriple($subject, 'rdf:type', 'skos:Concept');
    $graph->addCompressedTriple($subject, 'rdf:type', 'owl:Thing');
    $graph->addCompressedTriple($subject, 'rdf:type', 'owl:NamedIndividual');
  }
  
  static $map;
  
  static function map()
  {
    if(!isset(self::$map))
    {
      self::$map = array(
        'uri' => new URITriples,
        'scheme' => new SchemeTriples,
        'suffix' => new SuffixTriples,
        'part' => new URIPartTriples,
        'field' => new FieldTriples,
        'host' => new HostTriples,
        'mime' => new MIMETriples,
        'urn' => new URNNamespaceTriples,
        'well-known' => new WellknownTriples,
        'port' => new PortTriples,
        'service' => new ServiceTriples,
        'protocol' => new ProtocolTriples
      );
    }
    return self::$map;
  }
  
  public static function addForType($type, $graph, $id, $queries = false, &$link_old = false)
  {
    global $BASE;
    $triples = self::map()[$type];
    $link_old = $triples->link_old;
    $id = $triples->normalizeId($id);
    $subject = $triples->add($graph, $id, $queries);
    $dataset = "$BASE/void#$type";
    $graph->addCompressedTriple($subject, 'void:inDataset', $dataset);
    $graph->addCompressedTriple($dataset, 'hydra:member', $subject);
    return $subject;
  }
  
  static function idFromRecord($triples, $id, $info)
  {
    if(str_starts_with($id, '#') || !is_array($info)) return null;
    return $triples->unmapId($id);
  }
  
  public static function addAllForType($type, $graph, $queries = false, &$link_old = false)
  {
    global $PREFIX;
    $triples = self::map()[$type];
    $link_old = $triples->link_old;
    $ontology = "$PREFIX/$type/";
    $records = $triples->source();
    if(isset($records['#source']))
    {
      $graph->addCompressedTriple($ontology, 'prov:wasDerivedFrom', $records['#source']);
      $graph->addCompressedTriple($records['#source'], 'rdf:type', 'foaf:Document');
    }
    foreach($records as $id => $info)
    {
      $id = self::idFromRecord($triples, $id, $info);
      if($id === null) continue;
      if($triples->vocab_full)
      {
        $subject = $triples->add($graph, $id, $queries);
        continue;
      }
      $subject = "$PREFIX/$type/".encodeIdentifier($id);
      if($triples->entity_type !== null)
      {
        $graph->addCompressedTriple($subject, 'rdf:type', $triples->entity_type);
      }
      $graph->addCompressedTriple($subject, 'rdfs:label', $triples->label($id), 'xsd:string');
      $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', $ontology);
    }
    return $ontology;
  }
  
  public static function addAllMatching($graph, $subject, $predicate, $object)
  {
    global $PREFIX;
    if($subject instanceof Graphite_Resource)
    {
      $url = $subject->url();
      if(str_starts_with($url, $PREFIX))
      {
        $url = substr($url, strlen($PREFIX));
        if(preg_match('/^\/('.implode('|', self::types()).')\/([^\?#]*)/', $url, $b))
        {
          @list(, $type, $id) = $b;
          
          $id = rawurldecode($id);
          $id = normalizeEntityId($type, $id);
          self::addForType($type, $graph, $id, true, $link_old);
          return;
        }
      }
    }
    $any_type = 'rdfs:Resource';
    if($predicate instanceof Graphite_Resource)
    {
      $pred_url = $graph->shrinkURI($predicate->url());
      if($pred_url === 'rdf:type' && $object instanceof Graphite_Resource)
      {
        $obj_url = $graph->shrinkURI($object->url());
        static $concepts = array('skos:Concept', 'owl:Thing', 'owl:NamedIndividual', 'rdfs:Resource');
        if(in_array($obj_url, $concepts))
        {
          $any_type = $obj_url;
          $subject = null;
          $predicate = null;
          $object = null;
        }else{
          foreach(self::map() as $type => $triples)
          {
            if(in_array($obj_url, $triples->entity_types))
            {
              foreach($triples->source() as $id => $info)
              {
                $id = self::idFromRecord($triples, $id, $info);
                if($id === null) continue;
                $subject = $triples->add($graph, $id, false);
              }
              return;
            }
          }
        }
      }else if($pred_url === 'skos:notation' && $object instanceof Graphite_Literal)
      {
        $type_url = $graph->shrinkURI($object->nodeType());
        foreach(self::map() as $type => $triples)
        {
          if(in_array($type_url, $triples->entity_notation_types))
          {
            $id = $triples->normalizeId((string)$object);
            if($id === null) continue;
            $subject = $triples->add($graph, $id, true);
            return;
          }
        }
      }
    }
    if($subject === null && $predicate === null && $object === null)
    {
      foreach(self::map() as $type => $triples)
      {
        foreach($triples->source() as $id => $info)
        {
          $id = self::idFromRecord($triples, $id, $info);
          if($id === null) continue;
          $subject = "$PREFIX/$type/".encodeIdentifier($id);
          $graph->addCompressedTriple($subject, 'rdf:type', $any_type);
        }
      }
      return;
    }
    foreach(self::map() as $type => $triples)
    {
      if($triples->addMatching($graph, $subject, $predicate, $object))
      {
        break;
      }
    }
  }
  
  public static function addSources($graph, $subject)
  {
    global $BASE;
    global $PREFIX;
    $total_count = 0;
    foreach(self::map() as $type => $triples)
    {
      $records = $triples->source();
      if(isset($records['#source']))
      {
        $subset = "$subject#$type";
        if(empty($triples->entity_type))
        {
          $graph->addCompressedTriple($subject, 'void:subset', $subset);
          $graph->addCompressedTriple($subject, 'hydra:view', $subset);
        }else{
          $graph->addCompressedTriple($subject, 'void:classPartition', $subset);
          $graph->addCompressedTriple($subset, 'void:class', $triples->entity_type);
          $graph->addCompressedTriple($subject, 'hydra:view', $subset);
          $assertion = $subset.'-class';
          $graph->addCompressedTriple($subset, 'hydra:memberAssertion', $assertion);
          $graph->addCompressedTriple($assertion, 'hydra:property', 'rdf:type');
          $graph->addCompressedTriple($assertion, 'hydra:object', $triples->entity_type);
        }
        
        $graph->addCompressedTriple($subset, 'rdf:type', 'void:Dataset');
        $graph->addCompressedTriple($subset, 'rdf:type', 'hydra:Collection');
        $graph->addCompressedTriple($subset, 'dcterms:source', $records['#source']);
        foreach(array_rand($records, 2) as $id)
        {
          if(str_starts_with($id, '#') || !is_array($records[$id])) continue;
          $id = $triples->unmapId($id);
          if($id === null) continue;
          $example = $triples->add($graph, $id, false);
          $graph->addCompressedTriple($subset, 'void:exampleResource', $example);
          break;
        }
        $graph->addCompressedTriple($subset, 'void:dataDump', "$BASE/$type");
        $graph->addCompressedTriple($subset, 'void:rootResource', "$PREFIX/$type/");
        $count = count($records);
        $total_count += $count;
        $graph->addCompressedTriple($subset, 'void:entities', $count, 'xsd:integer');
        $graph->addCompressedTriple($subset, 'hydra:totalItems', $count, 'xsd:integer');
        $graph->addCompressedTriple($subset, 'void:uriSpace', "$PREFIX/$type/", 'literal');
        $graph->addCompressedTriple($subset, 'void:uriRegexPattern', '^'.addcslashes($PREFIX, "\\.")."/$type/", 'literal');
        $template = $subset.'-notation';
        $graph->addCompressedTriple($subset, 'hydra:search', $template);
        $graph->addCompressedTriple($template, 'rdf:type', 'hydra:IriTemplate');
        $graph->addCompressedTriple($template, 'hydra:template', "$BASE/$type{/notation}", 'hydra:Rfc6570Template');
        $graph->addCompressedTriple($template, 'hydra:variableRepresentation', 'hydra:BasicRepresentation');
        $mapping = $template.'-var';
        $graph->addCompressedTriple($template, 'hydra:mapping', "$subject#notation");
      }
    }
    return $total_count;
  }
  
  public static function normalizeEntityId($type, $id)
  {
    $map = self::map();
    if(isset($map[$type])) return $map[$type]->normalizeId($id);
    return $id;
  }
  
  public static function types()
  {
    return array_keys(self::map());
  }
  
  protected final function STR($arg)
  {
    return addslashes($arg);
  }
  
  protected final function URI($arg)
  {
    return urlencode_chars($arg, '<>');
  }
  
  protected final function LABELS()
  {
    return 'SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }';
  }
  
  protected final function CONSTRUCT_LABEL($entity, $target = null)
  {
    $label = $entity.'Label';
    $alt_label = $entity.'AltLabel';
    $description = $entity.'Description';
    if(empty($target))
    {
      return <<<EOF
$entity rdfs:label $label .
  $entity skos:altLabel $alt_label .
  $entity dct:description $description .
EOF;
    }else{
      return <<<EOF
$target rdfs:label $label .
  $target skos:altLabel $alt_label .
  $target dct:description $description .
  $target prov:wasDerivedFrom $entity .
EOF;
    }
  }
  
  protected final function CONSTRUCT_PAGE($entity, $target = null)
  {
    $page = $entity.'_page';
    $db = $entity.'_db';
    if(empty($target)) $target = $entity;
    return <<<EOF
$target foaf:page $page .
  $target owl:sameAs $db .
  $page a foaf:Document .
EOF;
  }
  
  protected final function MATCH_PAGE($entity, $ids = true)
  {
    $page = $entity.'_page';
    $page_id = $entity.'_page_id';
    $prop = $entity.'_prop';
    $prop_res = $entity.'_prop_res';
    $formatter = $entity.'_formatter';
    $formatter_pattern = $entity.'_formatter_pattern';
    $formatter_pattern_whole = $entity.'_formatter_pattern_whole';
    $formatter_prop = $entity.'_formatter_prop';
    $db = $entity.'_db';
    $ids_query = '';
    if($ids)
    {
      $ids_query = <<<EOF

    UNION {
      $entity $prop $page_id .
      $prop_res wikibase:directClaim $prop .
      $prop_res wikibase:propertyType wikibase:ExternalId .
      # source website for the property
      $prop_res wdt:P1896 [] .
      # formatter URL
      $prop_res p:P1630 $formatter_prop .
      $formatter_prop ps:P1630 $formatter .
      OPTIONAL {
        {
          # format as a regular expression
          $formatter_prop pq:P1793 $formatter_pattern .
          BIND(CONCAT("^", STR($formatter_pattern), "$") AS $formatter_pattern_whole)
        } UNION {
          # applies if regular expression matches
          $formatter_prop pq:P8460 $formatter_pattern_whole .
        }
      }
      BIND(COALESCE(URI(
        COALESCE(
          REPLACE(STR($page_id), $formatter_pattern_whole, STR($formatter)),
          REPLACE(STR($page_id), "^(.*)$", STR($formatter))
        )
      ), false) AS $page)
      FILTER isURI($page)
    }
EOF;
    }
    return <<<EOF
OPTIONAL {
    # described at URL
    { $entity wdt:P973 $page . }
    # official website
    UNION { $entity wdt:P856 $page . }
    # described by source/full work available at URL
    UNION { $entity wdt:P1343/wdt:P953 $page . }
    UNION {
      $page schema:about $entity .
      $page schema:isPartOf <https://en.wikipedia.org/>
      BIND(URI(REPLACE(STR($page), "^https?://en.wikipedia.org/wiki/", "http://dbpedia.org/resource/")) AS $db)
    }$ids_query
  }
EOF;
  }
}

function normalizeEntityId($type, $id)
{
  return Triples::normalizeEntityId($type, $id);
}

function graphVocab($id)
{
  global $PREFIX;
  $graph = initGraph();
  $subject = 'uriv:';
  addBoilerplateTriples($graph, $subject, "URI Vocabulary", true);
  $graph->addCompressedTriple($subject, 'rdf:type', 'owl:Ontology');
  $graph->addCompressedTriple($subject, 'dcterms:title', "URI Vocabulary", 'literal', 'en');
  $graph->addCompressedTriple($subject, 'dcterms:replaces', 'olduriv:');
  $graph->addCompressedTriple($subject, 'owl:priorVersion', 'olduriv:');
  $graph->addCompressedTriple($subject, 'vann:preferredNamespaceUri', $graph->expandURI($subject), 'xsd:anyURI');
  $graph->addCompressedTriple($subject, 'vann:preferredNamespacePrefix', rtrim($subject, ':'), 'xsd:string');
  addVocabTriples($graph);
  
  $desc = array();
  foreach($graph->ns as $prefix => $ns)
  {
    $desc[] = "@prefix $prefix: <$ns> .";
  }
  $desc[] = "@prefix : <{$graph->expandURI('uriv:')}> .";
  $desc[] = "@base <$PREFIX/> .";
  $desc[] = file_get_contents(__DIR__.'/data/vocab.ttl');
  $parser = ARC2::getTurtleParser($graph->arc2config);
  $parser->parse("", implode("\n", $desc));
  $errors = $parser->getErrors();
  if(!empty($errors))
  {
    foreach($errors as $error)
    {
      trigger_error($error, E_USER_WARNING);
    }
  }
  $graph->addTriples($parser->getTriples());

  return $graph;
}

function graphVoid($id)
{
  global $BASE;
  global $PREFIX;
  $graph = initGraph();
  $subject = "$BASE/void";
  $document = addBoilerplateTriples($graph, $subject, "VoID Dataset", false);
  $graph->addCompressedTriple($document, 'rdf:type', 'void:DatasetDescription');
  $graph->addCompressedTriple($subject, 'rdf:type', 'void:Dataset');
  $graph->addCompressedTriple($subject, 'rdf:type', 'hydra:Collection');
  $graph->addCompressedTriple($subject, 'foaf:homepage', '/');
  $graph->addCompressedTriple($subject, 'dcterms:title', "The uri4uri Dataset", 'literal', 'en');
  $graph->addCompressedTriple($subject, 'dcterms:publisher', 'http://my.data.is4.site/people/is4');
  $graph->addCompressedTriple($subject, 'void:vocabulary', "$PREFIX/vocab");
  $graph->addCompressedTriple($subject, 'void:feature', 'http://www.w3.org/ns/formats/Turtle');
  $graph->addCompressedTriple($subject, 'void:feature', 'http://www.w3.org/ns/formats/RDF_XML');
  $graph->addCompressedTriple($subject, 'void:feature', 'http://www.w3.org/ns/formats/N-Triples');
  $graph->addCompressedTriple($subject, 'void:feature', 'http://www.w3.org/ns/formats/JSON-LD');
  $graph->addCompressedTriple($subject, 'void:uriSpace', "$PREFIX/", 'literal');
  $graph->addCompressedTriple($subject, 'void:uriRegexPattern', '^'.addcslashes($PREFIX, "\\.").'/\w+/', 'literal');
  $count = Triples::addSources($graph, $subject);
  $graph->addCompressedTriple($subject, 'void:entities', $count, 'xsd:integer');
  $graph->addCompressedTriple($subject, 'hydra:totalItems', $count, 'xsd:integer');
  
  $mapping = "$subject#notation";
  $graph->addCompressedTriple($mapping, 'rdf:type', 'hydra:IriTemplateMapping');
  $graph->addCompressedTriple($mapping, 'hydra:variable', "notation", 'literal');
  $graph->addCompressedTriple($mapping, 'hydra:property', 'skos:notation');
  $graph->addCompressedTriple($mapping, 'hydra:required', 'true', 'xsd:boolean');
  
  addHyperSearch($graph);

  return $graph;
}

function addHyperSearch($graph)
{
  global $BASE;
  $subject = "$BASE/void";
  
  $graph->addCompressedTriple($subject, 'void:subset', $BASE.$_SERVER['REQUEST_URI']);
  
  $mapping = "$subject#subject";
  $graph->addCompressedTriple($mapping, 'rdf:type', 'hydra:IriTemplateMapping');
  $graph->addCompressedTriple($mapping, 'hydra:variable', "subject", 'literal');
  $graph->addCompressedTriple($mapping, 'hydra:property', 'rdf:subject');
  
  $mapping = "$subject#predicate";
  $graph->addCompressedTriple($mapping, 'rdf:type', 'hydra:IriTemplateMapping');
  $graph->addCompressedTriple($mapping, 'hydra:variable', "predicate", 'literal');
  $graph->addCompressedTriple($mapping, 'hydra:property', 'rdf:predicate');
  
  $mapping = "$subject#object";
  $graph->addCompressedTriple($mapping, 'rdf:type', 'hydra:IriTemplateMapping');
  $graph->addCompressedTriple($mapping, 'hydra:variable', "object", 'literal');
  $graph->addCompressedTriple($mapping, 'hydra:property', 'rdf:object');
  
  $search = "$subject#triples";
  $graph->addCompressedTriple($subject, 'hydra:search', $search);
  $graph->addCompressedTriple($search, 'hydra:template', "$BASE/triples{?subject,predicate,object}", 'hydra:Rfc6570Template');
  $graph->addCompressedTriple($search, 'hydra:variableRepresentation', 'hydra:ExplicitRepresentation');
  $graph->addCompressedTriple($search, 'hydra:mapping', "$subject#subject");
  $graph->addCompressedTriple($search, 'hydra:mapping', "$subject#predicate");
  $graph->addCompressedTriple($search, 'hydra:mapping', "$subject#object");
  
  return $subject;
}

function decodeNode($graph, $value)
{
  if(!isset($value) || $value === '' || str_starts_with($value, '?')) return null;
  if(str_starts_with($value, '"'))
  {
    if(preg_match('/"(.*)"(@|\^\^|)([^"]*)$/s', $value, $b))
    {
      @list(, $value, $separator, $type) = $b;
      
      if($separator === '' && $type === '')
      {
        return new Graphite_Literal($graph, array('v' => $value));
      }else if($type !== '')
      {
        if($separator === '@')
        {
          return new Graphite_Literal($graph, array('v' => $value, 'l' => $type));
        }else if($separator === '^^')
        {
          return new Graphite_Literal($graph, array('v' => $value, 'd' => $type));
        }
      }
    }
    return null;
  }
  return new Graphite_Resource($graph, $value);
}

function graphTriples($subject, $predicate, $object)
{
  global $BASE;
  $graph = initGraph();
  
  if(isset($subject) || isset($predicate) || isset($object))
  {
    Triples::addAllMatching($graph, decodeNode($graph, $subject), decodeNode($graph, $predicate), decodeNode($graph, $object));
  }
  
  $item_count = count($graph->allSubjects());
  $count = 0;
	foreach($graph->allSubjects() as $res)
	{
    $count += count($res->toArcTriples(false));
	}
  
  $query = http_build_query(array(
    'subject' => $subject,
    'predicate' => $predicate,
    'object' => $object
  ));
  $collection = addBoilerplateTriples($graph, "$BASE/triples?$query", "Triple Pattern Fragments", false);
  $graph->addCompressedTriple($collection, 'rdf:type', 'hydra:Collection');
  $graph->addCompressedTriple($collection, 'void:triples', $count, 'xsd:integer');
  $graph->addCompressedTriple($collection, 'hydra:totalItems', $item_count, 'xsd:integer');
  addHyperSearch($graph);
  return $graph;
}

function linkOldConcept($graph, $term, $type)
{
  static $linkmap = array(
    'c' => 'owl:equivalentClass',  
    'p' => 'owl:equivalentProperty',
    'd' => 'owl:equivalentClass'
  );
  $oldterm = "old$term";
  $graph->addCompressedTriple($term, 'dcterms:replaces', $oldterm);
  $graph->addCompressedTriple($term, 'skos:exactMatch', $oldterm);
  if(isset($linkmap[$type]))
  {
    $graph->addCompressedTriple($term, $linkmap[$type], $oldterm);
  }
}

function addVocabTriples($graph)
{
  global $filepath;
  $lines = file("$filepath/ns.csv");
  static $tmap = array(
    '' => array('skos:Concept', 'owl:Thing', 'owl:NamedIndividual'),
    'c' => array('rdfs:Class', 'owl:Class'),  
    'p' => array('rdf:Property'),
    'd' => array('rdfs:Datatype')
  );
  foreach($lines as $line)
  {
    if(preg_match('/^\s*(?:#|$)/', $line)) continue;
    @list($term, $type, $status, $name, $replaced) = explode(",", rtrim($line));
    $term = "uriv:$term";
    foreach($tmap[$type] as $class)
    {
      $graph->addCompressedTriple($term, 'rdf:type', $class);
    }
    $graph->addCompressedTriple($term, 'rdfs:isDefinedBy', 'uriv:');
    $graph->addCompressedTriple($term, 'rdfs:label', $name, 'literal');
    if($status === 'old-deprecated')
    {
      $graph->addCompressedTriple($term, 'owl:deprecated', 'true', 'xsd:boolean');
    }
    if($status === 'old' || $status === 'old-deprecated')
    {
      linkOldConcept($graph, $term, $type);
    }
    if(!empty($replaced))
    {
      $graph->addCompressedTriple($term, 'dct:isReplacedBy', $replaced);
    }
  }
}

function graphEntity($type, $id)
{
  $graph = initGraph();
  $subject = Triples::addForType($type, $graph, $id, true, $link_old);
  addBoilerplateTriples($graph, $subject, $id, $link_old);
  return $graph;
}

function graphAll($type)
{
  $graph = initGraph();
  $subject = Triples::addAllForType($type, $graph, false, $link_old);
  $graph->addCompressedTriple($subject, 'rdf:type', 'owl:Ontology');
  $graph->addCompressedTriple($subject, 'vann:preferredNamespaceUri', $subject, 'xsd:anyURI');
  $subject = $graph->shrinkURI($subject);
  $graph->addCompressedTriple($subject, 'vann:preferredNamespacePrefix', rtrim($subject, ':'), 'xsd:string');
  addBoilerplateTriples($graph, $subject, $type, $link_old);
  return $graph;
}

function addBoilerplateTriples($graph, $uri, $title, $link_old)
{
  global $BASE;
  $document_url = $BASE.$_SERVER['REQUEST_URI'];
  $graph->addCompressedTriple($document_url, 'rdf:type', 'foaf:Document');
  $graph->addCompressedTriple($document_url, 'dcterms:title', $title, 'literal');
  $graph->addCompressedTriple($document_url, 'foaf:primaryTopic', $uri);
  
  if($link_old) linkOldConcept($graph, $uri, '');
  return $document_url;
}

class URITriples extends Triples
{
  protected $link_old = true;
  protected $vocab_full = true;
  protected $entity_type = 'uriv:URIReference'; 
  protected $entity_types = array('uriv:URIReference', 'uriv:URI', 'uriv:RelativeURI', 'uriv:URL', 'uriv:PURL', 'uriv:PURL-Domain', 'uriv:URI-WellKnown', 'uriv:URN', 'uriv:FragmentURI');
  protected $entity_notation_types = array('xsd:anyURI', 'uriv:URIDatatype-IRI', 'uriv:URIDatatype-ASCII');
  
  protected function source()
  {
    return array('' => array());
  }
  
  protected function normalizeId($id)
  {
    return urlencode_chars($id, '<>');
  }

  protected function add($graph, $uri, $queries = false)
  {
    $subject = 'uri:'.encodeIdentifier($uri);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'uri:');
    $b = parse_url_fixed($uri);
  
    if(isset($b['scheme']))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URI');
    }else{
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:RelativeURI');
    }
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIReference');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($uri), 'xsd:string');
    
    $graph->addCompressedTriple($subject, 'skos:notation', $uri, 'xsd:anyURI');
    $graph->addCompressedTriple($subject, 'skos:notation', uri_to_iri($b), 'uriv:URIDatatype-IRI');
    $graph->addCompressedTriple($subject, 'skos:notation', uri_to_ascii($b), 'uriv:URIDatatype-ASCII');
    
    if(!empty($b['host']))
    {
      $host = strtolower($b['host']);
      $host_subject = self::addForType('host', $graph, $host);
      $graph->addCompressedTriple($subject, 'uriv:host', $host_subject);
      
      static $purl_hosts = array('purl.org', 'purl.com', 'purl.net', 'w3id.org');
      if(in_array($host, $purl_hosts))
      {
        $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:PURL');
        $path = $b['path'];
        $purls = get_purls();
        $pos = strlen($path);
        do{
          $purl_domain = strtolower(substr($path, 0, $pos));
          if(isset($purls[$purl_domain]))
          {
            if($pos === strlen($path) && !isset($b['query']) && !isset($b['fragment']))
            {
              $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:PURL-Domain');
              $graph->addCompressedTriple($subject, 'vs:term_status', 'stable', 'literal');
            }else{
              $domain_url = $b;
              $domain_url['path'] = substr($path, 0, $pos);
              unset($domain_url['query']);
              unset($domain_url['fragment']); 
              $graph->addCompressedTriple($subject, 'uriv:purlDomain', self::addForType('uri', $graph, unparse_url($domain_url)));
            }
            break;
          }
        }while(($pos = @strrpos($path, '/', $pos - strlen($path) - 1)) !== false);
        if($pos === false)
        {
          $graph->addCompressedTriple($subject, 'vs:term_status', 'unstable', 'literal');
        }
      }
    }
  
    if(!empty($b['scheme']))
    {
      $graph->addCompressedTriple($uri, 'uriv:identifiedBy', $subject);
      $graph->addCompressedTriple($subject, 'uriv:scheme', self::addForType('scheme', $graph, $b['scheme']));
      
      if($b['scheme'] == 'http' || $b['scheme'] == 'https')
      {
        if(!empty($b['host']))
        {
          $homepage = "$b[scheme]://$b[host]";
          if(!empty($b['port']))
          {
            $homepage.= ":$b[port]";
          }
          $homepage.='/';
    
          $graph->addCompressedTriple($host_subject, 'foaf:homepage', $homepage);
          $graph->addCompressedTriple($homepage, 'rdf:type', 'foaf:Document');
        }
        if(!empty($b['path']))
        {
          if(preg_match('/\/\.well-known\/([^\/]+)/', $b['path'], $bits))
          {
            $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URI-WellKnown');
            $graph->addCompressedTriple($subject, 'uriv:wellknownSuffix', self::addForType('well-known', $graph, $bits[1]));
          }
        }
      }else if($b['scheme'] == 'urn' && isset($b['path']))
      {
        list($urnns) = explode(':', $b['path'], 2);
        $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URN');
        $graph->addCompressedTriple($subject, 'uriv:urnNamespace', self::addForType('urn', $graph, strtolower($urnns)));
      }
    }
    
    if(isset($b['port']))
    {
      $graph->addCompressedTriple($subject, 'uriv:port', self::addForType('port', $graph, $b['port']));
    }
    else if(!empty($b['host']))
    {
      $graph->addCompressedTriple($subject, 'uriv:port', 'uriv:noPortSpecified');
      
      $service = @get_services()[$b['scheme']];
      $added_ports = array();
      while(is_array($service) && !empty($service))
      {
        $port = @$service['number'];
        if(!empty($port) && !isset($added_ports[$port]))
        {
          $added_ports[$port] = true;
          $graph->addCompressedTriple($subject, 'uriv:port', self::addForType('port', $graph, $port));
        }
        $service = @$service['additional'];
      }
    }
    
    if(isset($b['user']))
    {
      $graph->addCompressedTriple($subject, 'uriv:user', $b['user'], 'xsd:string');
      if(isset($b['pass']))
      {
        $graph->addCompressedTriple($subject, 'uriv:pass', $b['pass'], 'xsd:string');
      }
      $graph->addCompressedTriple($subject, 'uriv:account', "$subject#account-$b[user]");
      $graph->addCompressedTriple("$subject#account-$b[user]", 'rdf:type', 'foaf:OnlineAccount');
      $graph->addCompressedTriple("$subject#account-$b[user]", 'rdfs:label', $b['user'], 'xsd:string');
    }
  
    if(isset($b['path']))
    {
      $graph->addCompressedTriple($subject, 'uriv:path', $b['path'], 'xsd:string');
      if(preg_match("/\.([^\.\/]+)$/", $b['path'], $bits))
      {
        $graph->addCompressedTriple($subject, 'uriv:suffix', self::addForType('suffix', $graph, $bits[1]));
      }
      if(preg_match("/\/([^\/]+)$/", $b['path'], $bits))
      {
        $graph->addCompressedTriple($subject, 'uriv:filename', $bits[1], 'xsd:string');
      }
    }
  
    if(isset($b['query']))
    {
      $graph->addCompressedTriple($subject, 'uriv:query', self::addForType('part', $graph, $b['query']));
    }
  
    if(isset($b['fragment']))
    {
      list($uri_part) = explode('#', $uri, 2);
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:FragmentURI');
      $graph->addCompressedTriple($subject, 'uriv:fragment', self::addForType('part', $graph, $b['fragment']));
      $graph->addCompressedTriple($subject, 'uriv:fragmentOf', self::addForType('uri', $graph, $uri_part));
    }
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  ?thing dcterms:hasPart $subject_node .
  {$this->CONSTRUCT_PAGE('?thing')}
  {$this->CONSTRUCT_LABEL('?thing')}
} WHERE {
  ?thing ?prop <{$this->URI($uri)}> .
  ?prop_node wikibase:directClaim ?prop .
  ?prop_node wikibase:propertyType wikibase:Url .
  {$this->MATCH_PAGE('?thing')}
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class URIPartTriples extends Triples
{
  protected $vocab_full = true;
  protected $entity_type = 'uriv:URIPart';
  protected $entity_types = array('uriv:URIPart', 'uriv:URIPart-XPointer', 'uriv:URIPart-Media');
  protected $entity_notation_types = array('uriv:URIPartDatatype', 'uriv:URIPartDatatype-Decoded');
  
  protected function source()
  {
    return array('' => array());
  }
  
  protected function normalizeId($id)
  {
    return $id;
  }

  protected function add($graph, $part, $queries = false)
  {
    $subject = 'uripart:'.encodeIdentifier($part);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'uripart:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIPart');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($part), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $part, 'uriv:URIPartDatatype');
    $part_decoded = rawurldecode($part);
    $graph->addCompressedTriple($subject, 'skos:notation', $part_decoded, 'uriv:URIPartDatatype-Decoded');
    
    if(empty($part)) return $subject;
    
    if(empty(preg_replace_callback('/((?'.'>(?:[^()^]+|\^[()^])*))(\((?R)*\))?/', function($matches)
    {
      if(empty($matches[0])) return '';
      @list(, $name, $args) = $matches;
      if(empty($args) || !is_valid_qname($name))
      {
        return '.';
      }
      return '';
    }, $part_decoded)))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIPart-XPointer');
    }else{
      $parts = preg_split('/"[^"]*"(*SKIP)(*F)|[;&]/', $part);
      if(!empty($parts))
      {
        if(count($parts) === 1)
        {
          $kv = $parts[0];
          if(strpos($kv, '=') !== false)
          {
            list($key, $value) = explode('=', $kv, 2);
            $graph->addCompressedTriple($subject, 'schema:propertyID', urldecode($key), 'literal');
            $graph->addCompressedTriple($subject, 'schema:value', urldecode(trim($value, '"')), 'literal');
          }else{
            $graph->addCompressedTriple($subject, 'schema:propertyID', urldecode($kv), 'literal');
          }
        }
      
        $graph->addCompressedTriple($subject, 'rdf:type', 'rdf:Seq');
        $i = 0;
        foreach($parts as $kv)
        {
          ++$i;
          $field_subject = "$subject#_$i";
          $graph->addCompressedTriple($subject, "rdf:_$i", $field_subject);
          $graph->addCompressedTriple($field_subject, 'rdf:type', 'schema:PropertyValue');
          $graph->addCompressedTriple($field_subject, 'rdfs:label', $kv, 'xsd:string');
          if(strpos($kv, '=') !== false)
          {
            list($key, $value) = explode('=', $kv, 2);
            $graph->addCompressedTriple($field_subject, 'schema:propertyID', urldecode($key), 'literal');
            $graph->addCompressedTriple($field_subject, 'schema:value', urldecode(trim($value, '"')), 'literal');
          }else{
            $graph->addCompressedTriple($field_subject, 'schema:propertyID', urldecode($kv), 'literal');
          }
        }
      }
      
      $fields = parse_str_raw($part);
      if(isset($fields['t']) || isset($fields['xywh']))
      {
        $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIPart-Media');
      }
      
      $subject_inner = "$subject#/";
      foreach($fields as $key => $value)
      {
        self::addComplexField($graph, $subject, $subject_inner, true, $key, $value);
      }
    }
    
    return $subject;
  }
  
  static function addComplexField($graph, $subject, $inner, $root, $key, $value)
  {
    unescaped_parsed($key);
    unescaped_parsed($value);
    if(!$root && is_numeric($key))
    {
      $field = 'rdf:_'.($key+1);
    }else{
      $field = self::addForType('field', $graph, $key);
    }
    if(is_array($value))
    {
      $inner = $inner.rawurlencode($key);
      $subject_inner = "$inner/";
      $graph->addCompressedTriple($subject, $field, $inner);
      foreach($value as $key2 => $value2)
      {
        self::addComplexField($graph, $inner, $subject_inner, false, $key2, $value2);
      }
    }else{
      $graph->addCompressedTriple($subject, $field, trim($value, '"'), 'literal');
    }
  }
}

class FieldTriples extends Triples
{
  protected $entity_type = 'uriv:URIField'; 
  protected $entity_types = array('uriv:URIField');
  protected $entity_notation_types = array('uriv:URIFieldDatatype');
  
  protected function add($graph, $field, $queries = false)
  {
    $subject = 'field:'.encodeIdentifier($field);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'field:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'rdf:Property');
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIField');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($field), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $field, 'uriv:URIFieldDatatype');   
    $graph->addCompressedTriple($subject, 'schema:propertyID', $field, 'literal');
    $graph->addCompressedTriple($subject, 'skos:closeMatch', 'xyz:'.encodeIdentifier($field));
    
    return $subject;
  }
}

function addIanaRecord($graph, $subject, $records, $key)
{
  $info = @$records[$key];
  if(empty($info))
  {
    $graph->addCompressedTriple($subject, 'vs:term_status', 'unstable', 'literal');
    return;
  }
  if(!is_array($info))
  {
    return;
  }
  
  static $tmap = array(
    'permanent' => 'stable',
    'provisional' => 'testing',
    'historical' => 'archaic',
    'obsoleted' => 'archaic'
  );
  
  if(isset($info['description']))
  {
    $graph->addCompressedTriple($subject, 'rdfs:label', $info['description'], 'literal');
  }
  if(isset($info['type']))
  {
    $graph->addCompressedTriple($subject, 'vs:term_status', $tmap[$info['type']], 'literal');
  }else{
    $graph->addCompressedTriple($subject, 'vs:term_status', 'stable', 'literal');
  }
  if(isset($info['template']))
  {
    $graph->addCompressedTriple($subject, 'rdfs:seeAlso', $info['template']);
    $graph->addCompressedTriple($info['template'], 'rdf:type', 'foaf:Document');
  }
  if(isset($info['date']))
  {
    $graph->addCompressedTriple($subject, 'dcterms:date', $info['date'], xsdDateType($info['date']));
  }
  if(isset($info['updated']))
  {
    $graph->addCompressedTriple($subject, 'dcterms:modified', $info['updated'], xsdDateType($info['updated']));
  }
  foreach($info['refs'] as $url => $label)
  {
    $graph->addCompressedTriple($subject, 'uriv:IANARef', $url);
    if(str_starts_with($url, 'http:') || str_starts_with($url, 'https:'))
    {
      $graph->addCompressedTriple($subject, 'foaf:page', $url);
      $graph->addCompressedTriple($url, 'rdf:type', 'foaf:Document');
    }else{
      $graph->addCompressedTriple($url, 'rdf:type', 'foaf:Agent');
    }
    if(!empty($label))
    {
      $graph->addCompressedTriple($url, 'rdfs:label', $label, 'literal');
    }
  }
  
  if(isset($records['#source']))
  {
    $graph->addCompressedTriple($subject, 'prov:wasDerivedFrom', $records['#source']);
    $graph->addCompressedTriple($records['#source'], 'rdf:type', 'foaf:Document');
    if(isset($info['registry']))
    {
      $registry_id = $info['registry'];
      if(is_string($registry_id))
      {
        $registry_name = $registry_id;
      }else if(isset($records['#registry']))
      {
        $registry_name = $records['#registry'][$registry_id];
      }
      if(!empty($registry_name))
      {
        $registry = $records['#source'].'#table-'.$registry_name;
        $graph->addCompressedTriple($subject, 'vs:moreinfo', $registry);
        $graph->addCompressedTriple($registry, 'rdf:type', 'foaf:Document');
      }
    }
  }
  
  return $info;
}

class HostTriples extends Triples
{
  protected $link_old = true;
  protected $entity_type = 'uriv:Host'; 
  protected $entity_types = array('uriv:Host', 'uriv:IP', 'uriv:IPv4', 'uriv:IPv6', 'uriv:IP-Future', 'uriv:Domain', 'uriv:Domain-Special', 'uriv:TopLevelDomain', 'uriv:TopLevelDomain-CountryCode', 'uriv:TopLevelDomain-Generic', 'uriv:TopLevelDomain-GenericRestricted', 'uriv:TopLevelDomain-Infrastructure', 'uriv:TopLevelDomain-Sponsored', 'uriv:TopLevelDomain-Proposed', 'uriv:TopLevelDomain-Test');
  protected $entity_notation_types = array('uriv:HostDatatype', 'uriv:HostDatatype-Encoded');
  
  protected function source()
  {
    return get_tlds();
  }
  
  protected function unmapId($id)
  {
    return rtrim($id, '.');
  }
  
  protected function normalizeId($id)
  {
    $utf = idn_to_utf8($id, IDNA_ALLOW_UNASSIGNED, INTL_IDNA_VARIANT_UTS46, $idna_info);
    if($utf === false)
    {
      $utf = $idna_info['result'];
    }
    return parent::normalizeId($utf);
  }

  protected function add($graph, $host, $queries = false, &$special_type = null, $is_domain = false)
  {
    $subject = 'host:'.encodeIdentifier($host);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'host:');
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Host');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($host), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $host, 'uriv:HostDatatype');
    $host_idn = idn_to_ascii($host, IDNA_ALLOW_UNASSIGNED, INTL_IDNA_VARIANT_UTS46);
    if($host_idn !== false)
    {
      $graph->addCompressedTriple($subject, 'skos:notation', $host_idn, 'uriv:HostDatatype-Encoded');
    }
    if(!$is_domain)
    {
      if(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
      {
        return $this->addIPv4($graph, $subject, $host, $queries, $special_type);
      }
      if(preg_match('/^\[(.+)\]$/s', $host, $matches))
      {
        if(filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false)
        {
          return $this->addIPv6($graph, $subject, $host, $matches[1], $queries, $special_type);
        }else{
          return $this->addIPFuture($graph, $subject, $host, $matches[1], $queries, $special_type);
        }
      }
    }
    return $this->addDomain($graph, $subject, $host, $host_idn, $queries, $special_type);
  }
  
  protected function resolveDomain($graph, $subject, $ip)
  {
    static $localhost = array("\x7F\x00\x00\x01", "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01");
    if(in_array(@inet_pton($ip), $localhost))
    {
      $graph->addCompressedTriple($this->add($graph, 'localhost'), 'uriv:address', $subject);
    }else if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
    {
      $domain = gethostbyaddr($ip);
      if(!empty($domain) && filter_var($domain, FILTER_VALIDATE_IP) === false)
      {
        $graph->addCompressedTriple($this->add($graph, $domain), 'uriv:address', $subject);
      }
    }
  }
  
  protected function queryRdap($graph, $subject, $type, $object)
  {
    $record = @get_rdap_record($type, $object);
    if(!empty($record))
    {
      if(!empty($record['links']))
      {
        foreach($record['links'] as $link)
        {
          if(@$link['rel'] === 'self')
          {
            $self = $link['href'];
            $graph->addCompressedTriple($subject, 'uriv:rdapRecord', $self);
            $self_parts = parse_url_fixed($self);
            if(!empty($self_parts['host']))
            {
              $graph->addCompressedTriple($subject, 'uriv:hasRdapServer', $this->add($graph, $self_parts['host']));
            }
            $graph->addCompressedTriple($subject, 'prov:wasDerivedFrom', $self);
          }else if(!empty($link['href']))
          {
            $graph->addCompressedTriple($subject, 'rdfs:seeAlso', $link['href']);
          }
        }
      }
      if(!empty($record['port43']))
      {
        $whois = $record['port43'];
        $graph->addCompressedTriple($subject, 'uriv:hasWhoIsServer', $this->add($graph, $whois));
        $whois_record = get_whois_record($whois, $object);
        if(!empty($record))
        {
          $graph->addCompressedTriple($subject, 'uriv:whoIsRecord', $whois_record, 'xsd:string');
        }
      }
      if(!empty($record['events']))
      {
        foreach($record['events'] as $event)
        {
          static $actions = array(
            'registration' => 'dcterms:created',
            'last changed' => 'dcterms:modified'
          );
          $action = @$event['eventAction'];
          if(isset($actions[$action]) && !empty($event['eventDate']))
          {
            $graph->addCompressedTriple($subject, $actions[$action], $event['eventDate'], xsdDateType($event['eventDate']));
          }
        }
      }
    }
  }
  
  protected function addIPv4($graph, $subject, $ip, $queries, &$special_type)
  {
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:IP');
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:IPv4');
    
    $packed = inet_pton($ip);
    $graph->addCompressedTriple($subject, 'skos:notation', bin2hex($packed), 'xsd:hexBinary');
    $graph->addCompressedTriple($subject, 'skos:notation', base64_encode($packed), 'xsd:base64Binary');
    
    if(!$queries) return $subject;
    
    $packed = array_reverse(str_split($packed));
    foreach($packed as &$byte)
    {
      $byte = ord($byte);
    }
    $packed[] = 'in-addr.arpa';
    $rdns_domain = implode('.', $packed);
    $graph->addCompressedTriple($this->add($graph, $rdns_domain), 'uriv:address', $subject);
    
    $this->resolveDomain($graph, $subject, $ip);
    
    $this->queryRdap($graph, $subject, 'ip', $ip);
    
    return $subject;
  }
  
  protected function addIPv6($graph, $subject, $ip_wrapped, $ip, $queries, &$special_type)
  {
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:IP');
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:IPv6');
    
    $packed = inet_pton($ip);
    $graph->addCompressedTriple($subject, 'skos:notation', bin2hex($packed), 'xsd:hexBinary');
    $graph->addCompressedTriple($subject, 'skos:notation', base64_encode($packed), 'xsd:base64Binary');
    
    if(!$queries) return $subject;
    
    $packed = array_reverse(str_split($packed));
    foreach($packed as &$byte)
    {
      $ord = ord($byte);
      $byte = sprintf('%x.%x', $ord & 0xF, $ord >> 4);
    }
    $packed[] = 'ip6.arpa';
    $rdns_domain = implode('.', $packed);
    $graph->addCompressedTriple($this->add($graph, $rdns_domain), 'uriv:address', $subject);
    
    $this->resolveDomain($graph, $subject, $ip);
    
    $this->queryRdap($graph, $subject, 'ip', $ip);
    
    return $subject;
  }
  
  protected function addIPFuture($graph, $subject, $ip_wrapped, $ip, $queries, &$special_type)
  {
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:IP');
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:IP-Future');
    
    if(!$queries) return $subject;
    
    $this->resolveDomain($graph, $subject, $ip);
    
    $this->queryRdap($graph, $subject, 'ip', $ip);
    
    return $subject;
  }
    
  protected function addDomain($graph, $subject, $domain, $domain_idn, $queries = false, &$special_type = null)
  {
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Domain');
    
    $special_domains = get_special_domains();
    if($domain_idn !== false && isset($special_domains["$domain_idn."]))
    {
      addIanaRecord($graph, $subject, $special_domains, "$domain_idn.");
      $special_type = 'uriv:Domain-Special';
    }
    
    # Super Domains
    if(strpos($domain, ".") !== false)
    {
      if($queries && $domain_idn !== false)
      {
        if(preg_match('/^((?:[0-9]+\.){4})in-addr\.arpa$/', $domain_idn, $matches))
        {
          $address = @inet_pton(rtrim($matches[1], '.'));
          if(!empty($address))
          {
            $graph->addCompressedTriple($subject, 'uriv:address', $this->add($graph, inet_ntop(strrev($address))));
          }
        }else if(preg_match('/^((?:[0-9a-zA-Z]\.){32})ip6\.arpa$/', $domain_idn, $matches))
        {
          $address = @inet_ntop(hex2bin(strrev(str_replace('.', '', $matches[1]))));
          if(!empty($address))
          {
            $graph->addCompressedTriple($subject, 'uriv:address', $this->add($graph, "[$address]"));
          }
        }else{
          $addresses = gethostbynamel("$domain_idn.");
          if(!empty($addresses))
          {
            foreach($addresses as $address)
            {
              if(!empty($address))
              {
                $graph->addCompressedTriple($subject, 'uriv:address', $this->add($graph, $address));
              }
            }
          }
          $addresses = gethostbynamel6("$domain_idn.");
          if(!empty($addresses))
          {
            foreach($addresses as $address)
            {
              if(!empty($address))
              {
                $graph->addCompressedTriple($subject, 'uriv:address', $this->add($graph, "[$address]"));
              }
            }
          }
        }
        
        $this->queryRdap($graph, $subject, 'domain', $domain_idn);
      }
      
      list($domain_name, $domain) = explode(".", $domain, 2);
      $inner_subject = $this->add($graph, $domain, false, $special_type, true);
      $graph->addCompressedTriple($inner_subject, 'uriv:subDom', $subject);
      if(!empty($special_type))
      {
        $graph->addCompressedTriple($subject, 'rdf:type', $special_type);
      }
      return $subject;
    }
    if(!empty($special_type))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', $special_type);
    }
  
    # TLD Shenanigans...
  
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:TopLevelDomain');
    
    $tlds = get_tlds();
    if($domain_idn !== false && isset($tlds[$domain_idn]))
    {
      if(isset($tlds['#source']))
      {
        $graph->addCompressedTriple($subject, 'prov:wasDerivedFrom', $tlds['#source']);
        $graph->addCompressedTriple($tlds['#source'], 'rdf:type', 'foaf:Document');
      }
      $tld = $tlds[$domain_idn];
      $graph->addCompressedTriple($subject, 'uriv:delegationRecordPage', "http://www.iana.org$tld[url]");
      $graph->addCompressedTriple($subject, 'vs:moreinfo', "http://www.iana.org$tld[url]");
      $graph->addCompressedTriple($subject, 'foaf:page', "http://www.iana.org$tld[url]");
      $graph->addCompressedTriple("http://www.iana.org$tld[url]", 'rdf:type', 'foaf:Document');
      $type = str_replace(' ', '', ucwords(str_replace('-', ' ', $tld['type'])));
      $graph->addCompressedTriple($subject, 'rdf:type', "uriv:TopLevelDomain-$type");
      $graph->addCompressedTriple($subject, 'uriv:sponsor', "$subject#sponsor");
      $graph->addCompressedTriple("$subject#sponsor", 'rdf:type', 'foaf:Organization');
      $graph->addCompressedTriple("$subject#sponsor", 'rdfs:label', $tld['sponsor'], 'xsd:string');
    }
    
    if(!$queries || $domain_idn === false) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  $subject_node owl:sameAs ?domain .
  {$this->CONSTRUCT_PAGE('?domain', $subject_node)}
  {$this->CONSTRUCT_LABEL('?domain', $subject_node)}
  ?country dbp:cctld $subject_node .
  ?country a dbo:Country .
  {$this->CONSTRUCT_LABEL('?country')}
  {$this->CONSTRUCT_PAGE('?country')}
  ?country geo:lat ?lat .
  ?country geo:long ?long .
} WHERE {
  # IANA Root Zone Database ID
  ?domain wdt:P5914 "{$this->STR($domain_idn)}" .
  {$this->MATCH_PAGE('?domain')}
  OPTIONAL {
    # country
    ?domain wdt:P17 ?country .
    {$this->MATCH_PAGE('?country', false)}
    OPTIONAL {
      # coordinate location
      ?country p:P625 ?coords .
      ?coords psv:P625 ?coord_node .
      ?coord_node wikibase:geoLatitude ?lat .  
      ?coord_node wikibase:geoLongitude ?long .
    }
  }
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class SuffixTriples extends Triples
{
  public $link_old = true;
  protected $entity_type = 'uriv:Suffix';
  protected $entity_types = array('uriv:Suffix');
  protected $entity_notation_types = array('uriv:SuffixDatatype');
  
  protected function label($suffix)
  {
    return ".$suffix";
  }
  
  protected function add($graph, $suffix, $queries = false)
  {
    $subject = 'suffix:'.encodeIdentifier($suffix);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'suffix:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Suffix');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($suffix), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $suffix, 'uriv:SuffixDatatype');
    
    if(!$queries) return $subject;
  
    $suffix_lower = strtolower($suffix);
    $suffix_upper = strtoupper($suffix);
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  ?format dbp:extension $subject_node .
  ?format a uriv:Format .
  {$this->CONSTRUCT_LABEL('?format')}
  {$this->CONSTRUCT_PAGE('?format')}
  ?format dbp:mime ?mime .
  ?mime a uriv:Mimetype .
  ?mime rdfs:label ?mime_str .
  ?mime skos:notation ?mime_notation .
} WHERE {
  # file extension
  { ?format wdt:P1195 "{$this->STR($suffix_lower)}" . } UNION { ?format wdt:P1195 "{$this->STR($suffix_upper)}" . }
  {$this->MATCH_PAGE('?format')}
  OPTIONAL {
    # MIME type
    ?format wdt:P1163 ?mime_str .
    FILTER (isLiteral(?mime_str) && STR(?mime_str) != "application/octet-stream")
    BIND(STRDT(?mime_str, uriv:MimetypeDatatype) AS ?mime_notation)
    BIND(URI(CONCAT("{$this->STR($this->URI($graph->expandURI("mime:")))}", ?mime_str)) AS ?mime)
  }
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class MIMETriples extends Triples
{
  public $link_old = true;
  protected $entity_type = 'uriv:Mimetype';
  protected $entity_types = array('uriv:Mimetype', 'uriv:Mimetype-Discrete', 'uriv:Mimetype-Multipart', 'uriv:Mimetype-Structured', 'uriv:Mimetype-Parametrized', 'uriv:Mimetype-Implied');
  protected $entity_notation_types = array('uriv:MimetypeDatatype');
  
  protected function source()
  {
    return get_mime_types();
  }
  
  protected function normalizeId($mime)
  {
    $param_split = explode(';', $mime, 2);
    if(count($param_split) >= 2)
    {
      return parent::normalizeId($param_split[0]).';'.normalizeEntityId('part', $param_split[1]);
    }
    return parent::normalizeId($mime);
  }
  
  protected function add($graph, $mime, $queries = false)
  {
    $subject = 'mime:'.encodeIdentifier($mime);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'mime:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype');
    
    if(str_starts_with($mime, 'message/') || str_starts_with($mime, 'multipart/'))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype-Multipart');
    }else{
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype-Discrete');
    }
    
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($mime), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $mime, 'uriv:MimetypeDatatype');
    
    $implied = false;
    @list($bare_mime, $param_part) = explode(';', $mime, 2);
    if(!empty($param_part))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype-Parametrized');
      if(str_starts_with($mime, "application/prs.implied-"))
      {
        $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype-Implied');
        $implied = true;
      }else{
        $graph->addCompressedTriple($subject, 'skos:broader', self::addForType('mime', $graph, $bare_mime));
      }
      $mime_params = self::addForType('part', $graph, $param_part);
      $graph->addCompressedTriple($subject, 'uriv:mimeParams', $mime_params);
    }else{
      $mime_types = get_mime_types();
      addIanaRecord($graph, $subject, $mime_types, $bare_mime);
    }
    
    @list(, $suffix_type) = explode('+', $bare_mime, 2);
    if(!empty($suffix_type))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype-Structured');
      static $suffix_map = array(
        'ber' => 'application/ber-stream',
        'der' => 'application/der-stream',
        'wbxml' => 'application/vnd.wap.wbxml'
      );
      $base_mime = @$suffix_map[$suffix_type] ?? "application/$suffix_type";
      $base_subject = self::addForType('mime', $graph, $base_mime);
      $graph->addCompressedTriple($subject, 'dbp:extendedFrom', $base_subject);
    }
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $filter_query = '';
    $construct_query = '';
    if($implied)
    {
      $params = $graph->resource($mime_params);
      if(str_starts_with($mime, 'application/prs.implied-document+xml;'))
      {
        foreach($params->all('field:ns') as $ns)
        {
          $ns = (string)$ns;
          break;
        }
        foreach($params->all('field:public') as $public)
        {
          $public = (string)$public;
          break;
        }
        if(isset($ns))
        {
          $filter_query .= <<<EOF
  # XML namespace URL
  ?format wdt:P7510 <{$this->URI($ns)}> .
EOF;
          
        }
        if(isset($public))
        {
          $filter_query .= <<<EOF
  # Formal Public Identifier
  ?format wdt:P4506 "{$this->STR($public)}" .
EOF;
        }
      }else if(str_starts_with($mime, 'application/prs.implied-structure;'))
      {
        foreach($params->all('field:signature') as $signature)
        {
          $signature = (string)$signature;
          break;
        }
        $offset = 0;
        foreach($params->all('field:offset') as $offset)
        {
          $offset = intval((string)$offset);
          break;
        }
        if(isset($signature))
        {
          if(strlen($signature) > 1)
          {
            $base_signature = substr($signature, 0, -1);
            $base_structure_mime = "application/prs.implied-structure;signature=$base_signature";
            if(!empty($offset))
            {
              $base_structure_mime .= ";offset=$offset";
            }
            $graph->addCompressedTriple($subject, 'skos:broader', self::addForType('mime', $graph, $base_structure_mime));
          }
          
          $sig_hex = bin2hex($signature);
          $sig_hex_up = strtoupper($sig_hex);
          if($offset >= 0)
          {
            // beginning of file
            $rel_pos = 'wd:Q35436009';
          }else{
            // end of file
            $rel_pos = 'wd:Q1148480';
            $offset = -($offset + strlen($signature));
          }
          if($sig_hex == $sig_hex_up)
          {
            $filter_query .= <<<EOF
  VALUES (?pattern ?pattern_encoding) {
    # ASCII
    ("{$this->STR($signature)}" wd:Q8815)
    # hexadecimal
    ("{$this->STR($sig_hex)}" wd:Q82828)
  }
EOF;
          }else{
            $filter_query .= <<<EOF
  VALUES (?pattern ?pattern_encoding) {
    # ASCII
    ("{$this->STR($signature)}" wd:Q8815)
    # hexadecimal
    ("{$this->STR($sig_hex)}" wd:Q82828)
    ("{$this->STR($sig_hex_up)}" wd:Q82828)
  }
EOF;
          }
          $filter_query .= <<<EOF
  # file format identification pattern
  ?pattern_prop ps:P4152 ?pattern .
  # encoding
  ?pattern_prop pq:P3294 ?pattern_encoding .
  # relative to
  ?pattern_prop pq:P2210 $rel_pos .
  OPTIONAL {
    # offset
    ?pattern_prop pq:P4153 ?pattern_offset .
  }
  FILTER (COALESCE(?pattern_offset, 0) = $offset)
  ?format p:P4152 ?pattern_prop .
EOF;
        }
      }else if(str_starts_with($mime, 'application/prs.implied-executable;'))
      {
        foreach($params->all('field:interpreter') as $interpreter)
        {
          $interpreter = (string)$interpreter;
          break;
        }
        if(isset($interpreter))
        {
          $filter_query .= <<<EOF
  ?exec ?exec_claim "{$this->STR($interpreter)}" .
  ?package_prop wikibase:directClaim ?exec_claim .
  # instance of Wikidata property to identify packages in an operating-system-specific repository
  ?package_prop wdt:P31 wd:Q115268993 .
  {
    # readable file format
    ?exec wdt:P1072 ?format .
  } UNION {
    # instance of
    ?exec p:P31 ?exec_instance_prop .
    VALUES ?of_type {
      # interpreter
      wd:Q183065
      # compiler
      wd:Q47506
    }
    ?exec_instance_prop ps:P31 ?of_type .
    # of
    ?exec_instance_prop pq:P642 ?format .
  } UNION {
    VALUES ?inst_type {
      # programming language
      wd:Q9143
      # scripting language
      wd:Q187432
      # interpreted language
      wd:Q1993334
    }
    # instance of
    ?exec wdt:P31 ?inst_type .
    ?exec owl:sameAs? ?format .
  }
EOF;
        }
      }
      if(!empty($filter_query))
      {
        $filter_query .= <<<EOF
  OPTIONAL {
    ?format wdt:P1163 ?mime_str .
    FILTER (isLiteral(?mime_str) && STR(?mime_str) != "application/octet-stream")
    BIND(STRDT(?mime_str, uriv:MimetypeDatatype) AS ?mime_notation)
    BIND(URI(CONCAT("{$this->STR($this->URI($graph->expandURI("mime:")))}", ?mime_str)) AS ?mime)
  }
EOF;
      }
      $construct_query = <<<EOF
  ?mime a uriv:Mimetype .
  ?mime skos:broader $subject_node .
  ?mime rdfs:label ?mime_str .
  ?mime skos:notation ?mime_notation .
  ?format dbp:mime ?mime .
EOF;
    }else if($mime === 'text/plain' || $mime === 'application/octet-stream')
    {
      $filter_query = <<<EOF
    # MIME type
    ?format p:P1163 ?mime_prop .
    ?mime_prop ps:P1163 "{$this->STR($mime)}" .
    ?mime_prop prov:wasDerivedFrom ?mime_source .
    FILTER NOT EXISTS {
      ?format wdt:P1163 ?other_mime .
      FILTER(STR(?other_mime) != "{$this->STR($mime)}")
    }
EOF;
    }else{
      $filter_query = <<<EOF
    # MIME type
    ?format wdt:P1163 "{$this->STR($mime)}" .
EOF;
    }
    
    if(empty($filter_query)) return $subject;
    
    $query = <<<EOF
CONSTRUCT {
  ?format a uriv:Format .
  {$this->CONSTRUCT_LABEL('?format')}
  {$this->CONSTRUCT_PAGE('?format')}
  ?format dbp:extension ?suffix .
  ?suffix a uriv:Suffix .
  ?suffix rdfs:label ?suffix_label .
  ?suffix skos:notation ?suffix_notation .
  $construct_query
  ?format dbp:mime $subject_node .
} WHERE {
  $filter_query
  {$this->MATCH_PAGE('?format')}
  OPTIONAL {
    # file extension
    ?format wdt:P1195 ?suffix_strcs .
    FILTER isLiteral(?suffix_strcs)
    BIND(LCASE(STR(?suffix_strcs)) AS ?suffix_str)
    BIND(CONCAT(".", ?suffix_str) AS ?suffix_label)
    BIND(STRDT(?suffix_str, uriv:SuffixDatatype) AS ?suffix_notation)
    BIND(URI(CONCAT("{$this->STR($this->URI($graph->expandURI("suffix:")))}", ?suffix_str)) AS ?suffix)
  }
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class SchemeTriples extends Triples
{
  protected $link_old = true;
  protected $entity_type = 'uriv:URIScheme';
  protected $entity_types = array('uriv:URIScheme');
  protected $entity_notation_types = array('uriv:URISchemeDatatype');


  
  protected function source()
  {
    return get_schemes();
  }
  
  protected function label($scheme)
  {
    return "$scheme:";
  }
  
  protected function add($graph, $scheme, $queries = false)
  {
    $subject = 'scheme:'.encodeIdentifier($scheme);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'scheme:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIScheme');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($scheme), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $scheme, 'uriv:URISchemeDatatype');
  
    $schemes = get_schemes();
    addIanaRecord($graph, $subject, $schemes, $scheme);
    
    if(isset(get_services()[$scheme]))
    {
      $service = self::addForType('service', $graph, $scheme);
      $graph->addCompressedTriple($subject, 'dcterms:relation', $service);
      $graph->addCompressedTriple($subject, 'owl:differentFrom', $service);
    }
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  {$this->CONSTRUCT_LABEL('?scheme', $subject_node)}
  $subject_node owl:sameAs ?scheme .
  {$this->CONSTRUCT_PAGE('?scheme', $subject_node)}
  ?technology dcterms:hasPart $subject_node .
  {$this->CONSTRUCT_LABEL('?technology')}
  {$this->CONSTRUCT_PAGE('?technology')}
} WHERE {
  OPTIONAL {
    # Uniform Resource Identifier Scheme
    ?scheme wdt:P4742 "{$this->STR($scheme)}" .
    # instance of Uniform Resource Identifier scheme
    ?scheme wdt:P31 wd:Q37071 .
    {$this->MATCH_PAGE('?scheme')}
  }
  OPTIONAL {
    # Uniform Resource Identifier Scheme
    ?technology wdt:P4742 "{$this->STR($scheme)}" .
    FILTER NOT EXISTS {
      # instance of Uniform Resource Identifier scheme
      ?technology wdt:P31 wd:Q37071 .
    }
    {$this->MATCH_PAGE('?technology')}
  }
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class URNNamespaceTriples extends Triples
{
  protected $entity_type = 'uriv:URNNamespace';
  protected $entity_types = array('uriv:URNNamespace', 'uriv:URNNamespace-Experimental', 'uriv:URNNamespace-Informal', 'uriv:URNNamespace-Formal');
  protected $entity_notation_types = array('uriv:URNNamespaceDatatype');
  
  protected function source()
  {
    return get_urn_namespaces();
  }
  
  protected function label($ns)
  {
    return "urn:$ns:";
  }
  
  protected function add($graph, $ns, $queries = false)
  {
    $subject = 'urnns:'.encodeIdentifier($ns);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'urnns:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URNNamespace');
    if(str_starts_with($ns, 'x-'))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URNNamespace-Experimental');
    }else if(str_starts_with($ns, 'urn-'))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URNNamespace-Informal');
    }else{
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URNNamespace-Formal');
    }
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($ns), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $ns, 'uriv:URNNamespaceDatatype');
  
    $namespaces = get_urn_namespaces();
    addIanaRecord($graph, $subject, $namespaces, $ns);
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  ?technology dcterms:hasPart $subject_node .
  {$this->CONSTRUCT_LABEL('?technology')}
  {$this->CONSTRUCT_PAGE('?technology')}
} WHERE {
  # URN formatter
  ?technology wdt:P7470 "urn:{$this->STR($this->URI($ns))}:\$1" .
  {$this->MATCH_PAGE('?technology')}
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class WellknownTriples extends Triples
{
  protected $entity_type = 'uriv:WellKnownURISuffix';
  protected $entity_types = array('uriv:WellKnownURISuffix');
  protected $entity_notation_types = array('uriv:WellKnownURISuffixDatatype');
  
  protected function source()
  {
    return get_wellknown_uris();
  }
  
  protected function label($suffix)
  {
    return "/.well-known/$suffix";
  }
  
  protected function add($graph, $suffix, $queries = false)
  {
    $subject = 'wellknown:'.encodeIdentifier($suffix);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'wellknown:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:WellKnownURISuffix');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($suffix), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $suffix, 'uriv:WellKnownURISuffixDatatype');
  
    $wellknown = get_wellknown_uris();
    addIanaRecord($graph, $subject, $wellknown, $suffix);
    
    return $subject;
  }
}

class PortTriples extends Triples
{
  protected $entity_type = 'uriv:Port';
  protected $entity_types = array('uriv:Port');
  protected $entity_notation_types = array('xsd:unsignedShort');
  
  protected function source()
  {
    return get_ports();
  }
  
  protected function unmapId($id)
  {
    return is_numeric($id) ? $id : null;
  }
  
  protected function normalizeId($id)
  {
    return is_numeric($id) ? (string)(0+$id) : parent::normalizeId($id);
  }
  
  protected function add($graph, $port, $queries = false)
  {
    $subject = 'port:'.encodeIdentifier($port);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'port:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Port');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($port), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $port, 'xsd:unsignedShort');
    
    $ports = get_ports();
    $info = @$ports[$port];
    if(empty($info))
    {
      addIanaRecord($graph, $subject, $ports, null);
    }else while(is_array($info) && !empty($info))
    {
      if(!empty($info['protocol']))
      {
        $protocol = strtolower($info['protocol']);
        $specific = "$subject#$protocol";
        $graph->addCompressedTriple($subject, 'skos:narrower', $specific);
        $graph->addCompressedTriple($specific, 'rdf:type', 'uriv:Port');
        $graph->addCompressedTriple($specific, 'skos:notation', $port, 'xsd:unsignedShort');
        $graph->addCompressedTriple($specific, 'rdfs:label', $port.' ('.strtoupper($protocol).')', 'xsd:string');
        $desc = @$info['description'];
        addIanaRecord($graph, $specific, $ports, ($desc !== "Unassigned" && $desc !== "Reserved") ? $port : null);
        $graph->addCompressedTriple(self::addForType('protocol', $graph, $protocol), 'dcterms:hasPart', $specific);
        if(!empty($info['name']))
        {
          $service = $info['name'];
          $service_node = 'service:'.encodeIdentifier($service);
          $graph->addCompressedTriple($service_node, 'rdfs:isDefinedBy', 'service:');
          $this->addBaseTypes($graph, $service_node);
          $graph->addCompressedTriple($service_node, 'rdf:type', 'uriv:Service');
          $graph->addCompressedTriple($service_node, 'rdfs:label', strtoupper($service), 'xsd:string');
          $graph->addCompressedTriple($service_node, 'skos:notation', $service, 'uriv:ServiceDatatype');
          $graph->addCompressedTriple($service_node, 'dbp:ports', $specific);
        }
      }
      $info = @$info['additional'];
    }
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $protocol_values = array();
    foreach(get_protocols() as $protocol)
    {
      if(is_array($protocol))
      {
        $name = strtolower($protocol['id']);
        $protocol_values[] = "\"{$this->STR($name)}\"";
      }
    }
    $protocol_values = implode(' ', $protocol_values);
    
    $query = <<<EOF
CONSTRUCT {
  ?technology dbp:ports ?subject .
  ?subject a uriv:Port .
  ?subject rdfs:label ?protocol_label .
  ?subject skos:notation "{$this->STR($port)}"^^xsd:unsignedShort .
  $subject_node skos:narrower ?subject .
  ?protocol_node dcterms:hasPart ?subject .
  ?technology dcterms:hasPart ?service_node .
  {$this->CONSTRUCT_LABEL('?technology')}
  {$this->CONSTRUCT_PAGE('?technology')}
} WHERE {
  # port
  ?technology p:P1641 ?port_prop .
  ?port_prop ps:P1641 "{$this->STR($port)}"^^xsd:decimal .
  # of
  ?port_prop pq:P642 ?protocol .
  VALUES ?protocol_lower { $protocol_values }
  BIND(UCASE(?protocol_lower) AS ?protocol_upper)
  { ?protocol ?protocol_label_prop ?protocol_lower . } UNION { ?protocol ?protocol_label_prop ?protocol_upper . }
  BIND(CONCAT("{$this->STR($port)} (", ?protocol_upper, ")") AS ?protocol_label)
  BIND(URI(CONCAT("{$this->STR($this->URI($graph->expandURI($subject)))}#", ?protocol_lower)) AS ?subject)
  BIND(URI(CONCAT("{$this->STR($this->URI($graph->expandURI('protocol:')))}", ?protocol_lower)) AS ?protocol_node)
  # IANA service name
  OPTIONAL {
    ?technology wdt:P5814 ?service_name .
    BIND(URI(CONCAT("{$this->STR($this->URI($graph->expandURI('service:')))}", LCASE(?service_name))) AS ?service_node)
  }
  {$this->MATCH_PAGE('?technology')}
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class ServiceTriples extends Triples
{
  protected $entity_type = 'uriv:Service';
  protected $entity_types = array('uriv:Service');
  protected $entity_notation_types = array('uriv:ServiceDatatype');
  
  protected function source()
  {
    return get_services();
  }
  
  protected function label($service)
  {
    return strtoupper($service);
  }
  
  protected function add($graph, $service, $queries = false)
  {
    $subject = 'service:'.encodeIdentifier($service);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'service:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Service');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($service), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $service, 'uriv:ServiceDatatype');
    
    $services = get_services();
    $info = @$services[$service];
    if(empty($info))
    {
      addIanaRecord($graph, $subject, $services, null);
    }else while(is_array($info) && !empty($info))
    {
      addIanaRecord($graph, $subject, $services, $service);
      if(!empty($info['protocol']))
      {
        $protocol = strtolower($info['protocol']);
        if(!empty($info['number']))
        {
          $port = $info['number'];
          $specific = "port:$port#$protocol";
          $graph->addCompressedTriple($specific, 'rdf:type', 'uriv:Port');
          $graph->addCompressedTriple($specific, 'rdfs:label', $port.' ('.strtoupper($protocol).')', 'xsd:string');
          $graph->addCompressedTriple($specific, 'skos:notation', $port, 'xsd:unsignedShort');
          $graph->addCompressedTriple($subject, 'dbp:ports', $specific);
        }else{
          $specific = $subject;
        }
        $graph->addCompressedTriple(self::addForType('protocol', $graph, $protocol), 'dcterms:hasPart', $specific);
      }
      $info = @$info['additional'];
    }
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  {$this->CONSTRUCT_LABEL('?service', $subject_node)}
  $subject_node owl:sameAs ?service .
  {$this->CONSTRUCT_PAGE('?service', $subject_node)}
} WHERE {
  # IANA service name
  ?service wdt:P5814 "{$this->STR($service)}" .
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class ProtocolTriples extends Triples
{
  protected $entity_type = 'uriv:Protocol';
  protected $entity_types = array('uriv:Protocol');
  protected $entity_notation_types = array('uriv:ProtocolDatatype');
  
  protected function source()
  {
    return get_protocols();
  }
  
  protected function label($protocol)
  {
    return strtoupper($protocol);
  }
  
  protected function add($graph, $protocol, $queries = false)
  {
    $subject = 'protocol:'.encodeIdentifier($protocol);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'protocol:');
    
    $this->addBaseTypes($graph, $subject);
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Protocol');
    $graph->addCompressedTriple($subject, 'rdfs:label', $this->label($protocol), 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $protocol, 'uriv:ProtocolDatatype');
  
    $protocols = get_protocols();
    addIanaRecord($graph, $subject, $protocols, $protocol);
    
    if(!$queries) return $subject;
    
    $protocol_upper = strtoupper($protocol);
    
    $subject_node = "<{$this->URI($graph->expandURI($subject))}>";
    
    $query = <<<EOF
CONSTRUCT {
  $subject_node owl:sameAs ?protocol .
  {$this->CONSTRUCT_LABEL('?protocol', $subject_node)}
  {$this->CONSTRUCT_PAGE('?protocol', $subject_node)}
} WHERE {
  {
    # instance of/subclass of computer network protocol
    ?protocol wdt:P31/(wdt:P279)* wd:Q15836568 .
  } UNION {
    # port
    ?port_prop ps:P1641 ?port .
    # of
    ?port_prop pq:P642 ?protocol .
  }
  { ?protocol ?protocol_label_prop "{$this->STR($protocol)}" . } UNION { ?protocol ?protocol_label_prop "{$this->STR($protocol_upper)}" . }
  {$this->MATCH_PAGE('?protocol')}
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

function addExtraVocabTriples($graph)
{
  global $filepath;
  $lines = file("$filepath/nsextras.csv");
  $tmap = array(
    ''=>'skos:Concept',
    'c'=>'rdfs:Class',  
    'p'=>'rdf:Property',
    'd'=>'rdfs:Datatype');
  foreach($lines as $line)
  {
    if(preg_match('/^\s*(?:#|$)/', $line)) continue;
    list($term, $type, $name) = explode(",", rtrim($line));
    $graph->addCompressedTriple("$term", 'rdf:type', $tmap[$type]);
    $graph->addCompressedTriple("$term", 'rdfs:label', $name, 'literal');
  }
}
