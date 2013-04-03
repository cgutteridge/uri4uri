#!/usr/bin/perl 

use strict;
use warnings;

open( F,"uri-schemes.html" );
my $data = join("",<F>);
close F;

my @sections = split( /\+2/, $data );
shift @sections;

my $sid = 0;
my $records = {};
foreach my $section ( @sections )
{
	++$sid;
	my @trs = split( /<tr/, $section );
	shift @trs;
	foreach my $tr ( @trs )
	{		

		my $r = { refs=>{} };

		my @tds = split( /<td/, $tr );
		shift @tds;

		$r->{type} = [qw/ a b c permenent provisional historical /]->[$sid];

		$r->{id} = $tds[0];
		$r->{id} =~ s/^[^>]*>//;
		$r->{id} =~ s/<[^>]*>//g;	
		$r->{id} =~ s/\s*//g;

		if( $tds[0] =~ m/href="([^"]*)/ )
		{
			$r->{url} = $1;
		}

		$r->{name} = $tds[1];
		$r->{name} =~ s/^[^>]*>//;
		$r->{name} =~ s/<[^>]*>//g;	
		$r->{name} =~ s/^\s*//s;
		$r->{name} =~ s/\s*$//s;
	
		foreach my $ref ( split( /\[/, $tds[2] ) )
		{		
			if( $ref =~ m/href="([^"]*)/ )
			{
				my $url = $1;
				my $name = $ref;
				$name=~s/\]//g;
				$name =~ s/<[^>]*>//g;	
				$name =~ s/^\s*//s;
				$name =~ s/\s*$//s;
				$r->{refs}->{$url} = $name;
			}
		}
		$records->{$r->{id}} = $r;
	}
}
use Data::Dumper;
#print Dumper( $records );
use JSON;

my  $json_text   = to_json( $records, {  pretty => 1 } );
print $json_text;


__DATA__
<td><a href="http://www.iana.org/assignments/uri-schemes/prov/ymsgr">ymsgr</a></td>

<td><font color="#333333">ymsgr</font></td>

<td><font color="#333333">[<a href="http://www.iana.org/assignments/contact-people.html#Thaler">Thaler</a>]</font><
/td>


