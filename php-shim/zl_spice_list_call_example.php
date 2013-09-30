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
// Included a test partner_username for testing only.
//
$partner_username = "some-unique-token-identifying-your-user";

function test_create_list($list_name)
{
  printf("\nCreate new '%s' List---\n", $list_name);

  $con = create_connection();
  $request['list']['name'] = $list_name;
  $result = $con->post('/api/lists/create', null, $request);

  assert_success($con, $result);

  printf("\t'%s' list was created.\n", $list_name);
  // Dump the whole message.
  // $con->output_formatted_json($result, 2);
  return $result['list']['zlid'];
}

function test_delete_list($zlid)
{
  printf("\nDelete List -- '%s'\n", $zlid);

  $con = create_connection();
  $path = '/api'.$zlid;
  $result = $con->del($path, null, null);

  assert_success($con, $result);

  printf("\t'%s'  -- list was deleted.\n", $zlid);
  // Dump the whole message.
  // $con->output_formatted_json($result, 2);
}

function test_add_items_to_list(array $new_list_items, $list)
{
  printf("\nAdd %s items to '%s' list---\n", count($new_list_items), $list);

  $con = create_connection();
  $request = array();
  $request['zlid'] = $zlid;
  $request['text_list_items'] = $new_list_items;
  $result = $con->post('/api/lists/add_to_list', null, $request);

  assert_success($con, $result);
  $added_items = array();
  foreach($result['updated_items'] as $key => $value) {
    printf("\n%s was added to your list -- %s\n", $value['original'], $value['zlid']);
    array_push($added_items, $value['zlid']);
  }

  // $con->output_formatted_json($result, 2);
  return $added_items;
}

function test_remove_items_from_list(array $list_items_to_remove)
{
  printf("\nRemove %d items from current list.\n", count($list_items_to_remove));

  $con = create_connection();
  $request = array();
  $request['remove_items'] = $list_items_to_remove;

  $result = $con->post('/api/lists/remove_from_list', null, $request);

  assert_success($con, $result);

  printf("\t%d items were removed from your shopping list.\n", count($list_items_to_remove));
}

function test_show_list($zlid)
{
  printf("\nShow %s List---\n", $zlid);

  $con = create_connection();
  $path = '/api'.$zlid;

  $request = array();
  $request['brief'] = true;

  $result = $con->get($path, $request, null);

  assert_success($con, $result);

  $list = $result['list'];
  foreach ($list['list_items'] as $key => $value)
  {
    printf( "\t[%s] -> %s - zlid: %s\n", $key, $value['original'], $value['zlid']);
  }
}

function test_index_lists()
{
  $con = create_connection();
  $path = '/api/lists';
  $result = $con->get($path, null, null);

  assert_success($con, $result);

  $user_lists = $result['lists'];
  printf( "\nCurrent user has %d list(s).\n", count($user_lists));
  foreach ($user_lists as $key => $value)
  {
    printf( "'%s' was created on %s and currently has %d items on it.\n%s\n", $value['name'], $value['created_at'], $value['item_count'], $value['zlid']);
  }
}

function test_get_current_list()
{
  $con = create_connection();
  $path = '/api/lists/default';

  // We just want the list zlid here so we'll put that in our request by explicitly
  // asking for a brief response without list items.
  $request = array();
  $request['brief'] = true;
  $request['exclude_list_items'] = true;
  $result = $con->get($path, $request, null);

  assert_success($con, $result);
  // Dump our brief message.
  // $con->output_formatted_json($result, 2);

  $current_list_zlid = $result['list']['zlid'];
  printf( "\nThe zlid of the current list is: '%s'\n", $current_list_zlid );
  return $current_list_zlid;
}

// Create a new list.
test_create_list('Snacks');

// Show all of the current user's lists.
test_index_lists();

// Get the zlid of the current list.
$current_list = test_get_current_list();

// Add some items to the list.
$list_items = test_add_items_to_list(array('carrots', 'chips'), $current_list);

// Show the list with items.
test_show_list($current_list);

// Delete items from the current (default) list.  This function accepts zlids.
test_remove_items_from_list($list_items, $current_list);

// Show the empty list.
test_show_list($current_list);

// Delete the current list.
test_delete_list($current_list);
?>
