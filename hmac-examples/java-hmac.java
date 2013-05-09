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

import java.io.*;
import java.net.*;
import java.security.*;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

// Just a class to hang the function in.
class HmacExample
{
    // Our hmac function
    public static String hmac( String timestamp,
                               String partner_key,
                               String secret_key,
                               String partner_username,
                               String path,
                               String data )

        throws InvalidKeyException,
               NoSuchAlgorithmException,
               UnsupportedEncodingException
    {
        //
        // Pretty simple, concatenate the the relevant data parts together.
        //
        StringBuilder sb = new StringBuilder(1024);
        sb.append(timestamp);
        sb.append(partner_key);
        sb.append(partner_username);
        sb.append(path);
        sb.append(data);

        //
        // Get an hmac_sha1 key from the raw key bytes
        //
        SecretKeySpec signingKey =
            new SecretKeySpec(secret_key.getBytes(), "HmacSHA1");

        //
        // get an hmac_sha1 Mac instance and initialize with the signing key
        //
        Mac mac = Mac.getInstance("HmacSHA1");
        mac.init(signingKey);

        //
        // compute the hmac on input data bytes, not, we turn our
        // buffer into a string, and pull the UTF8 bytes from the string
        // for purposes of generating the HMAC.
        //
        byte[] rawHmac = mac.doFinal(sb.toString().getBytes("UTF8"));

        //
        // BASE64 encode the HMAC
        //
        return new sun.misc.BASE64Encoder().encode(rawHmac);
    }


    //
    // A simple entry point to call our function, with canned data.
    //
    public static void main(String[] args)
    {
        String timestamp =
            new Long((System.currentTimeMillis()/1000)+60).toString();

        String partner_key      = "joesfoodblog";
        String secret_key       = "0123456789abcdeffedcba9876543210";
        String partner_username = "";
        String text             = "message";
        String path = "/api/echo?text=helloworld&zlc_expires_at=%s&zlc_partner_key=%s";

        try
        {
            String full_path =
                    String.format( path,
                                   URLEncoder.encode(timestamp, "UTF-8"),
                                   URLEncoder.encode(partner_key, "UTF-8") );

            String hmac = HmacExample.hmac( timestamp,
                                            partner_key,
                                            secret_key,
                                            partner_username,
                                            full_path,
                                            "" );

            String signed_url = full_path + "&zlc_validation=" +
                                          URLEncoder.encode(hmac, "UTF-8") ;

            System.out.println("input:  " + path);
            System.out.println("hmac:   " + hmac);
            System.out.println("output: " + signed_url);
        }
        catch (Exception e)
        {
            System.out.println("Exception: " + e.getMessage());
        }
    }
};

