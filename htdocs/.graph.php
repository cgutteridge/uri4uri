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
  $graph->ns('domain', "$PREFIX/domain/");
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
  $graph->ns('olddomain', "$PREFIX_OLD/domain/");
  $graph->ns('oldsuffix', "$PREFIX_OLD/suffix/");
  $graph->ns('oldmime', "$PREFIX_OLD/mime/");
  $graph->ns('vs', 'http://www.w3.org/2003/06/sw-vocab-status/ns#');
  $graph->ns('dbo', 'http://dbpedia.org/ontology/');
  $graph->ns('dbp', 'http://dbpedia.org/property/');
  $graph->ns('prov', 'http://www.w3.org/ns/prov#');
  $graph->ns('vann', 'http://purl.org/vocab/vann/');
  $graph->ns('schema', 'http://schema.org/');
  
  return $graph;
}

abstract class Triples
{
  protected $link_old = false;
  protected $vocab_full = false; 
  abstract protected function add($graph, $uri, $queries = false);
  
  protected function source()
  {
    return array();
  }
  
  protected function unmapId($id)
  {
    return $id;
  }
  
  static $map;
  
  static function map($type)
  {
    if(!isset(self::$map))
    {
      self::$map = array(
        'uri' => new URITriples,
        'scheme' => new SchemeTriples,
        'suffix' => new SuffixTriples,
        'part' => new URIPartTriples,
        'field' => new FieldTriples,
        'domain' => new DomainTriples,
        'mime' => new MIMETriples,
        'urn' => new URNNamespaceTriples,
        'well-known' => new WellknownTriples,
        'port' => new PortTriples,
        'service' => new ServiceTriples,
        'protocol' => new ProtocolTriples
      );
    }
    return self::$map[$type];
  }
  
  public static function addForType($type, $graph, $id, $queries = false, &$link_old = false)
  {
    $triples = self::map($type);
    $link_old = $triples->link_old;
    return $triples->add($graph, $id, $queries);
  }
  
  public static function addAllForType($type, $graph, $queries = false, &$link_old = false)
  {
    global $PREFIX;
    $triples = self::map($type);
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
      if(str_starts_with($id, '#') || !is_array($info)) continue;
      $id = $triples->unmapId($id);
      if($id === null) continue;
      if($triples->vocab_full)
      {
        $subject = $triples->add($graph, $id, $queries);
        continue;
      }
      $subject = "$PREFIX/$type/".urlencode_minimal($id);
      $graph->addCompressedTriple($subject, 'rdfs:label', $id, 'xsd:string');
      $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', $ontology);
    }
    return $ontology;
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
      $prop_res wdt:P1630 $formatter .
      BIND(URI(REPLACE(STR($page_id), "^(.*)$", STR($formatter))) AS $page)
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

function graphVocab($id)
{
  $graph = initGraph();
  $subject = 'uriv:';
  addBoilerplateTriples($graph, $subject, "URI Vocabulary");
  $graph->addCompressedTriple($subject, 'rdf:type', 'owl:Ontology');
  $graph->addCompressedTriple($subject, 'dcterms:title', "URI Vocabulary", 'literal', 'en');
  $graph->addCompressedTriple($subject, 'vann:preferredNamespaceUri', $graph->expandURI($subject), 'xsd:anyURI');
  $graph->addCompressedTriple($subject, 'vann:preferredNamespacePrefix', rtrim($subject, ':'), 'xsd:string');
  addVocabTriples($graph);

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
    '' => 'skos:Concept',
    'c' => 'rdfs:Class',  
    'p' => 'rdf:Property',
    'd' => 'rdfs:Datatype'
  );
  foreach($lines as $line)
  {
    @list($term, $type, $status, $name, $replaced) = explode(",", rtrim($line));
    $term = "uriv:$term";
    $graph->addCompressedTriple($term, 'rdf:type', $tmap[$type]);
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
  $graph->addCompressedTriple($subject, 'vann:preferredNamespacePrefix', rtrim($graph->shrinkURI($subject), ':'), 'xsd:string');
  addBoilerplateTriples($graph, $subject, $type, $link_old);
  return $graph;
}

function addBoilerplateTriples($graph, $uri, $title, $link_old = true)
{
  global $PREFIX;
  global $PREFIX_OLD;
  $document_url = $PREFIX.$_SERVER['REQUEST_URI'];
  $graph->addCompressedTriple($document_url, 'rdf:type', 'foaf:Document');
  $graph->addCompressedTriple($document_url, 'dcterms:title', $title, 'literal');
  $graph->addCompressedTriple($document_url, 'foaf:primaryTopic', $uri);
  
  if($link_old) linkOldConcept($graph, $uri, '');
}

class URITriples extends Triples
{
  protected $link_old = true;

  protected function add($graph, $uri, $queries = false)
  {
    $subject = 'uri:'.urlencode_minimal($uri);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'uri:');
    $b = parse_url_fixed($uri);
  
    if(isset($b['fragment']))
    {
      list($uri_part) = explode('#', $uri, 2);
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:FragmentURI');
      $graph->addCompressedTriple($subject, 'uriv:fragment', self::addForType('part', $graph, $b['fragment']));
      $graph->addCompressedTriple($subject, 'uriv:fragmentOf', self::addForType('uri', $graph, $uri_part));
    }
  
    if(isset($b['scheme']))
    {
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URI');
    }else{
      $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:RelativeURI');
    }
    $graph->addCompressedTriple($subject, 'skos:notation', $uri, 'xsd:anyURI');
    
    if(!empty($b['host']))
    {
      $domain_subject = self::addForType('domain', $graph, strtolower($b['host']));
      $graph->addCompressedTriple($subject, 'uriv:host', $domain_subject);
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
    
          $graph->addCompressedTriple($domain_subject, 'foaf:homepage', $homepage);
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
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
    $query = <<<EOF
CONSTRUCT {
  ?thing dcterms:hasPart $subject_node .
  {$this->CONSTRUCT_PAGE('?thing')}
  {$this->CONSTRUCT_LABEL('?thing')}
} WHERE {
  ?thing ?prop <$uri> .
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
  
  protected function source()
  {
    return array('' => array());
  }

  protected function add($graph, $part, $queries = false)
  {
    if(empty($part))
    {
      $subject = 'uripart:#';
    }else{
      $subject = 'uripart:'.urlencode_minimal($part);
    }
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'uripart:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIPart');
    $graph->addCompressedTriple($subject, 'rdfs:label', $part, 'xsd:string');
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
      $parts = preg_split('/[;&]/', $part);
      if(!empty($parts))
      {
        $graph->addCompressedTriple($subject, 'rdf:type', 'rdf:Seq');
        $i = 0;
        foreach($parts as $kv)
        {
          ++$i;
          $field_subject = "$subject#_$i";
          $graph->addCompressedTriple($subject, "rdf:_$i", $field_subject);
          $graph->addCompressedTriple($field_subject, 'rdf:type', 'uriv:QueryKVP');
          if(strpos($kv, '=') !== false)
          {
            list($key, $value) = explode('=', $kv, 2);
            $graph->addCompressedTriple($field_subject, 'schema:propertyID', urldecode($key), 'literal');
            $graph->addCompressedTriple($field_subject, 'schema:value', urldecode($value), 'literal');
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
      $field = "rdf:_$key";
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
      $graph->addCompressedTriple($subject, $field, $value, 'literal');
    }
  }
}

class FieldTriples extends Triples
{
  protected function add($graph, $field, $queries = false)
  {
    $subject = 'field:'.urlencode_minimal($field);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'field:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'rdf:Property');
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIField');
    $graph->addCompressedTriple($subject, 'rdfs:label', $field, 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $field, 'uriv:URIFieldDatatype');   
    $graph->addCompressedTriple($subject, 'schema:propertyID', $field, 'literal');
    
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
    $graph->addCompressedTriple($subject, 'dcterms:date', $info['date'], 'xsd:date');
  }
  if(isset($info['updated']))
  {
    $graph->addCompressedTriple($subject, 'dcterms:modified', $info['updated'], 'xsd:date');
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
      $registry = $records['#source'].'#table-'.$info['registry'];
      $graph->addCompressedTriple($subject, 'vs:moreinfo', $registry);
      $graph->addCompressedTriple($registry, 'rdf:type', 'foaf:Document');
    }
  }
  
  return $info;
}

class DomainTriples extends Triples
{
  protected $link_old = true;
  
  protected function source()
  {
    return get_tlds();
  }
  
  protected function unmapId($id)
  {
    return rtrim($id, '.');
  }

  protected function add($graph, $domain, $queries = false, &$special_type = null)
  {
    $subject = 'domain:'.urlencode_minimal($domain);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'domain:');
    
    $domain_idn = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Domain');
    $graph->addCompressedTriple($subject, 'rdfs:label', $domain, 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $domain, 'uriv:DomainDatatype');
    if($domain_idn !== $domain)
    {
      $graph->addCompressedTriple($subject, 'skos:notation', $domain_idn, 'uriv:DomainDatatype-Encoded');
    }
    $graph->addCompressedTriple($subject, 'uriv:whoIsRecord', "https://www.iana.org/whois?q=$domain_idn");
    
    $special_domains = get_special_domains();
    if(isset($special_domains["$domain_idn."]))
    {
      addIanaRecord($graph, $subject, $special_domains, "$domain_idn.");
      $special_type = 'uriv:Domain-Special';
    }
  
    # Super Domains
    if(strpos($domain, ".") !== false)
    {
      list($domain_name, $domain) = explode(".", $domain, 2);
      $inner_subject = $this->add($graph, $domain, $queries, $special_type);
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
    if(isset($tlds['#source']))
    {
      $graph->addCompressedTriple($subject, 'prov:wasDerivedFrom', $tlds['#source']);
      $graph->addCompressedTriple($tlds['#source'], 'rdf:type', 'foaf:Document');
    }
    if(isset($tlds[$domain_idn]))
    {
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
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
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
  ?domain wdt:P5914 "$domain_idn" .
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
  
  protected function add($graph, $suffix, $queries = false)
  {
    $subject = 'suffix:'.urlencode_minimal($suffix);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'suffix:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Suffix');
    $graph->addCompressedTriple($subject, 'rdfs:label', ".$suffix", 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $suffix, 'uriv:SuffixDatatype');
    
    if(!$queries) return $subject;
  
    $suffix_lower = strtolower($suffix);
    $suffix_upper = strtoupper($suffix);
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
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
  { ?format wdt:P1195 "$suffix_lower" . } UNION { ?format wdt:P1195 "$suffix_upper" . }
  {$this->MATCH_PAGE('?format')}
  OPTIONAL {
    # MIME type
    ?format wdt:P1163 ?mime_str .
    FILTER (isLiteral(?mime_str) && STR(?mime_str) != "application/octet-stream")
    BIND(STRDT(?mime_str, uriv:MimetypeDatatype) AS ?mime_notation)
    BIND(URI(CONCAT("{$graph->expandURI("mime:")}", ?mime_str)) AS ?mime)
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
  
  protected function source()
  {
    return get_mime_types();
  }
  
  protected function add($graph, $mime, $queries = false)
  {
    $subject = 'mime:'.urlencode_minimal($mime);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'mime:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Mimetype');
    $graph->addCompressedTriple($subject, 'rdfs:label', $mime, 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $mime, 'uriv:MimetypeDatatype');
    
    $mime_types = get_mime_types();
    addIanaRecord($graph, $subject, $mime_types, $mime);
      
    @list(, $suffix_type) = explode("+", $mime, 2);
    if(!empty($suffix_type))
    {
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
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
    $filter_query = '';
    if($mime === 'text/plain' || $mime === 'application/octet-stream')
    {
      $filter_query = <<<EOF
    # MIME type
    ?format p:P1163 ?mime_prop .
    ?mime_prop ps:P1163 "$mime" .
    ?mime_prop prov:wasDerivedFrom ?mime_source .
    FILTER NOT EXISTS {
      ?format wdt:P1163 ?other_mime .
      FILTER(STR(?other_mime) != "$mime")
    }
EOF;
    }else{
      $filter_query = <<<EOF
    # MIME type
    ?format wdt:P1163 "$mime" .
EOF;
    }
    
    $query = <<<EOF
CONSTRUCT {
  ?format dbp:mime $subject_node .
  ?format a uriv:Format .
  {$this->CONSTRUCT_LABEL('?format')}
  {$this->CONSTRUCT_PAGE('?format')}
  ?format dbp:extension ?suffix .
  ?suffix a uriv:Suffix .
  ?suffix rdfs:label ?suffix_label .
  ?suffix skos:notation ?suffix_notation .
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
    BIND(URI(CONCAT("{$graph->expandURI("suffix:")}", ?suffix_str)) AS ?suffix)
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
  
  protected function source()
  {
    return get_schemes();
  }
  
  protected function add($graph, $scheme, $queries = false)
  {
    $subject = 'scheme:'.urlencode_minimal($scheme);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'scheme:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:URIScheme');
    $graph->addCompressedTriple($subject, 'rdfs:label', $scheme, 'xsd:string');
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
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
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
    ?scheme wdt:P4742 "$scheme" .
    # instance of Uniform Resource Identifier scheme
    ?scheme wdt:P31 wd:Q37071 .
    {$this->MATCH_PAGE('?scheme')}
  }
  OPTIONAL {
    # Uniform Resource Identifier Scheme
    ?technology wdt:P4742 "$scheme" .
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
  protected function source()
  {
    return get_urn_namespaces();
  }
  
  protected function add($graph, $ns, $queries = false)
  {
    $subject = 'urnns:'.urlencode_minimal($ns);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'urnns:');
    
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
    $graph->addCompressedTriple($subject, 'rdfs:label', $ns, 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $ns, 'uriv:URNNamespaceDatatype');
  
    $namespaces = get_urn_namespaces();
    addIanaRecord($graph, $subject, $namespaces, $ns);
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
    $query = <<<EOF
CONSTRUCT {
  ?technology dcterms:hasPart $subject_node .
  {$this->CONSTRUCT_LABEL('?technology')}
  {$this->CONSTRUCT_PAGE('?technology')}
} WHERE {
  # URN formatter
  ?technology wdt:P7470 "urn:$ns:\$1" .
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
  protected function source()
  {
    return get_wellknown_uris();
  }
  
  protected function add($graph, $suffix, $queries = false)
  {
    $subject = 'wellknown:'.urlencode_minimal($suffix);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'wellknown:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:WellKnownURISuffix');
    $graph->addCompressedTriple($subject, 'rdfs:label', $suffix, 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $suffix, 'uriv:WellKnownURISuffixDatatype');
  
    $wellknown = get_wellknown_uris();
    addIanaRecord($graph, $subject, $wellknown, $suffix);
    
    return $subject;
  }
}

class PortTriples extends Triples
{
  protected function source()
  {
    return get_ports();
  }
  
  protected function unmapId($id)
  {
    return is_numeric($id) ? $id : null;
  }
  
  protected function add($graph, $port, $queries = false)
  {
    $subject = 'port:'.urlencode_minimal($port);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'port:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Port');
    $graph->addCompressedTriple($subject, 'rdfs:label', $port, 'xsd:string');
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
          $service_node = 'service:'.urlencode_minimal($service);
          $graph->addCompressedTriple($service_node, 'rdfs:isDefinedBy', 'service:');
          $graph->addCompressedTriple($service_node, 'rdf:type', 'uriv:Service');
          $graph->addCompressedTriple($service_node, 'rdfs:label', $service, 'xsd:string');
          $graph->addCompressedTriple($service_node, 'skos:notation', $service, 'uriv:ServiceDatatype');
          $graph->addCompressedTriple($service_node, 'dbp:ports', $specific);
        }
      }
      $info = @$info['additional'];
    }
    
    if(!$queries) return $subject;
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
    $protocol_values = array();
    foreach(get_protocols() as $protocol)
    {
      if(is_array($protocol))
      {
        $name = strtolower($protocol['id']);
        $protocol_values[] = "\"$name\"";
      }
    }
    $protocol_values = implode(' ', $protocol_values);
    
    $query = <<<EOF
CONSTRUCT {
  ?technology dbp:ports ?subject .
  ?subject a uriv:Port .
  ?subject rdfs:label "$port" .
  ?subject skos:notation "$port"^^xsd:unsignedShort .
  $subject_node skos:narrower ?subject .
  ?protocol_node dcterms:hasPart ?subject .
  ?technology dcterms:hasPart ?service_node .
  {$this->CONSTRUCT_LABEL('?technology')}
  {$this->CONSTRUCT_PAGE('?technology')}
} WHERE {
  # port
  ?technology p:P1641 ?port_prop .
  ?port_prop ps:P1641 "$port"^^xsd:decimal .
  # of
  ?port_prop pq:P642 ?protocol .
  VALUES ?protocol_lower { $protocol_values }
  BIND(UCASE(?protocol_lower) AS ?protocol_upper)
  { ?protocol ?protocol_label_prop ?protocol_lower . } UNION { ?protocol ?protocol_label_prop ?protocol_upper . }
  BIND(URI(CONCAT("{$graph->expandURI($subject)}#", ?protocol_lower)) AS ?subject)
  BIND(URI(CONCAT("{$graph->expandURI('protocol:')}", ?protocol_lower)) AS ?protocol_node)
  # IANA service name
  OPTIONAL {
    ?technology wdt:P5814 ?service_name .
    BIND(URI(CONCAT("{$graph->expandURI('service:')}", LCASE(?service_name))) AS ?service_node)
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
  protected function source()
  {
    return get_services();
  }
  
  protected function add($graph, $service, $queries = false)
  {
    $subject = 'service:'.urlencode_minimal($service);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'service:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Service');
    $graph->addCompressedTriple($subject, 'rdfs:label', $service, 'xsd:string');
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
          $graph->addCompressedTriple($subject, 'rdfs:label', $port, 'xsd:string');
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
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
    $query = <<<EOF
CONSTRUCT {
  {$this->CONSTRUCT_LABEL('?service', $subject_node)}
  $subject_node owl:sameAs ?service .
  {$this->CONSTRUCT_PAGE('?service', $subject_node)}
} WHERE {
  # IANA service name
  ?service wdt:P5814 "$service" .
  {$this->LABELS()}
}
EOF;
    addWikidataResult($graph, $query);
    
    return $subject;
  }
}

class ProtocolTriples extends Triples
{
  protected function source()
  {
    return get_protocols();
  }
  
  protected function add($graph, $protocol, $queries = false)
  {
    $subject = 'protocol:'.urlencode_minimal($protocol);
    $graph->addCompressedTriple($subject, 'rdfs:isDefinedBy', 'protocol:');
    
    $graph->addCompressedTriple($subject, 'rdf:type', 'uriv:Protocol');
    $graph->addCompressedTriple($subject, 'rdfs:label', $protocol, 'xsd:string');
    $graph->addCompressedTriple($subject, 'skos:notation', $protocol, 'uriv:ProtocolDatatype');
  
    $protocols = get_protocols();
    addIanaRecord($graph, $subject, $protocols, $protocol);
    
    if(!$queries) return $subject;
    
    $protocol_upper = strtoupper($protocol);
    
    $subject_node = "<{$graph->expandURI($subject)}>";
    
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
  { ?protocol ?protocol_label_prop "$protocol" . } UNION { ?protocol ?protocol_label_prop "$protocol_upper" . }
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
    list($term, $type, $name) = explode(",", rtrim($line));
    $graph->addCompressedTriple("$term", 'rdf:type', $tmap[$type]);
    $graph->addCompressedTriple("$term", 'rdfs:isDefinedBy', 'uriv:');
    $graph->addCompressedTriple("$term", 'rdfs:label', $name, 'literal');
  }
}
