I, Qwerfjkl, have updated this repository at https://gitlab.wikimedia.org/toolforge-repos/missingredirectsproject. This serves as an archive of NickJ's work.

#############
### When ####
#############

27-June-2005: Initial release, ver 1.0

#############
### What ####
#############

This is the source code for these 3 projects:

* Link Suggester and LinkBot   ( http://en.wikipedia.org/wiki/User:Nickj/Link_Suggester )
* Wiki Syntax Project          ( http://en.wikipedia.org/wiki/Wikipedia:WikiProject_Wiki_Syntax )
* Mssing redirects & disambigs ( http://en.wikipedia.org/wiki/User:Nickj/Redirects )

The implementation is mostly in PHP4, with some SQL scripts, this text file, and a BASH shell script.

################
### License ####
################

All GPL, version 2.

######################
### File contents ####
######################

README.txt                  : The file you're reading now.
enwiki-DB-schema.sql        : The MySQL database schema used by these files, plus the "never_link_to" data.
sql-queries.php             : Contains database queries for reading and updating the database.
wiki.php                    : Stores functionality that is used in more than one script, but which is specific to the Wikipedia.
wiki-syntax.php             : The functions that detect Wiki Syntax problems (excluding redirect-related problems)
new-run.sh                  : Downloads the current dump of the Wikipedia, purges obsolete data, and runs the suggester.php script.
suggester.php               : Finds linkbot suggestions, redirect suggestions, and Wiki Syntax problems. Takes over a week to run.
output_new_redirects.php    : Outputs text files containing redirect suggestions.
most-suggested.php          : Web script, that shows the most suggested links (in HTML format). This is because sometimes silly redirects get added, and so the results need to be checked for new "never_link_to" candidates.
mysql.php                   : A series of functions for connection to and querying MySQL databases.
output_malformed_pages.php  : Outputs the files listing Syntax problems. Used by the Wiki Syntax project.
redirect-cleanup.sql        : A SQL script that deletes bad redirect suggestions. Apply these queries this before running output_new_redirects.php
upload-suggestions.php      : Bot script to upload the LinkBot's suggestions to the Wikipedia.
lib.php                     : A project-independent store of useful PHP classes or functions.

######################
### Who / Credits ####
######################

Nick Jenkins - http://en.wikipedia.org/wiki/User:Nickj  (Either leave a message on talk page, or to email, use the "E-mail this user" link).

############################
### Questions & Answers ####
############################

Q: This code is a mess. Can I clean it up / refactor it?
A: Yes, please go for it. Please add yourself to the "Who / credits" section,
   and send me the updated ZIP file, and I'll replace the current ZIP file with that new ZIP file.

Q: I've got a bugfix, can I send it to you?
A: Yes, please go for it. Please add yourself to the "Who / credits" section,
   and send me the updated ZIP file, and I'll replace the current ZIP file with that new ZIP file.

Q: Can I take the ideas and reimplement them in another language?
A: Yes, please go for it.

Q: Can I incorpotate some of this functionality into MediaWiki?
A: Yes, please go for it.

Q: Can I run this script, and update any of the three project pages with more recent results?
A: Yes, please go for it.

Other questions? Just ask. Basically if it helps the Wikipedia, and doesn't involve more work for me, then I'm very likely to say yes.

