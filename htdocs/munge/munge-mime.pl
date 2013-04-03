#!/usr/bin/perl 

use JSON;
use Data::Dumper;

use strict;
use warnings;

open( E, "mime.in.json");
my $in = from_json( join( '', <E> ), { utf8  => 1 } );
close E;
my $j;

foreach my $item ( @{$in->{results}->{bindings}} )
{
#thing,label,dest,extension
	foreach my $id ( split( /[ \.,;]+/, $item->{mime}->{value} ) )
	{
		next if $id eq "";
		$j->{$id}->{$item->{thing}->{value}} = { 
			uri=>$item->{thing}->{value},
			desc=>$item->{desc}->{value},
			label=>$item->{label}->{value},
		};
	}
}
binmode( STDOUT, ":utf8" );
my  $json_text   = to_json( $j, {  pretty => 1 } );
print $json_text;
