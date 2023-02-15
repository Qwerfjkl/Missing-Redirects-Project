#!/usr/bin/php -q
<?php

/*
** @desc: returns a result of malformed pages that match the specified format string
*/
function dbResultSetMalformedPagesWithFormatString ($format_string) {
   return safeQuery ("SELECT   page_title,
                               format,
                               context,
                               section
                      FROM     malformed_page
                      WHERE    ($format_string)
                      ORDER BY page_title");
}

/*
** @desc: Special case for parentheses
*/
function dbResultSetMalformedPagesWithFormatStringAndOnlyOneErrorOfThisType ($format_string) {
   return safeQuery ("SELECT   page_title,
                               format,
                               context,
                               section
                      FROM     malformed_page
                      WHERE    ($format_string)
                      GROUP BY page_title
                      HAVING   count(*) = 1
                      ORDER BY page_title");
}


/*
** @desc: returns a result of malformed pages for double-redirects of redirects to non-existent pages.
*/
function dbResultSetMalformedPagesWithBadRedirectTarget () {
   return safeQuery ("SELECT   page_title,
                               format,
                               context,
                               section
                      FROM     malformed_page
                      WHERE    format LIKE 'Target of Redirect does not exist%'
                      ORDER BY page_title");
}


/*
** @desc:
*/
function dbResultSetMalformedPagesWithFormats($array_of_formats, $report_articles_with_multiple_errors) {
    
    $format_string = "";
    foreach ($array_of_formats as $format) {
        if ($format_string != "") $format_string .= " OR ";
        $format_string .= "format = '" . addslashes($format) . "'";
    }
        
    if ($report_articles_with_multiple_errors) {
        return dbResultSetMalformedPagesWithFormatStringAndOnlyOneErrorOfThisType($format_string);
    }
    else {
        return dbResultSetMalformedPagesWithFormatString($format_string);
    }
}



/*
** @desc: saves the specified malformed page formats to disk
*/
function outputToFile($description, $array_of_formats, $include_unclosed_or_unopened_text = true, $no_redirect = false, $context_desc = "On line that starts with",
                      $before_context = "<nowiki>", $after_context = "</nowiki>") {
        
    $res = dbResultSetMalformedPagesWithFormats($array_of_formats, $description == "ordinary-brackets");
    
    switch ($description) {
      case "ordinary-brackets" : $please_help_out_text = "Balance parentheses - " . STANDARD_BLURB;
    							               break;
    	case "double-quotes"     : 
    	case "triple-quotes"     : $please_help_out_text = "Wiki quotes - " . STANDARD_BLURB;
    	                           break;
    	case "square-brackets"   : $please_help_out_text = "Balance brackets - " . STANDARD_BLURB;
    	                           break;
    	case "braces-tables"     : $please_help_out_text = "Fix table or curly braces - " . STANDARD_BLURB;
    	                           break;
    	case "redirect-syntax"   : $please_help_out_text = "Fix Redirect Syntax - " . STANDARD_BLURB;
    	                           break;    
    	case "double-redirect"   : $please_help_out_text = "Fix Double Redirect - " . STANDARD_BLURB;
    	                           break;    
    	case "div-tags"          : $please_help_out_text = "Balance Div tags - " . STANDARD_BLURB;
    	                           break; 
    	case "ascii-arrows"      : 
    	case "miscellaneous"     : 
    	case "headings"          : 
      default                  : $please_help_out_text = STANDARD_BLURB;
                                 break;
    }
    
    $counter = 0;
    while (list($page_title, $format, $context, $section) = DB_fetch_array($res)) {
        
        // convert underscores to spaces.
        $page_title = undoLinkFormatting($page_title);
        
        if ($counter == 0 || $counter % MAX_ITEMS_PER_PAGE == 0 ) {
            
            // if have a file open, then close it now.
            if (isset($fp)) {
                fclose($fp);   
            }
            
            $filename = "malformed_syntax/$description/$description" . "-" . str_pad($counter / MAX_ITEMS_PER_PAGE, FILENAME_PADDING, "0", STR_PAD_LEFT) . ".txt";
            $fp = fopen("./" . $filename, "w");
            if ($fp===false) {
                print "cannot write to file: $filename  - exiting.\n";
                exit();
            }
            
            // write out the file header    
            fwrite($fp, "[[WP:WS|Back to main index page]].\n\n");
            fwrite($fp, "'''How does this work?'''\n");
            fwrite($fp, "# Randomly select one block of 5 problems from the list of contents below.\n");
            fwrite($fp, "# Fix those 5 syntax problems.\n");
            fwrite($fp, "# With each fix, if you want, set the edit description to <nowiki>\"$please_help_out_text\"</nowiki>  (This is to encourage more malformed syntax to be fixed.)\n");
            fwrite($fp, "# Then delete that block of 5 problems from this page (so that we know that they're done).\n");
            fwrite($fp, "\nThat's it.\n");
            fwrite($fp, "\n'''Tip:''' Copy this into a text editor now so that you have it handy: <nowiki>$please_help_out_text</nowiki>\n\n");
            
            
            // start new page file.
        }
        
        if ($counter == 0 || $counter % ITEMS_PER_SECTION == 0 || $counter % MAX_ITEMS_PER_PAGE == 0 ) {
             fwrite ($fp, "\n\n===$page_title===\n");  
        }
        
        // write this to the file.
        $page_location_text = $no_redirect ? "[http://en.wikipedia.org/w/wiki.phtml?title=" . urlencode(standardLinkFormatting($page_title)) . "&redirect=no $page_title]" : "[[" . ($section ? $page_title . getSectionLink($section) . "|" : "") . urldecode($page_title) . "]]";
        fwrite ($fp, "* $page_location_text : " . ($include_unclosed_or_unopened_text ? "Unopened or unclosed <nowiki>$format</nowiki>" : $format) .  ($context==""? "" : " : $context_desc: " . $before_context . $context . $after_context) . "\n");
        
        // increment counter
        $counter+=1;
    }
    
    print "$description - $counter entries.\n";
}

// #####################################################################
// ##########################    GLOBAL CODE    ########################
// #####################################################################


// include library functions
include_once ("wiki.php");
require_once ("sql-queries.php");

// defines
define ("MAX_ITEMS_PER_PAGE", 120); // Keeps to less than 32 K
define ("ITEMS_PER_SECTION", 5);
define ("FILENAME_PADDING", 3);

define("STANDARD_BLURB", "[[WP:WS|Please help out by clicking here to fix someone else's Wiki syntax]].");


outputToFile("redirect-syntax", array("Bad REDIRECT syntax"), false, true );

outputToFile("double-redirect", array("Double-redirect"), false, true, "Current target", "[[", "]]" );

outputToFile("nonexistent-redirect-target", array("Target of Redirect does not exist"), false, true, "Target is", "[[", "]]" );

outputToFile("headings", array("==", "====", "===", "== and ===", "==== and ==", "== and ====",
                               "=== and ==", "==== and ===", "=== and ====") );


outputToFile("html-tags", array(  "-->", "<!--",
                                "<code>", "</code>",
                                "<div>", "</div>",
                                "<pre>", "</pre>",
                                "<math>", "</math>",
                                "<nowiki>", "</nowiki>" ) );

outputToFile("braces-tables", array ("{{", "|}", "{|", "}}", "|}}") );

outputToFile("ordinary-brackets", array("(", ")") );
outputToFile("double-quotes", array("''") );
outputToFile("triple-quotes", array("'''") );

outputToFile("square-brackets", array("]", "[", "[[", "]]", "[ and ]]", "[[ and ]", "[ and )", "( and ]", 
                                      "( and ]]", "] and )", "( and [", ") and ]", "( and [[",
                                      "]] and )", "] and [", "[ and [[", "[[ and [", "[[ and ] and [",
                                      "] and [ and ]]", "]] and [[", "]] and ]") );


outputToFile("miscellaneous", array(") and (", "'' and )", "( and ''", "'' and (", ") and ''",
                                    "'' and ]]", "] and ==", "'' and ] and )", "'' and [[", 
                                    "''' and [[", "]] and '''", "'' and ]", "[[ and )", ") and ]]", "( and ==", 
                                    "[ and (", "( and [[ and ]", "[ and ''", "== and )", "[[ and (",
                                    ") and [", "== and ]]", "[ and ( and ]]", "[[ and ''", "''' and )", 
                                    "] and (", "''' and ==", "[[ and ] and )", "]] and [[ and ]", 
                                    "] and ]]", ") and [[", "'' and ) and (", "] and [ and )", ") and ==== and ==", 
                                    "== and (", "[[ and '''", "] and [ and (", ") and ( and [", "'' and [",
                                    "] and ''", "]] and (", "( and [ and ]]", "( and '''", 
                                    "== and [[ and [", "[ and ]] and )", "]] and ] and ==", "[[ and ==",
                                    "'' and [[ and ]", ") and '''", ") and [ and ]]", "[ and ) and ''",
                                    "[ and ) and (", "'' and [[ and ]", "]] and ''", "'' and [ and ]]",
                                    "''' and ]]", "]] and '' and [["
                                    ) );

outputToFile("mixed-quotes", array ( 
                                    "''' and ''",
                                    "''' and '' and [ and ]]",
                                    "'' and '''"
                                   ) );


?>
