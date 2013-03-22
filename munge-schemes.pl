#!/usr/bin/perl 

use strict;
use warnings;

open( F,"uri-schemes.html" );
my $data = join("",<F>);
close F;

my @sections = split( /\+2/, $data );
shift @sections;

foreach my $section ( @sections )
{
	print "\n----\n\n$section";
}
