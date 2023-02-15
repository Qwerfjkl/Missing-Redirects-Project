#!/bin/sh

# Purpose : Download the latest EN Wikipedia database dump, store it, and then run our script.
# Author  : Nick Jenkins
# License : GPL v2
# Created : 25-April-2005

# Check we have the right number of command line args.
if [ ! $1 ]
then
   /bin/echo "Usage  : `basename $0` date_of_file_archive"
   /bin/echo "Example: `basename $0` 20050421"
   exit 1
fi

# Define the main variables.
FILE_NAME=$1_cur_table.sql.gz

# Download the data, and then check what we have downloaded looks valid.
/bin/date
/bin/echo "Downloading data"
/usr/bin/wget http://download.wikimedia.org/wikipedia/en/$FILE_NAME

/bin/date
/bin/echo "Checking downloaded .gz file integrity"

if [ "`/bin/gunzip --test $FILE_NAME 2>&1`" != "" ]
then
   /bin/echo "Error - Downloaded file is not a valid gzip archive:"
   /bin/echo "`/bin/gunzip --test $FILE_NAME 2>&1`"
   exit 1
fi

# Clean out the current obsolete data, before storing the new data.
/bin/date
/bin/echo "Deleting cur table"
/bin/echo "DELETE FROM cur" | /usr/bin/mysql enwiki
/bin/date
/bin/echo "Deleting obsolete generated data (20 mins)"
/bin/echo "DELETE FROM malformed_page" | /usr/bin/mysql enwiki
/bin/echo "DELETE FROM redirect_candidate" | /usr/bin/mysql enwiki
/bin/echo "DELETE FROM suggested_link" | /usr/bin/mysql enwiki

# Uncompress and store the new data.
/bin/date
/bin/echo "Uncompressing and storing new data"
/bin/gunzip --stdout --quiet $FILE_NAME | /usr/bin/mysql enwiki

# Run the script.
/bin/date
/bin/echo "Generating new data"
/var/www/hosts/www.nickj.org/code/wiki/suggester.php
/bin/echo "All done"
/bin/date

# Bail, successfully.
exit 0