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
//---
// zl_spice_connection.php
//

//
// Namespaces are good, put it back when you have PHP 5.3.
//
// namespace ZipList\Spice\Client;

// ---
//
// ZipListSpiceConnection
//
// This is a simple wrapper class for lib_curl, and serves to handle
// interactions with the ZipList Spice framework. It basically handles the
// HMAC authentication.
//
// General inputs and outputs are associative arrays or hashmaps.  The
// over wire protocol is JSON. Translation is fairly simple.
//
// Don't forget to switch ports if you enable SSL.  443 is the standard port
// number there.
//
// This is a credible first cut, but could be refactored to remove some
// of the duplicate code.
//


//
// Put your partner credentials here.
//
$partner_key = "joesfoodblog";
$secret_key = "0123456789abcdeffedcba9876543210";

//
// Included a test partner_username for testing only.
//
$partner_username = "some-unique-token-identifying-your-user";

$test_host = "api.ziplist.com";
$test_port = 80;

class ZipListSpiceConnection
{

  public $host;
  public $port;
  public $partner_username;
  public $partner_key;
  public $secret_key;

  public $http_timeout_seconds;
  public $timestamp_delta_minutes;
  public $use_ssl;

  public $last_error_msg;
  public $last_error_number;
  public $last_http_code;

  public $last_path;

  public $debug;

  // ---
  // Basic constructor, to set the partner key, secret key
  // and optional partner_username.
  //
  function __construct( $key,
                        $secret,
                        $pusername = null )

  {
    $this->partner_key      = $key;
    $this->secret_key       = $secret;
    $this->partner_username = $pusername;

    $this->port = 80;
    $this->host = "api.ziplist.com";

    $this->http_timeout_seconds = 60;
    $this->use_ssl              = true;
    $this->debug                = false;
  }


  // ---
  // create_query_string
  //
  // Private function to build a query string from a basic hash.
  // Be careful with this one.  It takes a hash, but it probably
  // won't work for a deep or complex structure.  Basic key
  // and simple value pairs, please.
  //
  // returns a string containing the encoded uri plus query data.
  //
  private function create_query_string($uri, $query_hash)
  {
    $output = $uri;
    //
    // If we have a hash that isn't empty.
    // Then iterate through it building up our
    // query string. Be careful to make sure it's
    // all escaped properly.
    //
    if ( empty($query_hash) == false )
    {
      $output .= "?";

      foreach($query_hash as $key=>$value)
      {
        //
        // need to handle bool specially.
        //
        if ( (is_bool($value) == true ) && ($value == true) )
        {
          $output .= (string) urlencode((string)$key);
          $output .= "=";
          $output .= 'true';
        }
        elseif ( (is_bool($value) == true) && ($value == false) )
        {
          $output .= (string) urlencode((string)$key);
          $output .= "=";
          $output .= 'false';
        }
        elseif ( is_array($value) == true )
        {
          $sub_array = $value;
          foreach($sub_array as $subkey=>$subvalue)
          {
            $output .= (string) urlencode((string)$key."[][original]");
            $output .= "=";
            $output .= (string) urlencode((string)$subvalue['original']);

            $output .= "&";
          }
        }
        else
        {
          $output .= (string) urlencode((string)$key);
          $output .= "=";
          $output .= (string) urlencode((string)$value);
        }

        $output .= "&";
      }

      //
      // snag that list & off the tail of the string.
      //
      $output = substr($output,0,-1);
    }
    printf("URL PATH = %s\n", $output);

    return $output;
  }


  // ---
  // generate_hmac
  //
  // Private function to build the authentication HMAC hash digest.
  //
  // returns a string containing the HMAC.
  //
  public function generate_hmac( $partner_username,
                                 $timestamp,
                                 $partner_key,
                                 $secret_key,
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

      // Generate an SHA1 hash with this value, seeded with the
      // partners secret_key.
      $raw_hmac = hash_hmac  ( "sha1",
                                $hmac_body,
                                $secret_key,
                                true );

      //
      // Then base64_encode the hash code.
      //
      $hmac = base64_encode($raw_hmac);

      return $hmac;
  }


  private function clear_error()
  {
    $this->last_error_msg    = "";
    $this->last_error_number = 0;
    $this->last_http_code    = 0;

    $this->last_path = "";
  }


  // ---
  // http 'get'
  //
  public function get($path, $query_params = null)
  {
    //
    // Reset the error status.
    //
    self::clear_error();

    //
    // Add any query parameters listed to the URL path.
    //
    $path = self::create_query_string($path, $query_params);

    //
    // No data payload on a get.
    //
    $data = "";

    //
    // Get a timestamp with a 5 minute of window of validity.
    //
    $timestamp = time() + 60 * 5;

    //
    // Use our timestamp, and relevant partner information
    // to generate our secure HMAC hash for this operation.
    //
    $hmac = self::generate_hmac( $this->partner_username,
                                 $timestamp,
                                 $this->partner_key,
                                 $this->secret_key,
                                 $path,
                                 $data);

    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Accept: application/json';
    $headers[] = 'X_ZL_VALIDATION: '.$hmac;
    $headers[] = 'X_ZL_EXPIRES_AT: '.$timestamp;
    $headers[] = 'X_ZL_PARTNER_KEY: '.$this->partner_key;
    $headers[] = 'X-ZL-API-VERSION: '."20110104";
    if ( isset($this->partner_username) )
    {
      $headers[] = 'X_ZL_PARTNER_USERNAME: '.$this->partner_username;
    }

    $curl_path = "http://";
    if ( $this->use_ssl == true )
    {
      $curl_path = "https://";
    }
    $curl_path .= $this->host . $path;

    $this->last_path = $curl_path;

    if ( $this->debug == true )
    {
      printf( "get - %s\n", $this->last_path);
    }

    //
    // Setup Curl
    //
    $process = curl_init($curl_path);

    curl_setopt($process, CURLOPT_HTTPGET, true);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($process, CURLOPT_TIMEOUT, $this->http_timeout_seconds);
    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($process, CURLOPT_PORT, $this->port);

    if ( $this->use_ssl == true )
    {
      curl_setopt($process, CURLOPT_SSL_VERIFYHOST, true);
      curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 2);
    }

    //
    // Execute the curl request.
    //
    $return = curl_exec($process);

    //
    // Get the error number, error_message (if any) and the
    // http response code, and save them in this object.
    //
    $this->last_error_number = curl_errno($process);
    $this->last_error_msg    = curl_error($process);
    $this->last_http_code    = curl_getinfo($process, CURLINFO_HTTP_CODE );

    $json_return = null;
    if ( $this->last_error_number != 0 )
    {
      $json_return = array();
    }
    else
    {
      $json_return =  json_decode($return, true);
    }

    curl_close($process);

    return $json_return;
  }


  // ---
  // http 'post'
  //
  public function post($path, $query_params, $data_array)
  {
    //
    // Reset the error status.
    //
    self::clear_error();

    //
    // Add any query parameters listed to the URL path.
    //
    $path = self::create_query_string($path, $query_params);

    //
    // Get a timestamp with a 5 minute of window of validity.
    //
    $timestamp = time() + 60 * 5;

    $data = json_encode($data_array);

    //
    // Use our timestamp, and relevant partner information
    // to generate our secure HMAC hash for this operation.
    //
    $hmac = self::generate_hmac( $this->partner_username,
                                 $timestamp,
                                 $this->partner_key,
                                 $this->secret_key,
                                 $path,
                                 $data);


    $headers[] = "Content-length: ".strlen($data);
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Accept: application/json';
    if ( isset($this->partner_username) )
    {
      $headers[] = 'X_ZL_PARTNER_USERNAME: '.$this->partner_username;
    }
    $headers[] = 'X_ZL_VALIDATION: '.$hmac;
    $headers[] = 'X_ZL_EXPIRES_AT: '.$timestamp;
    $headers[] = 'X_ZL_PARTNER_KEY: '.$this->partner_key;
    $headers[] = 'X-ZL-API-VERSION: '."20110104";

    $curl_path = "http://";
    if ( $this->use_ssl == true )
    {
      $curl_path = "https://";
    }
    $curl_path .= $this->host;
    $curl_path .= $path;

    $this->last_path = $curl_path;

    if ( $this->debug == true )
    {
      printf( "post - %s\n", $this->last_path);
      printf( "data - %s\n", $data);
    }

    //
    // Setup Curl
    //
    $process = curl_init($curl_path);

    // Apply the post data to our curl call
    curl_setopt($process, CURLOPT_POST, 1);
    curl_setopt($process, CURLOPT_POSTFIELDS, $data);

    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($process, CURLOPT_TIMEOUT, $this->http_timeout_seconds);
    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($process, CURLOPT_PORT, $this->port);

    if ( $this->use_ssl == true )
    {
      curl_setopt($process, CURLOPT_SSL_VERIFYHOST, true);
      curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 2);
    }

    //
    // Execute the curl request.
    //
    $return = curl_exec($process);

    //
    // Get the error number, error_message (if any) and the
    // http response code, and save them in this object.
    //
    $this->last_error_number = curl_errno($process);
    $this->last_error_msg    = curl_error($process);
    $this->last_http_code    = curl_getinfo($process, CURLINFO_HTTP_CODE );

    $json_return = null;
    if ( $this->last_error_number != 0 )
    {
      $json_return = array();
    }
    else
    {
      $json_return =  json_decode($return, true);
    }

    curl_close($process);

    return $json_return;
  }


  // ---
  // http 'put'
  //
  // Be forewarned, due to a limitation in lib_curl
  // a 'put' action must put from the local file system.
  // This is easy enough to handle, using the tmp_file facility,
  // however, it may not be the most efficient process.
  // Consider using post, or get when appropriate.
  //
  public function put($path, $query_params, $data_array)
  {
    //
    // Reset the error status.
    //
    self::clear_error();

    //
    // Add any query parameters listed to the URL path.
    //
    $path = self::create_query_string($path, $query_params);

    //
    // Get a timestamp with a 5 minute of window of validity.
    //
    $timestamp = time() + 60 * 5;

    $data = json_encode($data_array);

    //
    // Use our timestamp, and relevant partner information
    // to generate our secure HMAC hash for this operation.
    //
    $hmac = self::generate_hmac( $this->partner_username,
                                 $timestamp,
                                 $this->partner_key,
                                 $this->secret_key,
                                 $path,
                                 $data);


    $headers[] = "Content-length: ".strlen($data);
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Accept: application/json';
    if ( isset($this->partner_username) )
    {
      $headers[] = 'X_ZL_PARTNER_USERNAME: '.$this->partner_username;
    }
    $headers[] = 'X_ZL_VALIDATION: '.$hmac;
    $headers[] = 'X_ZL_EXPIRES_AT: '.$timestamp;
    $headers[] = 'X_ZL_PARTNER_KEY: '.$this->partner_key;
    $headers[] = 'X-ZL-API-VERSION: '."20110104";

    $curl_path = "http://";
    if ( $this->use_ssl == true )
    {
      $curl_path = "https://";
    }

    $curl_path .= $this->host;
    $curl_path .= $path;

    $this->last_path = $curl_path;

    if ( $this->debug == true )
    {
      printf( "put  - %s\n", $this->last_path);
      printf( "data - %s\n", $data);
    }

    //
    // Setup Curl
    //
    $process = curl_init($curl_path);

    $put_tmp_file = tmpfile();
    fwrite($put_tmp_file, $data);
    fseek($put_tmp_file, 0);


    //
    // Setup for the put, with a tmp file and size.
    //
    curl_setopt($process, CURLOPT_PUT, true);
    curl_setopt($process, CURLOPT_INFILE, $put_tmp_file);
    curl_setopt($process, CURLOPT_INFILESIZE, strlen($data));

    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($process, CURLOPT_TIMEOUT, $this->http_timeout_seconds);
    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($process, CURLOPT_PORT, $this->port);

    if ( $this->use_ssl == true )
    {
      curl_setopt($process, CURLOPT_SSL_VERIFYHOST, true);
      curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 2);
    }

    //
    // Execute the curl request.
    //
    $return = curl_exec($process);

    //
    // Get the error number, error_message (if any) and the
    // http response code, and save them in this object.
    //
    $this->last_error_number = curl_errno($process);
    $this->last_error_msg    = curl_error($process);
    $this->last_http_code    = curl_getinfo($process, CURLINFO_HTTP_CODE );

    $json_return = null;
    if ( $this->last_error_number != 0 )
    {
      $json_return = array();
    }
    else
    {
      $json_return =  json_decode($return, true);
    }

    //
    // Close the tmp file to ensure that we do not leak.
    //
    fclose($put_tmp_file);

    curl_close($process);

    return $json_return;
  }


  // ---
  // http 'delete'
  //
  public function del($path, $query_params = null)
  {
    //
    // Reset the error status.
    //
    self::clear_error();

    //
    // Add any query parameters listed to the URL path.
    //
    $path = self::create_query_string($path, $query_params);

    //
    // No data payload on a delete.
    //
    $data = "";

    //
    // Get a timestamp with a $timestamp_delta_minutes window of validity.
    // We do not recommend a window of less than five minutes.
    //
    if ( $this->timestamp_delta_minutes < 5 )
    {
      $this->timestamp_delta_minutes = 5;
    }
    $timestamp = time() + 60 * $this->timestamp_delta_minutes;

    //
    // Use our timestamp, and relevant partner information
    // to generate our secure HMAC hash for this operation.
    //
    $hmac = self::generate_hmac( $this->partner_username,
                                 $timestamp,
                                 $this->partner_key,
                                 $this->secret_key,
                                 $path,
                                 $data);

    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Accept: application/json';
    if ( isset($this->partner_username) )
    {
      $headers[] = 'X_ZL_PARTNER_USERNAME: '.$this->partner_username;
    }
    $headers[] = 'X_ZL_VALIDATION: '.$hmac;
    $headers[] = 'X_ZL_EXPIRES_AT: '.$timestamp;
    $headers[] = 'X_ZL_PARTNER_KEY: '.$this->partner_key;
    $headers[] = 'X-ZL-API-VERSION: '."20110104";

    $curl_path = "http://";
    if ( $this->use_ssl == true )
    {
      $curl_path = "https://";
    }
    $curl_path .= $this->host;
    $curl_path .= $path;

    $this->last_path = $curl_path;

    if ( $this->debug == true )
    {
      printf( "del  - %s\n", $this->last_path);
    }

    //
    // Setup Curl
    //
    $process = curl_init($curl_path);

    curl_setopt($process, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($process, CURLOPT_TIMEOUT, $this->http_timeout_seconds);
    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($process, CURLOPT_PORT, $this->port);

    if ( $this->use_ssl == true )
    {
      curl_setopt($process, CURLOPT_SSL_VERIFYHOST, true);
      curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 2);
    }


    //
    // Execute the curl request.
    //
    $return = curl_exec($process);

    //
    // Get the error number, error_message (if any) and the
    // http response code, and save them in this object.
    //
    $this->last_error_number = curl_errno($process);
    $this->last_error_msg    = curl_error($process);
    $this->last_http_code    = curl_getinfo($process, CURLINFO_HTTP_CODE );

    $json_return = null;
    if ( $this->last_error_number != 0 )
    {
      $json_return = array();
    }
    else
    {
      $json_return =  json_decode($return, true);
    }

    curl_close($process);

    return $json_return;
  }


  public function output_formatted_json($array, $indent = 0)
  {
    $indent_text = '';

    for ($ii = 0;$ii < $indent; $ii++)
    {
        $indent_text .= '  ';
    }

    echo "\n".$indent_text."{\n";

    foreach ( $array as $key => $value )
    {
        echo $indent_text."\"".$key."\" : ";
        if (is_array($value))
            self::output_formatted_json( $value, $indent + 1 );
        else echo "\"".$value."\";\n";
    }
    echo $indent_text."}\n";
  }
}

// Set up our connection
function create_connection() {
    global $test_host, $test_port, $partner_key, $secret_key, $partner_username;
    // Create a new connection object, without user credentials.
    $con = new ZiplistSpiceConnection( $partner_key,
                                       $secret_key,
                                       $partner_username );
    // Redirect it to our test / integration host.
    $con->use_ssl = false;
    $con->host = $test_host;
    $con->port = $test_port;
    return $con;
}

// Quick and easy way to check spice call results for testing...
function assert_success( $connection, $result )
{
  if ( $connection->last_error_number != 0 )
  {
    exit("HTTP operation failed: $connection->last_error_msg \n");
  }
  elseif ( ( $connection->last_http_code < 200 ) ||
           ( $connection->last_http_code >= 300  ) )
  {
    var_dump($result);
    exit("Spice call failed: $connection->last_http_code \n");
  }
}

?>
