#!/usr/bin/perl -wT -I.

#
# Copyright (c) 2008-2013 ZipList, Inc.
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
#
# THE ZIPLIST SOFTWARE IS PROVIDED BY ZIPLIST, INC ON AN "AS IS" BASIS.
# ZIPLIST MAKES NO WARRANTIES, EXPRESS OR IMPLIED, INCLUDING WITHOUT
# LIMITATION THE IMPLIED WARRANTIES OF NON-INFRINGEMENT, MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE, REGARDING THE ZIPLIST SOFTWARE
# OR ITS USE AND OPERATION ALONE OR IN COMBINATION WITH YOUR PRODUCTS.
#
# IN NO EVENT SHALL ZIPLIST BE LIABLE FOR ANY SPECIAL, INDIRECT, INCIDENTAL
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) ARISING IN ANY WAY OUT OF THE USE, REPRODUCTION,
# MODIFICATION AND/OR DISTRIBUTION OF THE ZIPLIST SOFTWARE, HOWEVER CAUSED
# AND WHETHER UNDER THEORY OF CONTRACT, TORT (INCLUDING NEGLIGENCE),
# STRICT LIABILITY OR OTHERWISE, EVEN IF ZIPLIST HAS BEEN ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.
#
# ACCESS TO THIS SOURCE CODE DOES NOT GRANT NOR DOES IT IMPLY A LICENSE
# AGREEMENT WITH ZIPLIST, TO USE ITS APIS.
#

#
# Run some basic tests using the shim.
#

use strict;
use ZipList::SpiceConnection;
use Digest::HMAC_SHA1;
use Data::Dumper;

my $partner_key    = "joesfoodblog";
my $partner_secret = "0123456789abcdeffedcba9876543210";

# Don't forget to specify port 443 for most SSL transactions.
my $test_use_ssl = 0;
my $test_host = "api.zltest.info";
# my $test_port = 443;
my $test_port = 80;



sub assert_success
{
    my ( $message, $connection, $result ) = @_;

    if ( $connection->{last_error_number} != 0 )
    {
        die("$message, failed - HTTP operation failed: $connection->{last_error_message}\n");
    }
    elsif ( ( $connection->{last_http_code} < 200 ) ||
            ( $connection->{last_http_code} >= 300  ) )
    {
        print Dumper($result);
        print "\n";
        die("$message, failed - Spice call failed: $connection->{last_http_code} \n");
    }
    else
    {
        printf("%-58s - passed.\n", $message);
    }

    1;
}


sub assert
{
    my $message = shift;
    my $test_func= shift;

    if ( $test_func->(@_) )
    {
        print("$message - passed.\n");
    }
    else
    {
        print("$message - failed.\n");
    }


}


sub dump_recipe_box
{
    my $result = shift;

    #
    # Print the zlid, publisher_recipe_id and title of each recipe in the box.
    #
    my $count = 0;
    my $recipes = $result->{recipes};
    foreach ( @$recipes )
    {
        printf("  recipe(%2d) %s - %s\n", $count, $_->{zlid}, $_->{title});
        $count += 1;
    }
}


sub recipe_box_contains
{
    my ($recipe_box, $recipe_zlid) = @_;

    my $result = 0;

    #
    # Print the zlid, publisher_recipe_id and title of each recipe in the box.
    #
    my $recipes = $recipe_box->{recipes};
    foreach ( @$recipes )
    {
        if ( $_->{zlid} eq $recipe_zlid )
        {
            $result = 1;
            last;
        }
    }

    return $result;
}


sub assert_recipe_box_contains
{
    my $message = shift;
    my $result  = recipe_box_contains(@_);
    if ( $result == 0 )
    {
        die("$message - failed. Recipe box DOES NOT CONTAIN $_[1]\n");
    }
    else
    {
        printf("%-58s - passed. Recipe box contains %s\n", $message, $_[1]);
    }

    return $result;
}


sub assert_recipe_box_does_not_contain
{
    my $message = shift;
    my $result  = recipe_box_contains(@_);
    if ( $result == 1 )
    {
        die("$message - failed. Recipe box CONTAINS $_[1]\n");
    }
    else
    {
        printf("%-58s - passed. Recipe box does not contain %s\n",
               $message, $_[1]);
    }

    return $result;
}


# ---
# Test the retrieval of a users recipe box.
#
# Returns the hash result, fails on assert if it does not work.
#
sub test_get_recipe_box
{
    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    $con->{use_ssl} = $test_use_ssl;
    $con->{host}    = $test_host;
    $con->{port}    = $test_port;

    my $result = $con->get("/api/recipes/index", { brief => "true" } );

    assert_success("Test HTTP GET  - test_get_recipe_box",
                     $con,
                     $result );

    $result;
}


sub test_create_recipe
{
    #
    # Create a new connection without user credentials.
    #
    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret);

    $con->{use_ssl} = $test_use_ssl;
    $con->{host}    = $test_host;
    $con->{port}    = $test_port;

    my @ingredients  = ( "shrimp", "grits", "cheese" );
    my @instructions = ( "Cook", "Eat" );
    my @user_tags    = ("favorite","easy dinner","delicous");

    my $new_recipe  = {  "title" => "Rods Awesome Shrimp & Grits",
                         "description" => "Shrimp & Grits",
                         "cook_time" => "30 min",
                         "text_ingredients" => \@ingredients,
                         "private" => 0,
                         "instructions" => \@instructions,
                         "user_tags" => \@user_tags
                        };

    my $request = {recipe=>$new_recipe};

    my $params = { as_publisher=>"true"};
    #
    # REST is cool, you could do this with either a post or a put.
    # Either will work, but post is probably more efficient. I'll use
    # a put as a test of my put method.
    #
    # $result = $con->post('/api/recipes/create', $params, $request);
    my $result = $con->put('/api/recipes/create', $params, $request);

    assert_success("Test HTTP PUT  - test_create_recipe:", $con, $result);

    my $recipe = $result->{'recipe'};
    print "  New recipe created - $recipe->{'zlid'} - $recipe->{'title'}\n";

    return $recipe->{'zlid'};
}


sub test_add_recipe_to_box
{
    my $zlid = shift;

    #
    # Create a new connection with user credentials.
    #
    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    $con->{use_ssl} = $test_use_ssl;
    $con->{host} = $test_host;
    $con->{port} = $test_port;


    my $request = { zlid=>$zlid,
                    brief=>'true'
                  };

    my $result = $con->post('/api/recipes/add_to_box',
                            undef,
                            $request);

    assert_success("Test HTTP POST - test_add_recipe_to_box", $con, $result);

    return $result;
}


sub test_remove_recipe_from_box
{
    my $zlid = shift;

    #
    # Create a new connection with user credentials.
    #
    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    $con->{use_ssl} = $test_use_ssl;
    $con->{host}    = $test_host;
    $con->{port}    = $test_port;

    my $path   = "/api".$zlid;
    my $result = $con->delete($path);

    assert_success("Test HTTP DEL  - test_remove_recipe_from_box",
                   $con, $result);

    return $result;
}


sub test_delete_recipe
{
    my $zlid = shift;

    #
    # Create a new connection without user credentials.
    #
    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );


    $con->{use_ssl} = $test_use_ssl;
    $con->{host}    = $test_host;
    $con->{port}    = $test_port;

    my $request = { zlid=>$zlid,
                    brief=>'true',
                    as_publisher=>'true'
                  };

    my $path   = "/api/recipes/delete";
    my $result = $con->post($path, undef, $request);

    assert_success("Test HTTP POST - test_delete_recipe, as publisher",
                   $con, $result);

    return $result;
}


sub test_get_partner_signed_url
{
    #
    # Create a new connection with user credentials.
    #
    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    $con->{host}    = $test_host;
    $con->{port}    = 80;

    my $result = $con->get_partner_signed_url("/recipes/box");

    print "signed url = $result\n";

    return $result;
}


my $recipe_box;
my $recipe_zlid;

print("Develop ZipList - \n");
print(" host   - $test_host \n");
print(" port   - $test_port \n");
print(" ssl    - $test_use_ssl \n");
print(" key    - $partner_key \n");
print(" secret - $partner_secret \n");
print(" user   - $partner_user \n");
print("\n");


my $con = new ZipList::SpiceConnection( $partner_key,
                                        $partner_secret );

$con->{http_timeout_seconds} = 180;
$con->{use_ssl} = $test_use_ssl;
$con->{host}    = $test_host;
$con->{port}    = $test_port;

my $recipe_url = "http://www.seriouseats.com/recipes/2011/08/dinner-tonight-lamb-burgers-with-red-onion-relish-recipe.html";

my $args = { brief => "true", source_url => $recipe_url };

my $result = $con->get("/api/recipes/clip", $args);

assert_success("Test HTTP GET  - test_recipe_clip",
               $con,
               $result );

print $con->{last_response_string};
print "\n";

print "Clipped - $result->{recipe}->{zlid} - $result->{recipe}->{title}\n";

my $zlid = $result->{recipe}->{zlid};


my $request =
{
  zlid => $zlid,
  brief => "true",
  public_view => "false"
};

$con->{partner_username} = $partner_user;

$result = $con->get("/api/recipes/add_to_box", $request);

assert_success("Test HTTP GET  - test_add_to_box",
               $con,
               $result );

print $con->{last_response_string};
print "\n";


$request =
{
  sources => ("$zlid")
};

$result = $con->get("/api/lists/add_to_list", $request);


assert_success("Test HTTP GET  - test_add_to_list",
               $con,
               $result );

print $con->{last_response_string};
print "\n";


1;

