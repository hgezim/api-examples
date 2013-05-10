api-examples
============

# Ziplist Spice API Code Examples and Software Shims

This repository contains software examples, and sample shims for working with 
the ZipList, Inc. SPICE APIs.  If you are a ZipList partner, or potential 
partner then feel free to have a look around.  If you are just curious, 
feel free to look around.

Please take a moment to read the license. It is paraphrased here in plain 
english:

This is all sample code.  There is no warranty.  There are no claims that 
it will work properly.  These examples are meant for demonstration purposes 
only, and to serve to help springboard your own development, and API 
integration efforts.  We do not recommend you use this code in a production 
environment. If you do, and it breaks, it is not our fault. You were warned.
We will not be held liable.

You can not use any of this code to access our APIs until you have a license 
agreement with ZipList, and ZipList has issued you partner credentials. 
Without partner credentials none of it will work anyway.  By making this 
code public we are neither granting nor implying a license to use ZipList, 
Inc.  APIs.

## Contents

Before swan diving into this code, it is recommended you read through the
only API documentation.  
[You can access it here](http://api.ziplist.com/developers)
In particular, you are going to want to look at the authentication
and the partner signed HMAC documentation before you delve into this
code.

### HMAC Examples -
One of the big hurdles for working with our APIs is authentication.  
While ther are exceptions, the vast majority of partner integrations 
will use a partner signed HMAC solution.

The hmac-exmaples subdirectory contains self contained examples of generating
a signed url in multiple languages.  If you write a new one, and would like
to share it with the ZipList community, then by all means send us a pull 
request. We'd love to have it.

## Shims -
We provide several shims which are example code, that demonstrate how 
to quickly come up to speed on integrating with our APIs. They are very
approachable and easy to understand.  As a general rule, you configure them
with a host and credentials.  And you make API calls by providing a path,
query parameters, and a data payload for POST operations.  Query params
and data payloads accept hashes or associative arrays, or whatever your
langauge supports, and the return the results in the same format, a hash.
Over the wire the use our JSON protocol, as the trip from hash to JSON
and back again is generally a very easy one.

- Perl Shim - perl-shim subdirectory
- PHP Shim  - php-shim subdirectory

If you build a shim in your platform language of choice, and wish to 
contribute it back to this effort, then by all means send us a pull 
request, we'd love to have it.


