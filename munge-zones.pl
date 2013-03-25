#!/usr/bin/perl 

use strict;
use warnings;

open( F,"<:utf8","root-zone-db.html" );
my $data = join("",<F>);
close F;


my $records = {};

my @tables = split( /<table/, $data );

my @trs = split( /<tr/, $tables[1] );
shift @trs;
shift @trs; # header row
foreach my $tr ( @trs )
{		
	$tr =~ s/<\/tr>.*//s;

	my $r = {};
	
	my @tds = split( /<td/, $tr );
	shift @tds;
#          '><span class="domain tld"><a href="/domains/root/db/xn--o3cw4h.html">.ไทย</a></span></td>
#                ',
#          '>country-code</td>
#                <!-- ',
#          '>Thailand<br/><span class="tld-table-so">Thai Network Information Center Foundation</span></td> </td> --
#>
#                ',
#          '>Thai Network Information Center Foundation</td>


	$r->{name} = $tds[0];
	$r->{name} =~ s/^[^>]*>//;
	$r->{name} =~ s/<[^>]*>//g;	
	$r->{name} =~ s/\s*//g;

	if( $tds[0] =~ m/href="([^"]*)/ )
	{
		$r->{url} = $1;
		$r->{url} =~ m/\/([a-z0-9-]+)\.html$/;
		$r->{id} = $1;
	}

	$r->{type} = $tds[1];
	$r->{type} =~ s/<\/td>.*//s;
	$r->{type} =~ s/^[^>]*>//;
	$r->{type} =~ s/<[^>]*>//g;	
	$r->{type} =~ s/^\s*//s;
	$r->{type} =~ s/\s*$//s;

	$r->{sponsor} = $tds[3];
	$r->{sponsor} =~ s/^[^>]*>//;
	$r->{sponsor} =~ s/<[^>]*>//g;	
	$r->{sponsor} =~ s/^\s*//s;
	$r->{sponsor} =~ s/\s*$//s;
	$r->{sponsor} =~ s/&#39;/'/g;
	$r->{sponsor} =~ s/&amp;/&/g;

	$records->{$r->{id}} = $r;
}
use Data::Dumper;
use JSON;
binmode( STDOUT, ":utf8" );
my  $json_text   = to_json( $records, {  pretty => 1 } );
print $json_text;


