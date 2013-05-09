#
# Copyright (c) 2008-2013 ZipList, Inc.
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
#
# THE ZIPLIST SOFTWARE IS PROVIDED BY ZIPLIST, INC ON AN "AS IS" BASIS.
# ZIPLIST MAKES NO WARRANTIES, EXPRESS OR IMPLIED, INCLUDING WITHOUT
# LIMITATION THE IMPLIED WARRANTIES OF NON-INFRINGEMENT, MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE, REGARDING THE ZIPLIST SOFTWARE
# OR ITS USE AND OPERATION ALONE OR IN COMBINATION WITH YOUR PRODUCTS.
#
# IN NO EVENT SHALL ZIPLIST BE LIABLE FOR ANY SPECIAL, INDIRECT, INCIDENTAL
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) ARISING IN ANY WAY OUT OF THE USE, REPRODUCTION,
# MODIFICATION AND/OR DISTRIBUTION OF THE ZIPLIST SOFTWARE, HOWEVER CAUSED
# AND WHETHER UNDER THEORY OF CONTRACT, TORT (INCLUDING NEGLIGENCE),
# STRICT LIABILITY OR OTHERWISE, EVEN IF ZIPLIST HAS BEEN ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.
#
# ACCESS TO THIS SOURCE CODE DOES NOT GRANT NOR DOES IT IMPLY A LICENSE
# AGREEMENT WITH ZIPLIST, TO USE ITS APIS.
#

require 'uri'
require 'cgi'
require 'openssl'
require 'base64'

#
# Convenience method for adding in your query params.
#
# create url with query params
#
def create_query_string (uri, query_params)
  output_str = uri

  if (query_params != nil) && (query_params.size > 0)
    output_str += "?"
    query_params.each do |key, val|
        output_str += "\#{key}=\#{CGI::escape(val.to_s)}"
        output_str += "&"
    end
    output_str = output_str[0...-1]
  end
  output_str
end

#
# Here is your hmac generator
#
def calc_hmac( expires_at,
               partner_key,
               secret_key,
               partner_username,
               url,
               body = "" )
  #
  # initialize hmac algorithm
  #
  digest = OpenSSL::Digest::Digest.new('sha1')
  hmac   = OpenSSL::HMAC.new(secret_key, digest)

  #
  # parse the URI and reconstruct (without protocol, host, port, etc.)
  #
  uri  = URI.parse(url)
  path = (uri.path.nil? or uri.path.empty?) ? '/' : uri.path
  if uri.query
    path << '?' << uri.query
  end

  #
  # Accumulate the data for the HMAC generation.
  # And feed it into the hmac generator.
  #
  str = [ expires_at.to_s,
          partner_key,
          partner_username,
          path].compact.join


  hmac << str
  body.strip.each_byte {|c|
    hmac << c.chr
  }

  #
  # Base64 encode and return the result.
  #
  res = Base64.encode64(hmac.digest).strip
  res
end



#
# Your partner credentials
#
partner_key = "joesfoodblog"
secret_key  = "0123456789abcdeffedcba9876543210"

#
# partner_username of the current user, leave it blank when not work
# on behalf of a user.
#
partner_username = ""

#
# Get an UTC expires_at for 5 minutes in the future.
#
expires_at   = (Time.now.utc + (5 * 60)).to_i

#
# Here is our echo service query parameters.
#
query_params = { :text => "Hello World",
                 :zlc_expires_at=> expires_at,
                 :zlc_partner_key=> partner_key }

# Here is our full URL path, the signer will parse
# and generate the hmac appropriately.
path    = "http://zltest.info/api/echo"

#
# Create our full path with query strings.
# We can use a full path here, because our ruby
# HMAC generator will parse it, and remove the protocol,
# host, port, etc.  Before generating the HMAC signature.
#
path_with_params = create_query_string(path, query_params)

puts "Here is our full path with parameters:"
puts path_with_params

#
# Generate the hmac value.
#
hmac = calc_hmac( expires_at, partner_key, secret_key,
                  partner_username, path_with_params )

puts "Here is the Base64 encoded HMAC Value: \#{hmac}"
puts "Here is the URL encoded HMAC Value: \#{CGI::escape(hmac)}"

#
# Add the hmac param to the URL...
#
signed_path = path_with_params + "&zlc_validation=" + CGI::escape(hmac)

puts "Here is your signed request URL for the echo API---"
puts "If you've put in your real credentials you should be"
puts "able to copy and paste this into a browser to see the JSON"
puts "results of the call :"
puts signed_path


#
# Bear in mind, that you could have just as easily included the
# expires at time, partner_key, and validation into HTTP
# parameters for the request. We use a full signed URL here
# for demonstration purposes as it can easily be loaded with
# a browser
#
