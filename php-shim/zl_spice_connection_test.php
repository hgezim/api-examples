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


require("zl_spice_connection.php");

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

// Don't forget to specify port 443 for most SSL transactions.
// $test_port = 443;

// ---
// Quick and easy way to check spice call results for testing...
//
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


function test_discovery_feed()
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nTest Discovery Feed Service ---\n");
  //
  // Create a new connection object, with user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  //
  // Query string
  //
  $query = null;

  printf("echo_text = %s\n", $query['text']);

  //
  // A /recipes/index with user credentials (partner_username) will get us
  // the users recipe box.
  //
  $result = $con->get('/api/discovery', $query);

  assert_success($con, $result);

  // Dump the whole message.
  $con->output_formatted_json($result, 2);

  $filters = array();
  $filter1 = array();

  $filter1['publisher'] = "/partners/1f64a280-78e5-012e-24b3-12313b088211";
  // $filter1 = array(  'publisher'=>"Delish" );
  $query['filters'] = $filter1;
  $query['feed_name'] = 'popular_recipes';
  $result = $con->post('/api/discovery/feed', nil, $query);

  // Dump the whole message.
  $con->output_formatted_json($result, 2);
}


// ---
// Test Echo service...
//

function test_echo_service()
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nTest Echo Service ---\n");
  //
  // Create a new connection object, with user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  //
  // Query string
  //
  $query['text'] = '123';

  printf("echo_text = %s\n", $query['text']);

  //
  // A /recipes/index with user credentials (partner_username) will get us
  // the users recipe box.
  //
  $result = $con->get('/api/echo', $query);

  assert_success($con, $result);

  printf("echoed_text = %s\n", $result['echoed_text']);

  // Dump the whole message.
  $con->output_formatted_json($result, 2);
}


// ---
// Test the retrieval of a users recipe box.
//
function test_get_recipe_box()
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nGet User's Recipe Box ---\n");
  //
  // Create a new connection object, with user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  //
  // Ask for brief versions of the recipes.
  //
  $query['brief'] = true;
  $query['dont_create_partner_users'] = true;

  //
  // A /recipes/index with user credentials (partner_username) will get us
  // the users recipe box.
  //
  $result = $con->get('/api/recipes/index', $query);

$con->output_formatted_json($result);
  assert_success($con, $result);

  //
  // Print the zlid, publisher_recipe_id and title of each recipe in the box.
  //
  foreach ($result['recipes'] as $key => $value)
  {
    printf( "\tRecipe[%d] %s - %s - %s\n",
            $key, $value['zlid'],
            $value['publisher_recipe_id'], $value['title']);
  }
}


// ---
// Test the retrieval of a users recipe box.
//
function test_recipe_get_public_view()
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nGet User's Recipe Box ---\n");
  //
  // Create a new connection object, with user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     "" );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  //
  // Ask for brief versions of the recipes.
  //
  $query['brief'] = true;

  //
  // A /recipes/index with user credentials (partner_username) will get us
  // the users recipe box.
  //
  $result = $con->get('/api/recipes', $query);

  assert_success($con, $result);

}


// ---
// Test the creation of new recipe.
//
// This recipe is created as a publisher.
//
// This function returns the zlid for the new recipe.
// This can be used in other operations.
//
function test_create_recipe()
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nCreate New Recipe ---\n");
  //
  // Create a new connection object, without user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     null );

  //
  // Now we are acting as a publisher. We are going to create a brand
  // new recipe in the system.
  //

  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  $ingredients = array( "shrimp", "grits", "cheese" );

  $new_recipe = array(  'title'=>"Rod's Awesome Shrimp & Grits",
                        'description'=>"Shrimp & Grits",
                        'cook_time'=>"30 min",
                        'text_ingredients'=>$ingredients,
                        'private'=>false,
                        'instructions'=>array("Cook","Eat")
                     );

  $request = array('recipe'=>$new_recipe);

  $query = array('as_publisher'=>true);

  //
  // REST is cool, you could do this with either a post or a put.
  // Either will work, but post is probably more efficient. I'll use
  // a put as a test of my put method.
  //
  $result = $con->post('/api/recipes/create', $query, $request);

  assert_success($con, $result);

  $recipe = $result['recipe'];
  printf( "\tNew Recipe %s - %s - %s\n",
            $recipe['zlid'],
            $recipe['publisher_recipe_id'], $recipe['title']);

  return $recipe['zlid'];
}


function test_add_recipe_to_box($zlid)
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nAdd Recipe '%s' to User Box---\n", $zlid);
  //
  // Create a new connection object, without user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  // Tip: If you will get the recipe resource back in the results of this call.
  // If you aren't interested in the data, specify the brief flag and keep the
  // work and data transferred to a minimum.
  $input = array();
  $input['brief'] = true;
  $input['zlid'] = $zlid;
  $result = $con->post('/api/recipes/add_to_box', null, $input);

  assert_success($con, $result);

  printf("\tRecipe '%s' added to user box.\n", $result['recipe']['title']);
}


function test_remove_recipe_from_box($zlid)
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nRemove Recipe '%s' from User Box---\n", $zlid);
  //
  // Create a new connection object, without user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  //
  // You can remove the recipe from the users recipe box via
  // the remove_from_box service entry point. Shown below.
  //
  // $input = array();
  // $input['brief'] = true;
  // $input['zlid'] = $zlid;
  // $result = $con->post('/api/recipes/remove_from_box', null, $input);

  //
  // Or you can do it with a simple DELETE operation REST style with
  // the ID in the url. Like so... I'm including the delete here to
  // test my delete operation.
  //
  $path = '/api'.$zlid;
  $result = $con->del($path, null, null);

  assert_success($con, $result);

  printf("\tRecipe '%s' removed from user box.\n", $zlid);
}


function test_delete_recipe($zlid)
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nDelete Recipe '%s' As Publisher ---\n", $zlid);
  //
  // Create a new connection object, without user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     null );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  $input = array();
  $input['brief'] = true;
  $input['zlid'] = $zlid;
  $input['as_publisher'] = true;

  $result = $con->post('/api/recipes/delete', null, $input);

  assert_success($con, $result);

  printf("\tRecipe '%s' deleted.\n", $zlid);
}


function test_search_recipes( $brief )
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nSearch For Recipes ---\n");
  //
  // Create a new connection object, with user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  //
  // Ask for brief versions of the recipes.
  //
  $query['q'] =     "shrimp";
  $query['brief'] = $brief;
  $query['per_page']   = 10;
  $query['page']       = 1;

  //
  // A /recipes/index with user credentials (partner_username) will get us
  // the users recipe box.
  //
  $result = $con->get('/api/recipes/search', $query);

  assert_success($con, $result);


  //
  // Print the zlid, publisher_recipe_id and title of each recipe in the box.
  //
  foreach ($result['recipes'] as $key => $value)
  {
    printf( "\tRecipe[%d] %s - %s - %s\n",
            $key, $value['zlid'],
            $value['publisher_recipe_id'], $value['title']);
  }
}

function test_add_item_to_list(array $new_list_items)
{
  global $test_host, $test_port, $partner_key, $secret_key, $partner_username;

  printf("\nAdd %s items to Shopping List---\n", count($new_list_items));
  //
  // Create a new connection object, without user credentials.
  //
  $con = new ZiplistSpiceConnection( $partner_key,
                                     $secret_key,
                                     $partner_username );
  //
  // Redirect it to our test / integration host.
  //
  $con->use_ssl = false;
  $con->host = $test_host;
  $con->port = $test_port;

  $input = array();
  $input['brief'] = true;
  $input['items'] = $items;

  $request = array('text_list_items'=>$new_list_items);

  $result = $con->post('/api/lists/add_to_list', null, $request);

  assert_success($con, $result);

  foreach($new_list_items as $new_list_item) {
    printf("\t'%s' was added to user shopping list.\n", $new_list_item);
  }
}


test_feed();
test_echo_service();
test_get_recipe_box();
$new_recipe_zlid = test_create_recipe();
test_add_recipe_to_box($new_recipe_zlid);
test_get_recipe_box();
test_remove_recipe_from_box($new_recipe_zlid);
test_get_recipe_box();
test_delete_recipe($new_recipe_zlid);
test_search_recipes(true);
test_add_item_to_list(array('eggs','milk','bread'));

?>
