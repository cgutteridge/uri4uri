<?php

/*************************************************************************
php easy :: whois lookup script
==========================================================================
Author:      php easy code, www.phpeasycode.com
Web Site:    http://www.phpeasycode.com
Contact:     webmaster@phpeasycode.com
(heavily hacked by Christopher Gutteridge to do stuff on this site)
*************************************************************************/

// For the full list of TLDs/Whois servers see http://www.iana.org/domains/root/db/ and http://www.whois365.com/en/listtld/

function whoisservers()
{
	return array(
"ac.uk" => "whois.ja.net",
	"ac" =>"whois.nic.ac",
	"ae" =>"whois.nic.ae",
	"aero"=>"whois.aero",
	"af" =>"whois.nic.af",
	"ag" =>"whois.nic.ag",
	"al" =>"whois.ripe.net",
	"am" =>"whois.amnic.net",
	"arpa" =>"whois.iana.org",
	"as" =>"whois.nic.as",
	"asia" =>"whois.nic.asia",
	"at" =>"whois.nic.at",
	"au" =>"whois.aunic.net",
	"az" =>"whois.ripe.net",
	"ba" =>"whois.ripe.net",
	"be" =>"whois.dns.be",
	"bg" =>"whois.register.bg",
	"bi" =>"whois.nic.bi",
	"biz" =>"whois.biz",
	"bj" =>"whois.nic.bj",
	"br" =>"whois.registro.br",
	"bt" =>"whois.netnames.net",
	"by" =>"whois.ripe.net",
	"bz" =>"whois.belizenic.bz",
	"ca" =>"whois.cira.ca",
	"cat" =>"whois.cat",
	"cc" =>"whois.nic.cc",
	"cd" =>"whois.nic.cd",
	"ch" =>"whois.nic.ch",
	"ci" =>"whois.nic.ci",
	"ck" =>"whois.nic.ck",
	"cl" =>"whois.nic.cl",
	"cn" =>"whois.cnnic.net.cn",
	"com" =>"whois.verisign-grs.com",
	"coop" =>"whois.nic.coop",
	"cx" =>"whois.nic.cx",
	"cy" =>"whois.ripe.net",
	"cz" =>"whois.nic.cz",
	"de" =>"whois.denic.de",
	"dk" =>"whois.dk-hostmaster.dk",
	"dm" =>"whois.nic.cx",
	"dz" =>"whois.ripe.net",
	"edu" =>"whois.educause.edu",
	"ee" =>"whois.eenet.ee",
	"eg" =>"whois.ripe.net",
	"es" =>"whois.ripe.net",
	"eu" =>"whois.eu",
	"fi" =>"whois.ficora.fi",
	"fo" =>"whois.ripe.net",
	"fr" =>"whois.nic.fr",
	"gb" =>"whois.ripe.net",
	"gd" =>"whois.adamsnames.com",
	"ge" =>"whois.ripe.net",
	"gg" =>"whois.channelisles.net",
	"gi" =>"whois2.afilias-grs.net",
	"gl" =>"whois.ripe.net",
	"gm" =>"whois.ripe.net",
	"gov" =>"whois.nic.gov",
	"gr" =>"whois.ripe.net",
	"gs" =>"whois.nic.gs",
	"gw" =>"whois.nic.gw",
	"gy" =>"whois.registry.gy",
	"hk" =>"whois.hkirc.hk",
	"hm" =>"whois.registry.hm",
	"hn" =>"whois2.afilias-grs.net",
	"hr" =>"whois.ripe.net",
	"hu" =>"whois.nic.hu",
	"ie" =>"whois.domainregistry.ie",
	"il" =>"whois.isoc.org.il",
	"in" =>"whois.inregistry.net",
	"info" =>"whois.afilias.net",
	"int" =>"whois.iana.org",
	"io" =>"whois.nic.io",
	"iq" =>"vrx.net",
	"ir" =>"whois.nic.ir",
	"is" =>"whois.isnic.is",
	"it" =>"whois.nic.it",
	"je" =>"whois.channelisles.net",
	"jobs" =>"jobswhois.verisign-grs.com",
	"jp" =>"whois.jprs.jp",
	"ke" =>"whois.kenic.or.ke",
	"kg" =>"www.domain.kg",
	"ki" =>"whois.nic.ki",
	"kr" =>"whois.nic.or.kr",
	"kz" =>"whois.nic.kz",
	"la" =>"whois.nic.la",
	"li" =>"whois.nic.li",
	"lt" =>"whois.domreg.lt",
	"lu" =>"whois.dns.lu",
	"lv" =>"whois.nic.lv",
	"ly" =>"whois.nic.ly",
	"ma" =>"whois.iam.net.ma",
	"mc" =>"whois.ripe.net",
	"md" =>"whois.ripe.net",
	"me" =>"whois.meregistry.net",
	"mg" =>"whois.nic.mg",
	"mil" =>"whois.nic.mil",
	"mn" =>"whois.nic.mn",
	"mobi" =>"whois.dotmobiregistry.net",
	"ms" =>"whois.adamsnames.tc",
	"mt" =>"whois.ripe.net",
	"mu" =>"whois.nic.mu",
	"museum" =>"whois.museum",
	"mx" =>"whois.nic.mx",
	"my" =>"whois.mynic.net.my",
	"na" =>"whois.na-nic.com.na",
	"name" =>"whois.nic.name",
	"net" =>"whois.verisign-grs.net",
	"nf" =>"whois.nic.nf",
	"nl" =>"whois.domain-registry.nl",
	"no" =>"whois.norid.no",
	"nu" =>"whois.nic.nu",
	"nz" =>"whois.srs.net.nz",
	"org" =>"whois.pir.org",
	"pl" =>"whois.dns.pl",
	"pm" =>"whois.nic.pm",
	"pr" =>"whois.uprr.pr",
	"pro" =>"whois.registrypro.pro",
	"pt" =>"whois.dns.pt",
	"re" =>"whois.nic.re",
	"ro" =>"whois.rotld.ro",
	"ru" =>"whois.ripn.net",
	"sa" =>"whois.nic.net.sa",
	"sb" =>"whois.nic.net.sb",
	"sc" =>"whois2.afilias-grs.net",
	"se" =>"whois.iis.se",
	"sg" =>"whois.nic.net.sg",
	"sh" =>"whois.nic.sh",
	"si" =>"whois.arnes.si",
	"sk" =>"whois.ripe.net",
	"sm" =>"whois.ripe.net",
	"st" =>"whois.nic.st",
	"su" =>"whois.ripn.net",
	"tc" =>"whois.adamsnames.tc",
	"tel" =>"whois.nic.tel",
	"tf" =>"whois.nic.tf",
	"th" =>"whois.thnic.net",
	"tj" =>"whois.nic.tj",
	"tk" =>"whois.dot.tk",
	"tl" =>"whois.nic.tl",
	"tm" =>"whois.nic.tm",
	"tn" =>"whois.ripe.net",
	"to" =>"whois.tonic.to",
	"tp" =>"whois.nic.tl",
	"tr" =>"whois.nic.tr",
	"travel" =>"whois.nic.travel",
	"tv" => "tvwhois.verisign-grs.com",
	"tw" =>"whois.twnic.net.tw",
	"ua" =>"whois.net.ua",
	"ug" =>"whois.co.ug",
	"uk" =>"whois.nic.uk",
	"us" =>"whois.nic.us",
	"uy" =>"nic.uy",
	"uz" =>"whois.cctld.uz",
	"va" =>"whois.ripe.net",
	"vc" =>"whois2.afilias-grs.net",
	"ve" =>"whois.nic.ve",
	"vg" =>"whois.adamsnames.tc",
	"wf" =>"whois.nic.wf",
	"ws" =>"whois.website.ws",
	"yt" =>"whois.nic.yt",
	"yu" =>"whois.ripe.net");
}

function LookupDomain($domain, $whoisserver){
	$domain_parts = explode(".", $domain);
	$tld = strtolower(array_pop($domain_parts));
	$result = QueryWhoisServer($whoisserver, $domain);
	if(!$result) {
		return;
	}
	else {
		while(strpos($result, "Whois Server:") !== FALSE){
			preg_match("/Whois Server: (.*)/", $result, $matches);
			$secondary = $matches[1];
			if($secondary) {
				$result = QueryWhoisServer($secondary, $domain);
				$whoisserver = $secondary;
			}
		}
	}
	return "$result";
}

function LookupIP($ip) {
	$whoisservers = array(
		//"whois.afrinic.net", // Africa - returns timeout error :-(
		"whois.lacnic.net", // Latin America and Caribbean - returns data for ALL locations worldwide :-)
		"whois.apnic.net", // Asia/Pacific only
		"whois.arin.net", // North America only
		"whois.ripe.net" // Europe, Middle East and Central Asia only
	);
	$results = array();
	foreach($whoisservers as $whoisserver) {
		$result = QueryWhoisServer($whoisserver, $ip);
		if($result && !in_array($result, $results)) {
			$results[$whoisserver]= $result;
		}
	}
	$res = "RESULTS FOUND: " . count($results);
	foreach($results as $whoisserver=>$result) {
		$res .= "\n\n-------------\nLookup results for " . $ip . " from " . $whoisserver . " server:\n\n" . $result;
	}
	return $res;
}

function ValidateIP($ip) {
	$ipnums = explode(".", $ip);
	if(count($ipnums) != 4) {
		return false;
	}
	foreach($ipnums as $ipnum) {
		if(!is_numeric($ipnum) || ($ipnum > 255)) {
			return false;
		}
	}
	return $ip;
}

function ValidateDomain($domain) {
	if(!preg_match("/^([-a-z0-9]{2,100})\.([a-z\.]{2,8})$/i", $domain)) {
		return false;
	}
	return $domain;
}

function QueryWhoisServer($whoisserver, $domain) {
	$port = 43;
	$timeout = 5;
	$fp = @fsockopen($whoisserver, $port, $errno, $errstr, $timeout) or die("Socket Error " . $errno . " - " . $errstr);
	//if($whoisserver == "whois.verisign-grs.com") $domain = "=".$domain; // whois.verisign-grs.com requires the equals sign ("=") or it returns any result containing the searched string.
	fputs($fp, $domain . "\r\n");
	$out = "";
	while(!feof($fp)){
		$out .= fgets($fp);
	}
	fclose($fp);

	$res = "";
	if((strpos(strtolower($out), "error") === FALSE) && (strpos(strtolower($out), "not allocated") === FALSE)) {
		$rows = explode("\n", $out);
		foreach($rows as $row) {
			$row = trim($row);
			if(($row != '') && ($row{0} != '#') && ($row{0} != '%')) {
				$res .= $row."\n";
			}
		}
	}
	return $res;
}

