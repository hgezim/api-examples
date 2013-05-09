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

use Digest::HMAC_SHA1;
use URI::Escape;


#
# A convenience function for turning a hash into a query
# string, for attaching to a URL.
#
sub create_query_string
{
    my ($uri, $query_hash) = @_;
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
sub generate_hmac
{
    my ( $timestamp,
         $partner_key,
         $partner_secret,
         $partner_username,
         $uri,
         $data ) = @_;

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

# Get an expires at time, and set it for 5 minutes in the future.
my $expires_at = time + (5*60);

# Set up your partner_key and your secret key
my $partner_key = "joesfoodblog";
my $secret_key  = "0123456789abcdeffedcba9876543210";

# This is your parnter_username, we leave it blank as the echo example
# does not require a user.
my $partner_username = "";

# We are going to use the echo api.
my $uri = "/api/echo";

# No payload or post body.
my $data = "";

my $query_params = { text => "Hello",
                     zlc_expires_at=> $expires_at,
                     zlc_partner_key=> $partner_key };

my $path = create_query_string($uri, $query_params);

print "This is the URL we will sign we will access: " . $path . "\n";

my $hmac = generate_hmac( $expires_at, $partner_key, $secret_key, $partner_username, $path, $data);

print "This is the calculated HMAC, Base64 encoded:  " . $hmac . "\n";

print "This is the URL encoded version of the same:  " . uri_escape($hmac) . "\n";


# Here we will add a protocol and host, as well as the HMAC validation
# parameter.  This to create a URL that can be used in a browser.
#
my $signed_url = "http://api.zltest.info" . $path . "&zlc_validation=" . uri_escape($hmac);

print "Here is the signed URL:  " . $signed_url . "\n";


