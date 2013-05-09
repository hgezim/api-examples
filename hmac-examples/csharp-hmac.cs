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
using System.Text;
using System.Security.Cryptography;

public class GenerateHmac
{

  static public string generateHmac( string expires_at,
                                     string path,
                                     string partner_key,
                                     string partner_secret,
                                     string partner_username,
                                     string body = null )
  {
    // Create authentication hash
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


  static public void Main ()
  {
    string partner_key = "mypartnerkey";
    string secret      = "mypartnersecret";

    // 5 minutes from now
    string expires_at  = (Convert.ToInt32((DateTime.UtcNow - new DateTime(1970, 1, 1)).TotalSeconds) + 300).ToString();

    string path = "/api/echo?text=helloworld&zlc_expires_at={0}&zlc_partner_key={1}";

    string uri = String.Format( path,
                                HttUtility.UrlEncode(expires_at),
                                HttpUtility.UrlEncode(partner_key) );

    string hmac = GenerateHmac.generateHmac(expires_at, uri, partner_key, secret, "", "");

    Console.WriteLine (uri + "&zlc_validation=" + HttpUtility.UrlEncode(hmac));

  }

}

