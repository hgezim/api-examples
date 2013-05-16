csharp-shim
============

# C# Shim

This directory contains a very basic shim written in C# for the DotNet 
platform.  There is a ZipListAPI.sln solution file.  The project is
structured as a console application for now, with a basic main
entry point that makes a couple of API calls for demonstration purposes. 

Moving forward, the shim should probably be packaged up as an importable
assembly, and the example codes perhaps moved into a test module, etc.

This example depends on a JSON implementation.  As I developed this I
played with some of the JSOM implementations availble in the the
DotNet platform. Some builtin and some third party. The 
[JSON@CodeTitans](http://codetitans.codeplex.com/) proved to be reasonably
fast, stable, and most importantly provided and easy to access JSON object
class, that let you treat deseriaizled JSON as basically a hash contiaing
different types of data.

While you certainly could create wrapper classes for each and every resource
available from the ZipList API, it is this developers opinion that you should
tread carefully down that path.  We do sometimes make addative changes to the
APIs. We almost certainly will at some point, rev the version.

Regardless, the JSON implementation used in this example seems to be a pretty
good one. You can of course, however, swap it out for your preferred JSON
implementation.
