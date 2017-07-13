# CodeSamples
This respository contains some samples of my work.  Please feel free to browse.
If you have any questions or would like to use some of this code in your own
work, please contact Ed Zenisek (ezenisek@gmail.com)

*I will reference a project called Maestro in many of these samples.  Maestro
has been my main focus at NMSU since 2012, so many of my samples come from 
there.  It is an enterprise program for handling IRB approvals for research at 
NMSU*

### JS&CSS Compressor
This is a snippit from Maestro which automatically combines and compresses all
Javascript and CSS code from the main program as well as additional plug-ins and
3rd party programs.  Maestro has dozens of CSS and Javascript scripts included,
and adding them all seperately as a page loads causes more traffic and slower
load times.  By combining them all together and then compressing them (using a 
program called YUICompressor) the load can be cut to a single request for CSS 
and a single request for JS.  It has the added benefit of munging the JS code, 
making it harder to read by potential Script Kiddies.

### Modular Authentication
This set of classes allows Maestro to have multiple authentication sources.  In 
this way, users from multiple locations can authenticate and login to use
Maestro services.  Currently I have a class for LDAP (Lightweight Directory
Access Protocol, such as Active Directory) and local (Database) authentication.
In order to enable a new type of authentication such as OAuth or Google Sign-in, 
a new class with the specifics for that type of login can be written and plugged
in.


