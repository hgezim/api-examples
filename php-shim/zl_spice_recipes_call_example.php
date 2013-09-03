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
require("zl_spice_helpers.php");
require("zl_spice_config.php");
//
// Include a test partner_username for testing only.
//
$partner_username = "some-unique-token-identifying-your-user";


function test_discovery_feed()
{
  printf("\nTest Discovery Feed Service ---\n");
  $con = create_connection();
  // Query string
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
  printf("\nTest Echo Service ---\n");
  $con = create_connection();
  // Query string
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
  printf("\nGet User's Recipe Box ---\n");
  $con = create_connection();
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
  printf("\nGet User's Recipe Box ---\n");
  $con = create_connection();
  // Ask for brief versions of the recipes.
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
  printf("\nCreate New Recipe ---\n");
  $con = create_connection();
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
  printf("\nAdd Recipe '%s' to User Box---\n", $zlid);
  $con = create_connection();
  // If you aren't interested in the data, specify the brief flag and keep the
  // work and data transferred to a minimum.
  $input = array();
  $input['brief'] = true;
  $input['zlid'] = $zlid;
  $result = $con->post('/api/recipes/add_to_box', null, $input);

  assert_success($con, $result);

  printf("\tRecipe '%s' added to user box.\n", $result['recipe']['title']);
}

function test_add_user_tags(array $tags, $zlid)
{
  printf("\nAdd %d tags to '%s'---\n", count($tags), $zlid);

  $con = create_connection();
  $new_recipe_tags = array($tags);
  $request = array();
  $request['zlid'] = $zlid;
  $request['user_tags'] = $tags;
  $result = $con->post('/api/recipes/add_user_tags', null, $request);

  assert_success($con, $result);

  foreach($result['recipe']['user_tags'] as $tag) {
    printf("\t'%s' tag added\n", $tag);
  }

  // printf("\tAdded '%s' tags to '%s'.\n", $result['recipe']['user_tags'], $result['recipe']['title']);
}

function test_show_recipe($zlid)
{
  $con = create_connection();
  $path = '/api'.$zlid.'/show';
  $result = $con->get($path, null, null);
  $recipe = $result['recipe'];
  assert_success($con, $result);

  printf("\nHere is '%s'\n", $recipe['title'] );
  $con->output_formatted_json($result, 2);
}

function test_remove_recipe_from_box($zlid)
{
  printf("\nRemove Recipe '%s' from User Box---\n", $zlid);
  $con = create_connection();
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
  printf("\nDelete Recipe '%s' As Publisher ---\n", $zlid);

  $con = create_connection();
  $input = array();
  $input['brief'] = true;
  $input['zlid'] = $zlid;
  $input['as_publisher'] = true;

  $result = $con->post('/api/recipes/delete', null, $input);

  assert_success($con, $result);

  printf("\tRecipe '%s' deleted.\n", $zlid);
}


function test_search_box( $brief )
{
  printf("\nSearch For Recipes ---\n");
  $con = create_connection();
  //
  // Ask for brief versions of the recipes.
  //
  $query['q'] =     "Shrimp";
  $query['brief'] = $brief;
  $query['per_page']   = 10;
  $query['page']       = 1;

  //
  // A /recipes/index with user credentials (partner_username) will get us
  // the users recipe box.
  //
  $result = $con->get('/api/recipes/search_box', $query);

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

// Basic tests
test_echo_service();
test_discovery_feed();

// Test creating a recipe.  This example is done as a publisher.
 $new_recipe_zlid = test_create_recipe();

// Test adding a recipe to your recipe box, then tagging it, showing it.
// Tagging only works if it is added to the user box.
test_add_recipe_to_box($new_recipe_zlid);
test_add_user_tags(array('barbecue', 'crockpot'), $new_recipe_zlid);
test_show_recipe($new_recipe_zlid);

// Test removing a recipe. This is done as a user.
test_remove_recipe_from_box($new_recipe_zlid);
test_get_recipe_box();

// Test deleting a recipe from the database. This is done as a publisher.
test_delete_recipe($new_recipe_zlid);
test_get_recipe_box();

//
// WIP
//
// Test searching for a recipe in a user's box.
// test_search_box(true);
// test_recipe_get_public_view();
?>
