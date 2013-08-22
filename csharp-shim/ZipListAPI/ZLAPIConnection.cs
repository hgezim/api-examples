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

// Build it on a mac with Mono SDK, and runtime. Mileage may vary.
// $ gmcs -reference:System.Web ZLAPIService.cs 
// $ mono csharp-hmac.exe 

using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Configuration;
using System.Net;
using System.IO;
using System.Security.Cryptography;
using System.Globalization;
using System.Web;

// We are using CodeTitans.JSon here. It works well, and allows
// you to go from JSon to Dictionary, or their functional equivalent,
// and back. You could easily swap out your preferred Json implementation
// or use XML.
//
// NOTE: I wouldn't recommend baking defined objects into your API 
// implementation, we do make addative changes now and then.
//
using CodeTitans.JSon;


namespace ZipList.API
{
    // A ZipList API Connection class
    // For doing some basic stuff.
    public class Connection
    {
        //---
        // Standard constructor, it works but you still need to set
        // the keys, etc. before you can do anything useful.
        //
        public Connection( ) { }

        //---
        // Constructor
        //
        // To set most important params, credentials and 
        // partner_username.
        //
        public Connection( string partner_key, 
                        string partner_secret,
                        string partner_username )
        {
            PartnerKey      = partner_key;
            PartnerSecret   = partner_secret;
            PartnerUsername = partner_username;
        }

        //---
        // ZipList API Host -
        //  - api.zltest.info for staging and test
        //  - api.ziplist.com for production 
        //
        private string mAPIHost = "api.zltest.info";
        public string APIHost
        {
            get { return mAPIHost; }

            set { mAPIHost = value; } 
        }

        private string mPartnerKey = "";
        public string PartnerKey 
        {
            get { return mPartnerKey; }

            set { mPartnerKey = value; } 
        }


        private string mPartnerSecret = "";
        public string PartnerSecret 
        {
            get { return mPartnerSecret; }

            set { mPartnerSecret = value; }
        }


        private string mPartnerUsername = "";
        public string PartnerUsername 
        {
            get { return mPartnerUsername; }

            set { mPartnerUsername = value; }
        }
        

        static private string generateHmac( string expires_at,
                                            string path,
                                            string partner_key,
                                            string partner_secret,
                                            string partner_username,
                                            string body = null )
        {
            //
            // Create authentication hash
            //
            string requestString = expires_at +
                                   partner_key +
                                   partner_username +
                                   path +
                                   body ;
        
            UTF8Encoding enc = new UTF8Encoding();
            var bPartnerSecret = enc.GetBytes(partner_secret);
            var hmac = new HMACSHA1(bPartnerSecret);
            var bRequestString = enc.GetBytes(requestString);
        
            var hash = hmac.ComputeHash(bRequestString);
            var hash64 = Convert.ToBase64String(hash).Trim();
        
            return hash64;
        }

        //---
        // Turn a simple dictionary into query parameters.  This works
        // well for flat stuff. But if you are going to use arrays,
        // other structure, etc.  Then all bets are off, and you need to
        // reexamine this.
        //
        private string ObjToQueryString(Dictionary<String, String> input)
        {
            if (input == null) 
                return "";

            var qsBuilder = new StringBuilder();
            foreach (var key in input.Keys)
            {
                if (input[key] != null)
                {
                    if (qsBuilder.Length > 0) qsBuilder.Append("&");
                    qsBuilder.Append( HttpUtility.UrlEncode(key) );
                    qsBuilder.Append( "=" ); 
                    qsBuilder.Append( HttpUtility.UrlEncode(input[key]) );
                }
            }

            if (qsBuilder.Length > 0)
                qsBuilder.Insert(0, '?');

            return qsBuilder.ToString();
        }


        public IJSonObject get( string service_path, 
                                Dictionary<String, String> query_params)
        {
            return call (service_path, "GET", query_params, null);
        }


        public IJSonObject put( string service_path, 
                                Dictionary<String, String> query_params, 
                                Dictionary<String, Object> body)
        {
            return call (service_path, "PUT", query_params, body);
        }


        public IJSonObject post( string service_path, 
                                 Dictionary<String, String> query_params, 
                                 Dictionary<String, Object> body )
        {
            return call (service_path, "POST", query_params, body);
        }


        public IJSonObject delete( string service_path, 
                                   Dictionary<String, String> query_params )
        {
            return call (service_path, "DELETE", query_params, null);
        }


        public IJSonObject call( string service_path, 
                                 string methodName, 
                                 Dictionary<String, String> query_params, 
                                 Dictionary<String, Object> body = null)
        {
              IJSonObject output = null;

            //
            // Get input string
            //
            string input_json = "";

            //
            // Create target Url
            //
            string url = service_path;

            url = url.ToLower();

            //
            // Add the any query parameters. 
            //
            url += ObjToQueryString(query_params);


            //
            // Convert the payload to a json string, if provided.
            // And needed.
            //
            if ( methodName == "PUT" || methodName == "POST" )
            {

                if (body != null) 
                {
                    JSonWriter jsw = new JSonWriter();
                    jsw.Write(body);
                    input_json = jsw.ToString(); 
                }
                else
                {
                    input_json = "";
                }
            }
            else
            {
                input_json = "";
            }
            

            var expires = (Convert.ToInt32((DateTime.UtcNow - new DateTime(1970, 1, 1)).TotalSeconds) + 300).ToString();   // 5 min from now

            //
            // Create authentication hash
            //  
            string hash64 = generateHmac( expires,
                                          url,
                                          PartnerKey,
                                          PartnerSecret,
                                          PartnerUsername,
                                          input_json );

            string path = "http://" + APIHost + url; 

            HttpWebRequest req = (HttpWebRequest)HttpWebRequest.Create(path);

            req.Headers.Add("X-ZL-Partner-Key", PartnerKey);
            req.Headers.Add("X-ZL-Expires-At",  expires); 
            req.Headers.Add("X-ZL-Partner-Username", PartnerUsername);
            req.Headers.Add("X-ZL-Validation", hash64);  
            req.ContentType = "application/json; charset=utf-8";
            req.Timeout     = 30000;
            req.Method      = methodName;


            if (req.Method == "POST" || req.Method == "PUT" )
            {
                if (input_json.Length > 0)
                {
                    Encoding encoding = new UTF8Encoding(false);
                    req.ContentLength = Encoding.UTF8.GetByteCount(input_json);
                    var sw = new StreamWriter(req.GetRequestStream(), encoding);
                    sw.Write(input_json);
                    sw.Close();
                }
            }


            HttpWebResponse resp = null;

            try
            {
                resp = req.GetResponse() as HttpWebResponse;
            }
            catch (WebException e)
            {
                throw e;
            }

            string json = "";

            if (resp.StatusCode == HttpStatusCode.OK)
            {
                using (Stream respStream = resp.GetResponseStream())
                {
                    StreamReader reader = new StreamReader( respStream, 
                                                            Encoding.UTF8);
                    json = reader.ReadToEnd();

                    JSonReader jr = new JSonReader();
                    output = jr.ReadAsJSonObject(json);
                }
            }

            return output;
        }
    }
}
