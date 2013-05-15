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

using System;
using System.Web;
using System.Text;
using System.Collections.Generic;
using CodeTitans.JSon;

namespace ZipListAPI
{
    public class ZLAPIConnectionExample
    {
        static public void Main ()
        {
            string partner_key = "joesfoodblog";
            string secret      = "0123456789abcdeffedcba9876543210";
            string partner_username = "deadbeef";

            string url = "/api/users/current";

            ZipList.API.Connection con = new ZipList.API.Connection();

            con.PartnerKey      = partner_key;
            con.PartnerSecret   = secret;
            con.PartnerUsername = partner_username;

			Console.WriteLine ("get current user: {0} partner_username = {1}", url, partner_username);
            IJSonObject output = con.get(url, null);
			Console.WriteLine ("{0} - {1}", output["success"].ToString(), output["http_status_code"]);
            Console.WriteLine ("current_user zlid - " + output["user"]["zlid"]);
            string default_list = output["user"]["default_list_zlid"].ToString ();
            Console.WriteLine ("     default_list - " + default_list);

            string add_to_list_url = "/api/lists/add_to_list";

            Dictionary<String, Object> body = new Dictionary<String, Object>();

            string[] items = new string[3];
            items[0] = "Milk";
            items[1] = "Bread";
            items[2] = "Eggs";

            body["zlid"] = default_list;
            body["text_list_items"] = items;

			// A little redundant - used to show what we are adding to the list.
			JSonWriter jsw = new JSonWriter();
			jsw.Write(body);
			string input_json = jsw.ToString(); 

			Console.WriteLine ("Add to List - {0} - {1}", add_to_list_url, input_json );

            output = con.post(add_to_list_url, null, body);
			Console.WriteLine ("{0} - {1}", output["success"].ToString(), output["http_status_code"]);

            IJSonObject list_items = output["list"]["list_items"];

			Console.WriteLine("Current User List - {0} - {1} items", output["list"]["name"], list_items.Count);
        
            foreach (var item in list_items.ArrayItems)
            {
                Console.WriteLine("   - " + item["original"]);
            }
        }
    }
}
