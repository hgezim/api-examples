<?php
//
// Copyright (c) 2008-2013 ZipList, Inc.
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE ZIPLIST SOFTWARE IS PROVIDED BY ZIPLIST, INC ON AN "AS IS" BASIS.
// ZIPLIST MAKES NO WARRANTIES, EXPRESS OR IMPLIED, INCLUDING WITHOUT
// LIMITATION THE IMPLIED WARRANTIES OF NON-INFRINGEMENT, MERCHANTABILITY
// AND FITNESS FOR A PARTICULAR PURPOSE, REGARDING THE ZIPLIST SOFTWARE
// OR ITS USE AND OPERATION ALONE OR IN COMBINATION WITH YOUR PRODUCTS.
//
// IN NO EVENT SHALL ZIPLIST BE LIABLE FOR ANY SPECIAL, INDIRECT, INCIDENTAL
// OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
// SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
// INTERRUPTION) ARISING IN ANY WAY OUT OF THE USE, REPRODUCTION,
// MODIFICATION AND/OR DISTRIBUTION OF THE ZIPLIST SOFTWARE, HOWEVER CAUSED
// AND WHETHER UNDER THEORY OF CONTRACT, TORT (INCLUDING NEGLIGENCE),
// STRICT LIABILITY OR OTHERWISE, EVEN IF ZIPLIST HAS BEEN ADVISED OF THE
// POSSIBILITY OF SUCH DAMAGE.
//
// ACCESS TO THIS SOURCE CODE DOES NOT GRANT NOR DOES IT IMPLY A LICENSE
// AGREEMENT WITH ZIPLIST, TO USE ITS APIS.
//

function generate_hmac( $timestamp,
                        $partner_key,
                        $secret_key,
                        $partner_username,
                        $uri,
                        $data )
{
    //
    // Build our secure HMAC
    //  timestamp
    //  partner_key
    //  partner_username (if applicable)
    //  uri_path (this is the path, sans scheme, host and port.
    //           (example:  /api/recipes/search ) this includes
    //            any query parameters.
    //
    // Concatenate all that together...
    //
    $hmac_body = "";
    $hmac_body .=  $timestamp;
    $hmac_body .=  $partner_key;
    $hmac_body .=  $partner_username;
    $hmac_body .=  $uri;
    $hmac_body .=  $data;

    //
    // Generate an SHA1 hash with this value, seeded with the
    // partners secret_key.
    //
    $raw_hmac = hash_hmac  ( "sha1",
                              $hmac_body,
                              $secret_key,
                              true );

    //
    // Then base64_encode the hash code.
    //
    $hmac = base64_encode($raw_hmac);

    // Return the value here.
    return $hmac;
}

//
// Get a timestamp with a 5 minute of window of validity.
// This is what you would normally call, but let's set the
// timestamp to something known that can be replicated reliably.
//
// $expires_at       = time() + 60 * 5;

// Set the expires at time for a LONG way off.
// 2030-05-01 00:00:00 UTC
$expires_at       = 1903824000;

$partner_key      = "my_partner_key";
$partner_secret   = "0123456789ABCDEFFEDCBA9876543210";
$parther_username = "";

// We are calling the echo service -
$path = "/api/echo";

// We will use the folling parameters
$query_hash = array( 'text'=>'message',
                     'zlc_expires_at'=>$expires_at,
                     'zlc_partner_key'=>$partner_key,
                   );

$path_with_query =  $path;

// Process the query parameters and add them to the path.
// A bit of extra code here, but it makes sure everything is properly
// URL encoded.
if ( empty($query_hash) == false )
{
  $path_with_query .= "?";

  //
  // Build up the query string from the associative array
  // above. This works fine for simple strings.  Boolean and
  // complex types must be handled differently.
  //
  foreach($query_hash as $key=>$value)
  {
    $path_with_query .= (string) urlencode((string)$key);
    $path_with_query .= "=";
    $path_with_query .= (string) urlencode((string)$value);
    $path_with_query .= "&";
  }
  //
  // snag that last '&' off the tail of the string.
  //
  $path_with_query = substr($path_with_query,0,-1);
}

printf("\n");
printf("Here is the input URL, the generated HMAC and finally the signed url:\n\n");
printf(" Full Path: %s\n", $path_with_query);

// Call our hmac generator...
$hmac_value = generate_hmac( $expires_at,
                             $partner_key,
                             $secret_key,
                             "",
                             $path_with_query,
                             null );

// Output the Base64 encoded version here...
printf("HMAC Value: %s\n", $hmac_value);

// Now add it with our original URL, making sure to urlencode the string.
$signed_url = $path_with_query;

$signed_url .= "&";
$signed_url .= urlencode("zlc_validation");
$signed_url .= "=";
$signed_url .= urlencode($hmac_value);

printf("Signed URL: %s\n", $signed_url);
?>
