<?php

// Purpose:    Stores functionality that is used in more than one page or
// script in the application, but that is not generic. Generic functionality should
// go into the non-app specific library file, and functionality that is specific to just one section
// should go into the appropriate file. Everything else should go here.

// include the file which provides the DB connection (can change as appropriate - need to create the wrapper and then test the app if changing this)
require_once("mysql.php");

// include the library functions
require_once("lib.php");

// report any errors at all
error_reporting(E_ALL);

// connect to the Database: (needed for sessions)
$hostname = PHP_OS == "WINNT" ? "ludo" : "localhost";
if (DB_connect("enwiki", "enwiki", "enwiki", $hostname) === false) {
    // if we cannot connect, print a legible non-cachable message, and exit.
    force_no_cache();
    print "Cannot connect to database!";
    exit();
}

// set the custom error handler - saves errors to the DB for review later on
set_error_handler("error_handler");


// ----------------   Error Handling function -------------------------

function error_handler ($type, $message, $file=__FILE__, $line=__LINE__) {
    global $page_title, $PHP_SELF;
    
    // these 'errors' are logged by Zend Studio when mouse-over an undefined var, and so are quite meaningless
    if ($file == "variable expression") return;
    
    dbSaveAddErrorLog  ($PHP_SELF,
                        (isset($page_title)?"'" . addslashes($page_title) . "'":"NULL"),
                        $type,
                        DB_safe_string(trim($message)),
                        $file,
                        $line );
}

// ----------------- Common wiki functions ------------------------


/*
** @desc: returns the expected link name, based on the standard wiki formatting.
*/
function standardLinkFormatting($name) {
    return str_replace(" ", "_", $name);
}


/*
** @desc: returns the standard text, when given a link name.
*/
function undoLinkFormatting($name) {
    return str_replace("_", " ", $name);
}

/*
** @desc: given a section name, returns a suitable # section link to it
*/
function getSectionLink($section) {
    // no section supplied
    if (empty($section)) {
        return "";
    }
    // if has quotes or tags, then don't use the section
    else if (strpos($section, "''") !== false || strpos($section, ">") !== false || strpos($section, "<") !== false) {
        return "";
    }
    else if (strpos($section, "[") !== false || strpos($section, "]") !== false || strpos($section, "|") !== false) {
        
        // try to remove any simple links
        $section = preg_replace("/\[\[([0-9A-Za-z _'\(\)]+)\]\]/", "\\1", $section);
        
        // we didn't succeed, then don't return a section
        if (strpos($section, "[") !== false || strpos($section, "]") !== false || strpos($section, "|") !== false) {
            return "";
        }
        
    }
    
    // section markers have their own encoding:
    return "#" . urlencode(standardLinkFormatting($section));
}


/*
** @desc: takes a wiki string, and removes the newlines, &'s, >'s, and <'s.
*/
function neuterWikiString($string) {
    // remove newline chars, and escape '<' and '>' and '&' (note that & needs to come first)
    return str_replace( array ("\n", "&", "<", ">"), array(" ", "&amp;", "&lt;", "&gt;"), $string);
}


/*
** @desc: fetches the article text from the web
*/
function getArticleTextFromWeb($page_title_url, &$str) {
        
    // build the URL
    $url = URL_PREFIX . $page_title_url . URL_SUFFIX;
    
    // start buffering output
    ob_start();
    
    // get the URL contents
    $read_bytes = @readfile($url);
    
    // End the output buffering and get the results
    $str = ob_get_contents();
    ob_end_clean();
    
    if (!$read_bytes) {
        print "No data read.\n";
        return false;
    }
    
    // find where content starts
    $start_pos = strpos ($str, START_CONTENT);
    
    // if start not found, then return false
    if ($start_pos === false) {
        print "Could not find start block.\n";
        return false;
    }
    
    // now shift forward from that point
    $start_pos = strpos ($str, ">", $start_pos) + 1;
    
    // if start not found, then return
    if ($start_pos === false) {
        print "Start pos not found.\n";
        return false;
    }
    
    // find end position
    $end_pos = strpos ($str, END_CONTENT, $start_pos);
    
    // if end_pos is not found, and is invalid
    if ($end_pos === false || $end_pos <= $start_pos + 1) {
        print "end_pos is not found, and is invalid.\n";
        return false;
    }
    
    $str = unhtmlentities(trim(substr ($str, $start_pos, $end_pos - $start_pos)));
    return true;
}


/*
** @desc: fetches the article text from the DB.
*/
function getArticleTextFromDB($page_title_url, &$str) {
        
    // get the contents from the database
    $str = trim(dbGetCurContents(addslashes($page_title_url)));
    
    // check that str looks valid, if not return false.
    if ($str == NULL) {
        return false;
    }
    
    if ($str == '0') {
        return false;
    }
    
    if ($str === false) {
        return false;
    }
    
    if ($str == "") {
        return false;
    }
    
    // add an extra newline to the database result, to ensure
    // any formatting at the very end gets processed OK.
    $str .= "\n"; 
    
    return true;
}


?>