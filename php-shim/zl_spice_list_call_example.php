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

function test_add_item_to_list(array $new_list_items)
{
  printf("\nAdd %s items to Shopping List---\n", count($new_list_items));

  $con = create_connection();
  $request = array('text_list_items'=>$new_list_items);
  $result = $con->post('/api/lists/add_to_list', null, $request);

  assert_success($con, $result);

  foreach($new_list_items as $new_list_item) {
    printf("\t'%s' was added to user shopping list.\n", $new_list_item);
  }
  // Dump the whole message.
  $con->output_formatted_json($result, 2);
}

function test_remove_items_from_list(array $list_items_to_remove)
{
  printf("\nRemove %s items from Shopping List---\n", count($list_items_to_remove));

  $con = create_connection();
  $request = array('remove_items'=>$list_items_to_remove);
  $result = $con->post('/api/lists/remove_from_list', null, $request);

  assert_success($con, $result);

  printf("\t%d items were removed from your shopping list.\n", count($list_items_to_remove));
}

function test_show_list($zlid)
{
  printf("\nShow %s List---\n", $zlid);

  $con = create_connection();
  $path = '/api'.$zlid;
  $result = $con->get($path, null, null);

  assert_success($con, $result);

  $list = $result['list'];
  foreach ($list['list_items'] as $key => $value)
  {
    printf( "\t[%s] -> [%s] - zlid: %s\n", $key, $value['original'], $value['zlid']);
  }
}

test_add_item_to_list(array('cream'));
//
// Pass zlids to these next functions
//
// test_remove_items_from_list(array('', ''));
// test_show_list('');
?>
