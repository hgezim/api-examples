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


=head1 NAME

ZipList::SpiceConnection - A Ziplist Spice Framework Shim.

=head1 SYNOPSIS

    #
    # Retrieve a users recipe box.
    #
    use ZipList::SpiceConnection;

    my $partner_key    = "foo-partner";
    my $partner_secret = "big long secret from ziplist";

    my $partner_user   = "unique partner specific user identifier";

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    $con->{use_ssl} = 1;
    $con->{port}    = 443;

    my $result = $con->get("/api/recipes/index", { brief => "true } );

=head2 DESCRIPTION

ZipList::SpiceConnection - This is a shim or wrapper class for the ZipList
Spice Framework. It basically handles the HMAC authentication, as
well as martialing data for you.

General inputs anre outputs are hashes.  The over the wire protocol is JSON.
Translation is farily simple.

see  http://www.ziplist.com/developers

=head1 LICENSE

- Proprietary -

Copyright 2012 ZipList, Inc. All Rights Reserved.

=head1 AUTHOR

ZipList, Inc.

=cut

package ZipList::SpiceConnection;

use strict;
use warnings;

our $VERSION = "1.00";

use Digest::HMAC_SHA1;
use URI::Escape;
use WWW::Curl::Easy;
use JSON;


#=============================================================================
# Public Interface
#=============================================================================

=head3 new

Construct a new SpiceConnection object.

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

The $partner_user param is optional. It is required for user specific calls.
It is not needed for partner specific calls, such as recipe publishing, etc.

See Spice Framework documentation for additional details.

Internally, the new connection object will default to our standard public
server. 'api.ziplist.com' and port 80, or standard HTTP traffic. However,
all of this can be over ridden for integration and development purposes.

All of the internal state of this connection can be accessed by the user.

=item C< $con->{partner_key} >

Read or set the partner_key

=item C< $con->{partner_secret} >

Read or set the partner_secret_key

=item C< $con->{partner_username} >

Read or set the partner username

=item C< $con->{default_timestamp_window_seconds} >

Read or set the default timestamp window in seconds.  All Spice operations
use an HMAC hash validation mechanism that includes a timestamp. This time
stamp is an Epoch time, UTC. It defines a point in the future when the
hash and thus the operation will no longer be valid.  The default window
for this is 5 minutes.

=item C< $con->{default_signed_url_timestamp_window_seconds} >

Read or set the default url timestamp window in seconds. This window is
for use on signed urls. Refer to Spice Framework documentation for additional
information. A signed URL operates on the same general principal and they also
will expire. This setting sets the window of time for signed URLs. The current
default is 15 minutes.

=item C< $con->{port} >

Read or set the default port used when making spice connections, or
generating signed URLs.

=item C< $con->{host} >

Read or set the default host used when making spice connections, or
generating signed URLs.

=item C< $con->{http_timeout_seconds} >

Read or set the default HTTP timeout used when making spice connections.

=item C< $con->{use_ssl} >

Read or set whether or not to use SSL when making spice connections, or
generating signed URLs. 0/1

These attributes contain the state of the last HTTP operation. These include
the last error number, the error message based on the error number, the HTTP
response code, the string data returned from the HTTP operation, and the hash
object parsed from the string, if applicable. Obviously for a 404, there will
be no resulting hash.  For a host not found there will be no http code or
response string.

These are convenience methods, as a general rule, the HTTP methods defined
on this object will return a hash parsed from the resulting JSON response,
or an empty hash if an error occurs. You can then query the object to
determine the issue.

    $con->{last_error_number}
    $con->{last_error_message}
    $con->{last_http_code}
    $con->{last_response_string}
    $con->{last_response_object}

=cut
sub new
{
    #
    # Set up the internal hash, and bless it.
    #
    my $type = shift;
    my $class = ref $type || $type;
    my $self = {
        partner_key      => "",
        partner_secret   => "",
        partner_username => "",
        default_timestamp_window_seconds => 60 * 5,
        default_signed_url_timestamp_window_seconds => 60 * 15,
        port => 80,
        host => "api.ziplist.com",
        http_timeout_seconds => 60,
        use_ssl => 0,
        last_error_message => "",
        last_error_number => 0,
        last_http_code => 0,
        last_response_string => "",
        last_response_object => undef
    };
    bless $self;

    #
    # Consume the parameters setting their values.
    #
    if (defined $_[0])
    {
        $self->{partner_key} = shift;
    }
    if (defined $_[0])
    {
        $self->{partner_secret} = shift;
    }
    if (defined $_[0])
    {
        $self->{partner_username} = shift;
    }

    #
    # Return the hash.
    #
    return $self;
}


=head3 describe

Mostly for debug. Prints out the internal state of the object.

    $con->describe;
=cut
sub describe
{
    my $self = shift;
    $self->_dump_hash($self);
}


=head3 get

Used for an authenticated HTTP get operation to the ZipList Spice Framework.

It takes two parameters:

- The first is the URI path for the get operation.  The host and port
are internalized in the connection object, and default to the ZipList
production system. (required)

- The second is a hash, containing any URL parameters, see below.
This example retrieves the recipe box of a user. (optional)

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    my $result = $con->get("/api/recipes/index", { brief => "true" } );

=cut
sub get
{
    my ( $self, $path, $query_params) = @_;

    #
    # Reset the error status.
    #
    $self->_clear_error();

    #
    # Add any query parameters listed to the URL path
    #
    $path = $self->_create_query_string($path, $query_params);

    #
    # No data payload on a get.
    #
    my $data = "";

    #
    # Get a timestamp with a n second of window of validity.
    #
    my $timestamp = time + $self->{default_timestamp_window_seconds};

    #
    # Use our timestamp, and relevant partner information
    # to generate our secure HMAC hash for this operation.
    #
    my $hmac = $self->_generate_hmac( $self->{partner_username},
                                      $timestamp,
                                      $self->{partner_key},
                                      $self->{partner_secret},
                                      $path,
                                      $data );


    my @headers = (
        'Content-Type: application/json',
        'Accept: application/json',
        'X_ZL_VALIDATION: '.$hmac,
        'X_ZL_EXPIRES_AT: '.$timestamp,
        'X_ZL_PARTNER_KEY: '.$self->{partner_key},
        'X-ZL-API-VERSION: '."20110104" );


    if ( length($self->{partner_username}) > 0 )
    {
        push @headers, 'X_ZL_PARTNER_USERNAME: '.$self->{partner_username};
    }

    my $curl_path = "http://";
    if ( $self->{use_ssl} )
    {
        $curl_path = "https://";
    }
    $curl_path .= $self->{host} . $path;

    my $response_body = "";
    my $curl = WWW::Curl::Easy->new();

    $curl->setopt(CURLOPT_URL, $curl_path);
    $curl->setopt(CURLOPT_HTTPGET, 1);
    $curl->setopt(CURLOPT_HTTPHEADER, \@headers);
    $curl->setopt(CURLOPT_TIMEOUT, $self->{http_timeout_seconds});
    $curl->setopt(CURLOPT_FOLLOWLOCATION, 1);
    $curl->setopt(CURLOPT_PORT, $self->{port});

    $curl->setopt(CURLOPT_WRITEDATA,\$response_body);

    if ( $self->{use_ssl} )
    {
        $curl->setopt(CURLOPT_SSL_VERIFYHOST, 1);
        $curl->setopt(CURLOPT_SSL_VERIFYPEER, 2);
    }

    my $value   = undef;

    my $retcode = $curl->perform();

    # $response_body = substr($response_body, 12);

    # Save the ret code from curl.
    $self->{last_error_number} = $retcode;

    #
    # Let's have a look then and deal with the result...
    #
    if ( $retcode != 0 )
    {
        # Get that error message and hang onto it.
        $self->{last_error_message} = $curl->errbuf;
        $value = { };
    }
    else
    {
        #
        # The call succeeded. So we should have an
        # http response of some sort, and some json.
        #
        $self->{last_http_code}    = $curl->getinfo(CURLINFO_HTTP_CODE);

        $self->{last_response_string} = $response_body;
        $self->{last_response_object} = undef;

        #
        # If we have some data, it should be json,
        # attempt to interpret it, and catch any exception.
        #
        if ( length($response_body) )
        {
            eval
            {
                $value = decode_json($response_body);
                1;
            }
            or do
            {
                # We barfed parsing the json. That's bad news.
                # Set the error number to -1.
                $self->{last_error_number} = -1;

                # Set the error message to the exception.
                $self->{last_error_message} = $@;

                # Set the object to be an empty hash.
                $value = { };
            }
        }
        else
        {
            $value = { };
        }
    }
    $self->{last_response_object} = $value;

    $curl->cleanup();

    $value;
}


=head3 post

Used for an authenticated HTTP post operation to the ZipList Spice Framework.

It takes three parameters:

- The first is the URI path for the post operation.  The host and port are
internalized in the connection object, and default to the ZipList production
system. (required)

- The second is a hash, containing any URL parameters, see below.
This example retrieves the recipe box of a user. (optional)

- The third is a hash, containing the post data. This is converted to json.
(required)

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    my $request = { zlid=>$zlid,
                    brief=>'true'
                  };

    my $result = $con->post('/api/recipes/add_to_box', undef, $request);

=cut
sub post
{
    my ( $self, $path, $query_params, $data_hash) = @_;

    #
    # Reset the error status.
    #
    $self->_clear_error();

    #
    # Add any query parameters listed to the URL path
    #
    $path = $self->_create_query_string($path, $query_params);

    #
    # Get a timestamp with a n second of window of validity.
    #
    my $timestamp = time + $self->{default_timestamp_window_seconds};

    #
    # Encode the hash into json.
    #
    my $data = encode_json($data_hash);

    #
    # Use our timestamp, and relevant partner information
    # to generate our secure HMAC hash for this operation.
    #
    my $hmac = $self->_generate_hmac( $self->{partner_username},
                                      $timestamp,
                                      $self->{partner_key},
                                      $self->{partner_secret},
                                      $path,
                                      $data );


    my @headers = (
        'Content-length: '.length($data),
        'Content-Type: application/json',
        'Accept: application/json',
        'X_ZL_VALIDATION: '.$hmac,
        'X_ZL_EXPIRES_AT: '.$timestamp,
        'X_ZL_PARTNER_KEY: '.$self->{partner_key},
        'X-ZL-API-VERSION: '."20110104" );


    if ( length($self->{partner_username}) )
    {
        push @headers, 'X_ZL_PARTNER_USERNAME: '.$self->{partner_username};
    }

    my $curl_path = "http://";
    if ( $self->{use_ssl} )
    {
        $curl_path = "https://";
    }
    $curl_path .= $self->{host} . $path;

    my $response_body = "";
    my $curl = WWW::Curl::Easy->new();

    $curl->setopt(CURLOPT_URL, $curl_path);
    $curl->setopt(CURLOPT_POST, 1);
    $curl->setopt(CURLOPT_POSTFIELDS, $data);
    $curl->setopt(CURLOPT_HTTPHEADER, \@headers);
    $curl->setopt(CURLOPT_TIMEOUT, $self->{http_timeout_seconds});
    $curl->setopt(CURLOPT_FOLLOWLOCATION, 1);
    $curl->setopt(CURLOPT_PORT, $self->{port});

    $curl->setopt(CURLOPT_WRITEDATA,\$response_body);

    if ( $self->{use_ssl} )
    {
        $curl->setopt(CURLOPT_SSL_VERIFYHOST, 1);
        $curl->setopt(CURLOPT_SSL_VERIFYPEER, 2);
    }

    my $value   = undef;

    my $retcode = $curl->perform();

    # Save the ret code from curl.
    $self->{last_error_number}    = $retcode;

    #
    # Let's have a look then and deal with the result...
    #
    if ( $retcode != 0 )
    {
        # Get that error message and hang onto it.
        $self->{last_error_message}    = $curl->errbuf;
        $value = { };
    }
    else
    {
        #
        # The call succeeded. So we should have an
        # http response of some sort, and some json.
        #
        $self->{last_http_code}    = $curl->getinfo(CURLINFO_HTTP_CODE);

        $self->{last_response_string} = $response_body;
        $self->{last_response_object} = undef;

        #
        # If we have some data, it should be json,
        # attempt to interpret it, and catch any exception.
        #
        if ( length($response_body) )
        {
            eval
            {
                $value = decode_json($response_body);
                1;
            }
            or do
            {
                # We barfed parsing the json. That's bad news.
                # Set the error number to -1.
                $self->{last_error_number} = -1;

                # Set the error message to the exception.
                $self->{last_error_message} = $@;

                # Set the object to be an empty hash.
                $value = { };
            }
        }
        else
        {
            $value = { };
        }
    }
    $self->{last_response_object} = $value;

    $curl->cleanup();

    $value;
}


=head3 put

Used for an authenticated HTTP PUT operation to the ZipList Spice Framework.
In general, this method is provided for completeness. You should be able to accomplish most things with a GET, POST or DELETE.

It takes three parameters:

- The first is the URI path for the put operation.  The host and port are
internalized in the connection object, and default to the ZipList production
system. (required)

- The second is a hash, containing any URL parameters, see below.
This example retrieves the recipe box of a user. (optional)

- The third is a hash, containing the post data. This is converted to json.
(required)

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret);

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
    # Either will work, but post is probably more efficient. We will
    # use a put.
    #
    my $result = $con->put('/api/recipes/create', $params, $request);

    #
    # But this would do the same thing.
    #
    my $result = $con->post('/api/recipes/create', $params, $request);

=cut
sub put
{
    my ( $self, $path, $query_params, $data_hash) = @_;

    #
    # Reset the error status.
    #
    $self->_clear_error();

    #
    # Add any query parameters listed to the URL path
    #
    $path = $self->_create_query_string($path, $query_params);

    #
    # Get a timestamp with a n second of window of validity.
    #
    my $timestamp = time + $self->{default_timestamp_window_seconds};

    #
    # Encode the hash into json.
    #
    my $data = encode_json($data_hash);

    #
    # Use our timestamp, and relevant partner information
    # to generate our secure HMAC hash for this operation.
    #
    my $hmac = $self->_generate_hmac( $self->{partner_username},
                                      $timestamp,
                                      $self->{partner_key},
                                      $self->{partner_secret},
                                      $path,
                                      $data );


    my @headers = (
        'Content-length: '.length($data),
        'Content-Type: application/json',
        'Accept: application/json',
        'X_ZL_VALIDATION: '.$hmac,
        'X_ZL_EXPIRES_AT: '.$timestamp,
        'X_ZL_PARTNER_KEY: '.$self->{partner_key},
        'X-ZL-API-VERSION: '."20110104" );


    if ( length($self->{partner_username}) )
    {
        push @headers, 'X_ZL_PARTNER_USERNAME: '.$self->{partner_username};
    }

    my $curl_path = "http://";
    if ( $self->{use_ssl} )
    {
        $curl_path = "https://";
    }
    $curl_path .= $self->{host} . $path;

    my $response_body = "";
    my $curl = WWW::Curl::Easy->new();


    my $read_buffer = _get_read_buffer($data);

    $curl->setopt(CURLOPT_URL, $curl_path);
    $curl->setopt(CURLOPT_PUT, 1);
    $curl->setopt(CURLOPT_READFUNCTION, \&_read_callback);
    $curl->setopt(CURLOPT_READDATA, \$read_buffer );
    $curl->setopt(CURLOPT_INFILESIZE, length ($data) );
    $curl->setopt(CURLOPT_HTTPHEADER, \@headers);
    $curl->setopt(CURLOPT_TIMEOUT, $self->{http_timeout_seconds});
    $curl->setopt(CURLOPT_FOLLOWLOCATION, 1);
    $curl->setopt(CURLOPT_PORT, $self->{port});

    $curl->setopt(CURLOPT_WRITEDATA,\$response_body);

    if ( $self->{use_ssl} )
    {
        $curl->setopt(CURLOPT_SSL_VERIFYHOST, 1);
        $curl->setopt(CURLOPT_SSL_VERIFYPEER, 2);
    }

    my $value   = undef;

    my $retcode = $curl->perform();

    # Save the ret code from curl.
    $self->{last_error_number}    = $retcode;

    #
    # Let's have a look then and deal with the result...
    #
    if ( $retcode != 0 )
    {
        # Get that error message and hang onto it.
        $self->{last_error_message}    = $curl->errbuf;
        $value = { };
    }
    else
    {
        #
        # The call succeeded. So we should have an
        # http response of some sort, and some json.
        #
        $self->{last_http_code}    = $curl->getinfo(CURLINFO_HTTP_CODE);

        $self->{last_response_string} = $response_body;
        $self->{last_response_object} = undef;

        #
        # If we have some data, it should be json,
        # attempt to interpret it, and catch any exception.
        #
        if ( length($response_body) )
        {
            eval
            {
                $value = decode_json($response_body);
                1;
            }
            or do
            {
                # We barfed parsing the json. That's bad news.
                # Set the error number to -1.
                $self->{last_error_number} = -1;

                # Set the error message to the exception.
                $self->{last_error_message} = $@;

                # Set the object to be an empty hash.
                $value = { };
            }
        }
        else
        {
            $value = { };
        }
    }
    $self->{last_response_object} = $value;

    $curl->cleanup();

    $value;
}


=head3 delete

Used for an authenticated HTTP DELETE operation to the ZipList Spice Framework.

It takes two parameters:

- The first is the URI path for the delete operation.  The host and port are
internalized in the connection object, and default to the ZipList production
system. (required)

- The second is a hash, containing any URL parameters, see below.
This example retrieves the recipe box of a user. (optional)

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    my $zlid   = "/recipes/2a5a1f50-afd8-012c-3221-1231feedbeef";
    my $path   = "/api".$zlid;
    my $result = $con->delete($path, undef);

=cut
sub delete
{
    my ( $self, $path, $query_params ) = @_;

    #
    # Reset the error status.
    #
    $self->_clear_error();

    #
    # Add any query parameters listed to the URL path
    #
    $path = $self->_create_query_string($path, $query_params);

    #
    # Get a timestamp with a n second of window of validity.
    #
    my $timestamp = time + $self->{default_timestamp_window_seconds};

    #
    # Encode the hash into json.
    #
    my $data = "";

    #
    # Use our timestamp, and relevant partner information
    # to generate our secure HMAC hash for this operation.
    #
    my $hmac = $self->_generate_hmac( $self->{partner_username},
                                      $timestamp,
                                      $self->{partner_key},
                                      $self->{partner_secret},
                                      $path,
                                      $data );


    my @headers = (
        'Content-Type: application/json',
        'Accept: application/json',
        'X_ZL_VALIDATION: '.$hmac,
        'X_ZL_EXPIRES_AT: '.$timestamp,
        'X_ZL_PARTNER_KEY: '.$self->{partner_key},
        'X-ZL-API-VERSION: '."20110104" );


    if ( length($self->{partner_username}) )
    {
        push @headers, 'X_ZL_PARTNER_USERNAME: '.$self->{partner_username};
    }

    my $curl_path = "http://";
    if ( $self->{use_ssl} )
    {
        $curl_path = "https://";
    }
    $curl_path .= $self->{host} . $path;

    my $response_body = "";
    my $curl = WWW::Curl::Easy->new();


    my $read_buffer = _get_read_buffer($data);

    $curl->setopt(CURLOPT_URL, $curl_path);
    $curl->setopt(CURLOPT_CUSTOMREQUEST, "DELETE");
    $curl->setopt(CURLOPT_HTTPHEADER, \@headers);
    $curl->setopt(CURLOPT_TIMEOUT, $self->{http_timeout_seconds});
    $curl->setopt(CURLOPT_FOLLOWLOCATION, 1);
    $curl->setopt(CURLOPT_PORT, $self->{port});

    $curl->setopt(CURLOPT_WRITEDATA,\$response_body);

    if ( $self->{use_ssl} )
    {
        $curl->setopt(CURLOPT_SSL_VERIFYHOST, 1);
        $curl->setopt(CURLOPT_SSL_VERIFYPEER, 2);
    }

    my $value   = undef;

    my $retcode = $curl->perform();

    # Save the ret code from curl.
    $self->{last_error_number}    = $retcode;

    #
    # Let's have a look then and deal with the result...
    #
    if ( $retcode != 0 )
    {
        # Get that error message and hang onto it.
        $self->{last_error_message}    = $curl->errbuf;
        $value = { };
    }
    else
    {
        #
        # The call succeeded. So we should have an
        # http response of some sort, and some json.
        #
        $self->{last_http_code}    = $curl->getinfo(CURLINFO_HTTP_CODE);

        $self->{last_response_string} = $response_body;
        $self->{last_response_object} = undef;

        #
        # If we have some data, it should be json,
        # attempt to interpret it, and catch any exception.
        #
        if ( length($response_body) )
        {
            eval
            {
                $value = decode_json($response_body);
                1;
            }
            or do
            {
                # We barfed parsing the json. That's bad news.
                # Set the error number to -1.
                $self->{last_error_number} = -1;

                # Set the error message to the exception.
                $self->{last_error_message} = $@;

                # Set the object to be an empty hash.
                $value = { };
            }
        }
        else
        {
            $value = { };
        }
    }
    $self->{last_response_object} = $value;

    $curl->cleanup();

    $value;
}


=head3 get_partner_signed_url

This method is used to generate a partner signed URL or partner authenticated
URL.

It takes one parameters:

- A URI path to be signed. Note, this may include URL parameters.
This string should be a partial URL or PATH. The protocol, host and port
are all internalized in the connection object, and default to the ZipList
production system. (required)

    my $con = new ZipList::SpiceConnection( $partner_key,
                                            $partner_secret,
                                            $partner_user );

    my $result = $con->get_partner_signed_url("/recipes/box");

    print "signed url = $result\n";

# This is an example of a signed URL with key data items [REPLACED].
$ http://api.ziplist.com/recipes/box?zlc_partner_username=[PARTNER_USERNAME]&zlc_expires_at=1328626954&zlc_partner_key=[PARTNER_KEY]&zlc_validation=[HMAC_HASH]

see  http://www.ziplist.com/developers/signed_url_tool

=cut
sub get_partner_signed_url
{
    my ( $self, $path ) = @_;

    #
    # Reset the error status.
    #
    $self->_clear_error();

    #
    # Get a timestamp with a n second of window of validity.
    # This value uses a different default than the standard call.
    #
    my $expires_timestamp =
        time + $self->{default_signed_url_timestamp_window_seconds};

    #
    # Setup to add some new query params.
    #
    my $query_params =
    {
        zlc_partner_key => $self->{partner_key},
        zlc_expires_at  => $expires_timestamp
    };

    #
    # Add the partne_username if it's been set for this connection.
    #
    if ( length($self->{partner_username}) )
    {
        $query_params->{zlc_partner_username} = $self->{partner_username};
    }

    #
    # Add any our new query parameters listed to the URL path
    #
    $path = $self->_create_query_string($path, $query_params);


    #
    # Use our timestamp, and relevant partner information
    # to generate our secure HMAC hash for this operation.
    #
    my $hmac = $self->_generate_hmac( $self->{partner_username},
                                      $expires_timestamp,
                                      $self->{partner_key},
                                      $self->{partner_secret},
                                      $path,
                                      undef );

    #
    # Add  an hmac parameter to the URL at the end.
    #
    $path = $self->_create_query_string($path, { zlc_validation => "$hmac" });

    #
    # Now build the full URL, including the protocol, host and
    # port if required.
    #
    my $port = $self->{port};
    my $full_url;
    my $protocol_default_port;
    if ( $self->{use_ssl} )
    {
        $full_url = "https://";
        $protocol_default_port = 443;
    }
    else
    {
        $full_url = "http://";
        $protocol_default_port = 80;
    }

    if ( $port == $protocol_default_port )
    {
        $full_url .= $self->{host} . $path;
    }
    else
    {
        $full_url .= $self->{host} . ":" . $port . $path;
    }

    return $full_url;
}


#-----------------------------------------------------------------------------
# Private Interface
#
# These are utility methods. And should not be used outside of this package.
#-----------------------------------------------------------------------------

# ---
# _create_query_string  (private utility method)
#
# Private function to build a query string form a basic hash.
# Be careful with this one. It takes a hash, but it certainly
# will not work for a deep or complex structure. Basic key
# value pairs of scalar values, please.
#
# Create URL query string from the parameters given:
#
#   uri  - string containing the URI
#   query_hash - hash containing params for the URI
#
# This method is resilient, and will accept a URI that
# already has some parameters on it, and will respond
# accordingly.
#
# Returns a string containing the encoded URI plus query data.
#
sub _create_query_string
{
    my ($self, $uri, $query_hash) = @_;
    my $output = $uri;

    #
    # Now build up the query string onto the URI
    #

    # If the query_hash is not defined, OR
    # query_hash is empty, ok no params were specified
    if ( (! $query_hash) || (keys(%{$query_hash}) == 0) )
    {
    }
    # otherwise process the query params as normal,
    # not forgetting to URI escape everything.
    else
    {
        #
        # If the url doesn't have a '?' then add one
        # this makes us purely addative. You can call it more than once.
        #
        if ( index($output, "?") == -1 )
        {
            $output .= "?";
        }
        else
        {
            $output .= "&";
        }

        while ( my ($key, $value) = each %{$query_hash} )
        {
            $output .= uri_escape($key);
            $output .= "=";
            $output .= uri_escape($value);
            $output .= "&";
        }
        $output = substr($output, 0, -1);
    }

    $output;
}


# ---
# _generate_hmac  (private utility method)
#
# Private function to create an SHA1 based HMAC hash for authentication.
#
# Generate an SHA1 HMAC from the following parameters:
#
#   partner_username - string identifying partner_user (optional)
#   timestamp        - epoch time UTC some time in the future
#   partner_key      - string partner key identifying partner
#   partner_secret   - string partner secret key
#   uri              - string uri including any url params
#   data             - string json of input data or hash (optional)
#
# This method is resilient, and will accept a URI that
# already has some parameters on it, and will respond
# accordingly.
#
# Returns a string containing the BASE64 encoded HMAC.
#
sub _generate_hmac
{
    my ( $self, $partner_username,
         $timestamp, $partner_key,
         $partner_secret, $uri, $data ) = @_;

    #
    # Build our secure HMAC
    #  timestamp
    #  partner_key
    #  partner_username (if applicable)
    #  uri_path (this is the path, sans scheme, host and port.
    #           (example:  /api/recipes/search ) this includes
    #            any query parameters.
    #
    # Concatenate all that together...
    #
    my $hmac_body = "";
    $hmac_body .=  "$timestamp";
    $hmac_body .=  $partner_key;
    $hmac_body .=  $partner_username;
    $hmac_body .=  $uri;
    $hmac_body .=  "$data" if $data;

    # Generate an SHA1 hash with this value, seeded with the
    # partners secret_key. Get the base64 version.
    my $sha = Digest::HMAC_SHA1->new($partner_secret);

    $sha->add($hmac_body);

    my $b64_hmac_digest = $sha->b64digest;

    #
    # Be careful! By Convention, CPAN Digest modules do not pad their
    # BASE64 Output to lengths that are multiples of 4. This can cause
    # other packages problems. So we do it here.
    #
    while (length($b64_hmac_digest) % 4) {
        $b64_hmac_digest .= '=';
    }

    return $b64_hmac_digest;
}


# ---
# _clear_error  (private utility method)
#
# Private function to clear the error state or previous state on the
# connection object.
#
# Clears:
#
#   last_error_number
#   last_error_message
#   last_http_code
#   last_response_string
#   last_response_object
#
sub _clear_error()
{
    my $self = shift;
    $self->{last_error_number} = 0;
    $self->{last_error_message} = "";
    $self->{last_http_code} = 0;
    $self->{last_response_string} = "";
    $self->{last_response_object} = undef;

    1;
}


# ---
# _dump_hash  (private utility method)
#
# Private function to dump a simple PERL Hash. Nothing complex, scalars only.
# Simple debug left over, but useful enough to keep around.
#
# This is a method on a connection object. It will dump the state of the
# connection object, OR if defined a hash passed as a parameter.
#
sub _dump_hash
{
    my $self = shift;
    my $hash = {};

    if ( defined $_[0] )
    {
        $hash = shift;
    }

    while ( my ($key, $value) = each %{$hash} )
    {
        if ( $value )
        {
            print " $key => $value\n";
        }
        else
        {
            print " $key => undef\n";
        }
    }

    1;
}


# ---
# _get_read_buffer  (private utility method)
#
# This is a helper method used for an HTTP PUT using WWW::Curl::easy.
#
# This is used in conjunction with the _read_callback below.
#
# see _read_callback(), put();
#
sub _get_read_buffer
{
    my ( $buffer ) = @_;

    my $read_buffer = {
            buffer => $buffer,
            length => length($buffer),
            offset => 0 };

    return $read_buffer;
}


# ---
# _read_callback  (private utility method)
#
# This is a helper method used for an HTTP PUT using WWW::Curl::easy.
#
# This is used in conjunction with the _get_read_buffer above.
#
# see _get_read_buffer(), put();
#
sub _read_callback
{
    my ( $maxlength, $pointer ) = @_;

    my $length = $$pointer->{length};
    my $offset = $$pointer->{offset};
    my $buffer = $$pointer->{buffer};

    my $length_to_send = 0;
    my $remaining_data = $length - $offset;
    if ( $maxlength >= $remaining_data )
    {
        $length_to_send = $remaining_data;
    }
    else
    {
        $length_to_send = $maxlength;
    }

    my $data = substr($buffer, $offset, $length_to_send);

    $offset = $offset + $length_to_send;
    $$pointer->{offset} = $offset;

    return $data;
}


1;
