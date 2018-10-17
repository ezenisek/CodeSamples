# My Portfolio
This respository contains some samples of my work.  Please feel free to browse.
If you have any questions or would like to use some of this code in your own
work, please contact Ed Zenisek (ezenisek@gmail.com)

*I will reference a project called Maestro in many of the code samples.  Maestro
has been my main focus at NMSU since 2012, so many of my samples come from 
there.  It is an enterprise program for handling IRB approvals for research at 
NMSU.*

##Code Samples

### JS&CSS Compressor
This is a snippit from Maestro which automatically combines and compresses all
Javascript and CSS code from the main program as well as additional plug-ins and
3rd party programs.  Maestro has dozens of CSS and Javascript scripts included,
and adding them all seperately during a page load causes more traffic and slower
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

### jQuery Pop Note
A for fun project that turned into a useful feature for Meastro, Pop Note is a 
jQuery plug-in that, along with the proper server side code, allows a user to 
create a virtual post-it note on the screen where they can write comments or 
thoughts.  The note is persistent, so it stays where it's put across sessions,
and it can be shared with other users.

### LIFT (Limited Instant File Transfer)
This is a module I developed for Drupal 7 to allow users to upload a file and
notify someone that the file is available for download.  The module is built to
only allow the file to be downloaded a configurable number of times, and for a 
configurable amount of time.  Once it has been successfully downloaded or the 
timer runs out, it is removed from the system and a notification is sent to the 
uploader.  This allows users to transfer files in a more secure way than 
dropbox.

### Drupal Store Update
This is a module I wrote for a local grocer that used a Drupal site for online
shopping.  They had a person dedicated to reading excel spreadsheets and 
updating the website manually twice a month with sales.  This module automates
that process.  It does the work of a full time employee, and the person doing 
the job manually was able to do the job they were hired for instead.

### Batch Mailer
This is an addon class for PHPMailer that I wrote to send large numbers of
emails in batches.  It's written so that the batches are sent server-side. That
way, the script will run even if a user browses away from it or the browser
crashes. It requires the class itself as well as a script placed on the server
to run the class in a recursive way.  Both are listed here, but will need a bit
of customization if you wish to use them yourself.

##Media Samples

### ISPCS Videos
In 2012 I was tasked with cutting together and producing a series of
promotional videos for the International Symposium for Personal and Commercial
Spaceflight by New Mexico Space Grant.  These are a couple of the videos I made.

### Logos and Images
This is just a sampling of some of the logo and image work I've done in my 
career.