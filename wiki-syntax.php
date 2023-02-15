<?php

// ------------------ Definitions ---------------------------

// For several types of tag, we create 4 defines:
// * A short tag open (e.g. "<div"), which is missing the closing ">". 
//   This is used for detecting when the tag is opened. It is required because sometimes people add parameters to tags
//   (e.g. "<div id='12'>), and sometimes they just use extra spaces (e.g. "</div >"
// * A short tag close (used for the same reasons as the short tag open).
// * A full tag open (e.g. "<div>"). This is used for storing on the stack, as users would
//   probably rather see that there was a problem with "<div>", rather than with "<div".
// * A full tag close (used for the same reasons as the full tag open).

// Math tags
define("FULL_MATH_OPEN"    , "<math>" );
define("FULL_MATH_CLOSE"   , "</math>");

// Nowiki tags
define("SHORT_NOWIKI_OPEN" , "<nowiki"  );
define("FULL_NOWIKI_OPEN"  , "<nowiki>" );
define("SHORT_NOWIKI_CLOSE", "</nowiki" );
define("FULL_NOWIKI_CLOSE" , "</nowiki>");

// Code tags
define("SHORT_CODE_OPEN"   , "<code"  );
define("FULL_CODE_OPEN"    , "<code>" );
define("SHORT_CODE_CLOSE"  , "</code" );
define("FULL_CODE_CLOSE"   , "</code>");

// div tags
define("SHORT_DIV_OPEN"    , "<div"  );
define("FULL_DIV_OPEN"     , "<div>" );
define("SHORT_DIV_CLOSE"   , "</div" );
define("FULL_DIV_CLOSE"    , "</div>");

// pre tags
define("FULL_PRE_OPEN"     , "<pre>" );
define("FULL_PRE_CLOSE"    , "</pre>");

// image tags
define("IMAGE_OPEN"       , "[[image:");

// ------------------ Functions ---------------------------


/*
** @desc: Given a type of formatting, this adds it to, or removes it from, the stack (as appropriate).
*/
function addRemoveFromStack($format, $start_format, $same_start_and_end, &$stack, $string) {
    // if it is there, remove it from the stack, as long is not start of format
    if (isset($stack[$start_format]) && ($same_start_and_end || $format != $start_format)) {
        array_pop($stack[$start_format]);
        if (empty($stack[$start_format])) unset($stack[$start_format]);
    }
    // otherwise, add it, and the string responsible for it.
    else {
        $stack[$format][] = $string;
    }
}


/*
** @desc: handles the stack for the formatting
*/
function formatHandler($string, &$formatStack, $reset = false) {
    static $in_nowiki, $in_comment, $in_math, $in_code, $in_pre;
    
    if (!isset($in_nowiki) || $reset) {
        $in_nowiki = false;
        $in_comment = false;
        $in_math = false;
        $in_code = false;
        $in_pre = false;
    }
    
    // don't bother processing an empty string.
    $string = trim($string);
    if ($string == "") return;

    $pattern      = "%(''''')|(''')|('')|"    // Wiki quotes
                  . "(\[\[image\:)|"          // image tags
                  . "(\[\[)|(\[)|(]])|(])|"   // Wiki square brackets
                  . "(\{\|)|(\|\}\})|(\|\})|" // Wiki table open & Close + infobox close.
                  . "(\{\{)|(\}\})|"          // Transclude open and close
                  . "(<!--)|(-->)|"           // Comment open and close
                  . "(====)|(===)|(==)|"      // Wiki headings
                  . "(" . FULL_MATH_OPEN . ")|(" . FULL_MATH_CLOSE . ")|"     // Math tags
                  . "(" . SHORT_NOWIKI_OPEN . ")|(" . SHORT_NOWIKI_CLOSE . ")|" // Nowiki tags
                  . "(" . SHORT_CODE_OPEN . ")|(" . SHORT_CODE_CLOSE . ")|"     // Code tags
                  . "(" . SHORT_DIV_OPEN . ")|(" . SHORT_DIV_CLOSE . ")|"       // div tags
                  . "(" . FULL_PRE_OPEN . ")|(" . FULL_PRE_CLOSE . ")%i";     // pre tags
                  
    $matches = preg_split ($pattern, strtolower($string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    foreach ($matches as $format) {
        
        if ($format == SHORT_NOWIKI_OPEN) {
            addRemoveFromStack(FULL_NOWIKI_OPEN, FULL_NOWIKI_OPEN, false, $formatStack, $string);
            $in_nowiki = true;
        }
        else if ($format == SHORT_NOWIKI_CLOSE) {
            addRemoveFromStack(FULL_NOWIKI_CLOSE, FULL_NOWIKI_OPEN, false, $formatStack, $string);
            $in_nowiki = false;
        }
        else if ($format == FULL_MATH_OPEN) {
            addRemoveFromStack(FULL_MATH_OPEN, FULL_MATH_OPEN, false, $formatStack, $string);
            $in_math = true;
        }
        else if ($format == FULL_MATH_CLOSE) {
            addRemoveFromStack(FULL_MATH_CLOSE, FULL_MATH_OPEN, false, $formatStack, $string);
            $in_math = false;
        }
        else if ($format == "<!--") {
            addRemoveFromStack($format, $format, false, $formatStack, $string);
            $in_comment = true;
        }
        else if ($format == "-->") {
             addRemoveFromStack($format, "<!--", false, $formatStack, $string);
             $in_comment = false;
        }
        else if ($format == SHORT_CODE_OPEN) {
            addRemoveFromStack(FULL_CODE_OPEN, FULL_CODE_OPEN, false, $formatStack, $string);
            $in_code = true;
        }
        else if ($format == SHORT_CODE_CLOSE) {
            addRemoveFromStack(FULL_CODE_CLOSE, FULL_CODE_OPEN, false, $formatStack, $string);
            $in_code = false;
        }
        else if ($format == FULL_PRE_OPEN) {
            addRemoveFromStack(FULL_PRE_OPEN, FULL_PRE_OPEN, false, $formatStack, $string);
            $in_pre = true;
        }
        else if ($format == FULL_PRE_CLOSE) {
            addRemoveFromStack(FULL_PRE_CLOSE, FULL_PRE_OPEN, false, $formatStack, $string);
            $in_pre = false;
        }
        
        else if (!$in_math && !$in_nowiki && !$in_comment && !$in_code && !$in_pre) {
            
            
            if ($format == "'''") {
                addRemoveFromStack($format, $format, true, $formatStack, $string);
            }
            else if ($format == "''") {
                addRemoveFromStack($format, $format, true, $formatStack, $string);
            }
            else if ($format == "'''''") {
                // this combines both of the above Wiki quote cases.
                addRemoveFromStack("'''", "'''", true, $formatStack, $string);
                addRemoveFromStack("''", "''", true, $formatStack, $string);
            }
            else if ($format == "[[") {
                addRemoveFromStack($format, $format, false, $formatStack, $string);
            }
            else if ($format == "[") {
                addRemoveFromStack($format, $format, false, $formatStack, $string);
            }
            else if ($format == "]]") {
                // the double brackets can either close an image tag, or close a link tag:
                if (isset($formatStack[IMAGE_OPEN]) && !isset($formatStack["[["]) ) {
                    addRemoveFromStack($format, IMAGE_OPEN, false, $formatStack, $string);
                }
                else {
                    addRemoveFromStack($format, "[[", false, $formatStack, $string);
                }
            }
            else if ($format == "]") {
                addRemoveFromStack($format, "[", false, $formatStack, $string);
            }
            else if ($format == "{|") {
                addRemoveFromStack($format, $format, false, $formatStack, $string);
            }
            else if ($format == "|}") {
                addRemoveFromStack($format, "{|", false, $formatStack, $string);
            }
            else if ($format == "====") {
                addRemoveFromStack($format, $format, true, $formatStack, $string);
            }
            else if ($format == "===") {
                addRemoveFromStack($format, $format, true, $formatStack, $string);
            }
            else if ($format == "==") {
                addRemoveFromStack($format, $format, true, $formatStack, $string);
            }
            else if ($format == "{{") {
                addRemoveFromStack($format, $format, false, $formatStack, $string);
            }
            else if ($format == "}}") {
                addRemoveFromStack($format, "{{", false, $formatStack, $string);
            }
            else if ($format == "|}}") {
                addRemoveFromStack($format, "{{", false, $formatStack, $string);
            }
            else if ($format == SHORT_DIV_OPEN) {
                addRemoveFromStack(FULL_DIV_OPEN, FULL_DIV_OPEN, false, $formatStack, $string);
            }
            else if ($format == SHORT_DIV_CLOSE) {
                addRemoveFromStack(FULL_DIV_CLOSE, FULL_DIV_OPEN, false, $formatStack, $string);
            }
            else if ($format == IMAGE_OPEN) {
                addRemoveFromStack(IMAGE_OPEN, IMAGE_OPEN, false, $formatStack, $string);
            }  
        }
    }
}


/*
** @desc: returns whether a format is a multi-line or a single line format.
*/
function is_single_line_format($format) {
    if ($format == "'''"  || $format == "''"  ||
        $format == "[["   || $format == "]]"  || 
        $format == "["    || $format == "]"   || 
        $format == "====" || $format == "===" || $format == "==" ||
        $format == "("    || $format == ")"  ) {
            return true;
    }
    return false;
}


/*
** @desc: checks the formatting of one line of text, and logs any errors found.
*/
function checkLineFormatting($page_title, $full_line, &$formatting_stack, &$section) {

    // the temp array for storing the section heading parsing output
    $section_array = array();
    
    // whether we are on a section heading line or not
    $heading_line = false;
    
    if (preg_match("/^={2,4}([^=]+)={2,4}$/", trim($full_line), $section_array)) {
        $section = trim($section_array[1]);
        $heading_line = true;
    }
    
    // if no formatting in operation, return
    if (empty($formatting_stack)) return;
          
    // don't report any heading problems if we're not in a heading line.
    if (!$heading_line) {
        if (isset($formatting_stack["=="]))   unset($formatting_stack["=="]);
        if (isset($formatting_stack["==="]))  unset($formatting_stack["==="]);
        if (isset($formatting_stack["===="])) unset($formatting_stack["===="]);
    }
    
    // A string that holds the description of what the formatting problem is:
    $format_string = "";
    
    // for each misplaced bit of formatting
    foreach (array_keys($formatting_stack) as $format) {
        
        // only consider single-line formatting at this point
        if (is_single_line_format($format)) {
           
            // save this format string.
            if ($format_string != "") {
                $format_string .= " and ";
            }
            
            $format_string .= "$format";
            
            // then remove it from the stack
            unset($formatting_stack[$format]);
        }
    }
    
    // if after that there were any formatting problems, the store those problems.
    if ($format_string != "") {
        // save the formatting problem to the DB.
        dbSaveMalformedPage(addslashes(standardLinkFormatting($page_title)), addslashes($page_title), addslashes($format_string), addslashes(neuterWikiString($full_line)), addslashes($section));
    }
}


/*
** @desc: save any full-page formatting problems.
*/
function saveFullPageFormattingProblems($formatting_stack, $page_title) {
    foreach (array_keys($formatting_stack) as $format) {
        dbSaveMalformedPage(addslashes(standardLinkFormatting($page_title)), addslashes($page_title), addslashes($format), "");
    }
}


// ------------- How to call these functions ---------------------------


/*
The usage of the above functions (as used by the Wiki Syntax Project) is like this:

// for each article in the English Wikipedia, set $page_title

    $formatting_stack = array();
    // reset the static vars in the format handler
    formatHandler("", $formatting_stack, true);

    // for each $line of the article text in the $page_title article
           formatHandler($line, $formatting_stack);
           checkLineFormatting($page_title, $line, $formatting_stack);
    // end for

    // then save any full-page formatting problems.
    saveFullPageFormattingProblems($formatting_stack, $page_title)

// end for   

*/

?>
