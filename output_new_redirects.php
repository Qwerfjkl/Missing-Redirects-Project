#!/usr/bin/php -q
<?php

/*
** @desc: returns a result of redirect labels.
*/
function dbResultSetRedirectLabels($cutoff) {
 return safeQuery ("SELECT   label,
                             count(*) AS count
                    FROM     redirect_candidate
                    GROUP BY label
                    HAVING   count > '$cutoff'
                    ORDER BY count DESC, 
                             dest,
                             label");

}


/*
** @desc: returns a result of redirect labels.
*/
function dbResultSetRedirectDestsForLabel($label) {
   return safeQuery ("SELECT dest, 
                             count(*) AS count
                      FROM   redirect_candidate
                      WHERE  label = '$label'
                      GROUP BY dest
                      ORDER BY count desc");
}


/*
** @desc returns whether this is something we want to link on or not.
*/
function isBadlySyntaxedLink($link) {
    // empty links are badly syntaxed
    if (empty($link)) return true;
    
    // assume links with these characters in them are badly syntaxed
    if (strpos($link, "''") !== false || strpos($link, "/") !== false || strpos($link, "<") !== false
        || strpos($link, ">") !== false || strpos($link, ";") !== false || strpos($link, "[") !== false
        || strpos($link, "]") !== false || strpos($link, "#") !== false || strpos($link, "+") !== false
        || strpos($link, "*") !== false || strpos($link, "}") !== false || strpos($link, "{") !== false) 
        return true;

    // otherwise is probably OK.
    return false;
}


function createFilePointer($dir, $filenamePrefix, $counter, $num_items_per_page) {
    $filename = $dir . "/" . $filenamePrefix . "-" . str_pad($counter / $num_items_per_page, FILENAME_PADDING, "0", STR_PAD_LEFT) . ".txt";
    $fp = fopen("./" . $filename, "w");
    if ($fp===false) {
        print "cannot write to file: $filename  - exiting.\n";
        exit();
    }
    
    // write the header bit
    fwrite($fp, "[[User:Nickj/Redirects|Back to main index page]].\n\n");
    fwrite($fp, "'''How does this work?'''\n\n");
    fwrite($fp, "# Randomly select a section from the list of contents below.\n");
    fwrite($fp, "# In that section, you will find 5 suggested redirects/disambiguations.\n");
    fwrite($fp, "#* Each suggestion will have a link that says \"Easy Preview\". Clicking this link automatically fills in the description, and the contents of the page, but doesn't save it. You still have to then press the Save button if you want to save the suggestion, but you don't have to type a single thing.\n");
    fwrite($fp, "#* Each suggestion will have the name of the page that's being suggested, just to the right of \"Easy Preview\". If this link is red, then there is nothing at this page currently, so you can add the suggestion, if you agree with it. If this link is blue, then it's probably already been done.\n");
    //#fwrite($fp, "# Then please delete from this page any suggestions that you added, or that have already been done (this is to make it easier to see what's still to be done). 
    fwrite($fp, "# If there were suggestions that you thought were bad suggestions, then please <s>strike</s> them out, rather than deleting them (and they will then be excluded from future lists). When all the suggestions on a page are either blue or have been struck out, then that page is completed (and can be crossed off the main list).\n");
    fwrite($fp, "\nThat's it. Repeat, if you like.\n\n");
    
    return $fp;
}

function getRedirectFilePointer($redirect_count) {
    return createFilePointer("create-redirects" , "redirects", $redirect_count, MAX_REDIRECTS_PER_PAGE);
}

function getDisambigFilePointer($disambig_count) {
    return createFilePointer("create-redirects" , "disambig", $disambig_count, MAX_DISAMBIGS_PER_PAGE);
}


/*
** @desc: returns a string with a list of 
*/
function easyPreviewString($page_title, $contents, $editDesc) {
    return "[http://en.wikipedia.org/w/wiki.phtml?title=" . urlencode(standardLinkFormatting($page_title))
           . "&wpTextbox1=<nowiki>" . urlencode($contents) . "&action=edit&wpSummary=" . urlencode($editDesc) . "</nowiki>&wpPreview=1 Easy Preview]";
}


function getContainsState($str) {
    if (
        strpos($str, "Alabama") !== false ||
        strpos($str, "Alaska") !== false ||
        strpos($str, "Arizona") !== false ||
        strpos($str, "Arkansas") !== false ||
        strpos($str, "California") !== false ||
        strpos($str, "Colorado") !== false ||
        strpos($str, "Connecticut") !== false ||
        strpos($str, "Delaware") !== false ||
        strpos($str, "Florida") !== false ||
        strpos($str, "Georgia") !== false ||
        strpos($str, "Hawaii") !== false ||
        strpos($str, "Idaho") !== false ||
        strpos($str, "Illinois") !== false ||
        strpos($str, "Indiana") !== false ||
        strpos($str, "Iowa") !== false ||
        strpos($str, "Kansas") !== false ||
        strpos($str, "Kentucky") !== false ||
        strpos($str, "Louisiana") !== false ||
        strpos($str, "Maine") !== false ||
        strpos($str, "Maryland") !== false ||
        strpos($str, "Massachusetts") !== false ||
        strpos($str, "Michigan") !== false ||
        strpos($str, "Minnesota") !== false ||
        strpos($str, "Mississippi") !== false ||
        strpos($str, "Missouri") !== false ||
        strpos($str, "Montana") !== false ||
        strpos($str, "Nebraska") !== false ||
        strpos($str, "Nevada") !== false ||
        strpos($str, "New Hampshire") !== false ||
        strpos($str, "New Jersey") !== false ||
        strpos($str, "New Mexico") !== false ||
        strpos($str, "New York") !== false ||
        strpos($str, "North Carolina") !== false ||
        strpos($str, "North Dakota") !== false ||
        strpos($str, "Ohio") !== false ||
        strpos($str, "Oklahoma") !== false ||
        strpos($str, "Oregon") !== false ||
        strpos($str, "Pennsylvania") !== false ||
        strpos($str, "Rhode Island") !== false ||
        strpos($str, "South Carolina") !== false ||
        strpos($str, "South Dakota") !== false ||
        strpos($str, "Tennessee") !== false ||
        strpos($str, "Texas") !== false ||
        strpos($str, "Utah") !== false ||
        strpos($str, "Vermont") !== false ||
        strpos($str, "Virginia") !== false ||
        strpos($str, "Washington") !== false ||
        strpos($str, "West Virginia") !== false ||
        strpos($str, "Wisconsin") !== false ||
        strpos($str, "Wyoming") !== false
        ) {
            return true;
        }
        else {
            return false;      
        }
}

// #####################################################################
// ##########################    GLOBAL CODE    ########################
// #####################################################################


// include library functions
include_once ("wiki.php");
require_once ("sql-queries.php");

// defines
define ("MAX_REDIRECTS_PER_PAGE", 110); // Keeps to less than 32 K (usually)
define ("MAX_DISAMBIGS_PER_PAGE", 65);  // Keeps to less than 32 K (usually)
define ("FILENAME_PADDING", 3);


define ("SUGGESTED_TIMES", 3);
define ("DISAMBIG_CUTOFF", 3); //1);
define ("REDIRECT_PERCENTAGE", 0.95);

define ("ITEMS_PER_SECTION", 5);

define ("PROJECT_BLURB", " - [[User:Nickj/Redirects|Missing Redirects - Join us in adding redirects and disambigs that appear to be missing]]");

// create the redirect output file
$redirect_count = 0;
$rfp = getRedirectFilePointer($redirect_count);

// create the disambig output file
$disambig_count = 0;
$dfp = getDisambigFilePointer($disambig_count);

// for each redirect label
$res = dbResultSetRedirectLabels (SUGGESTED_TIMES);

while (list($label) = DB_fetch_array($res)) {
    
    // if the label seems to have any kind of invalid characters, then skip it.
    if (isBadlySyntaxedLink($label)) {
        continue;
    }
    
    
    $total_count = 0;
    
    // the dests found
    $array_of_dests = array();
    
    $has_comma = false;
    $contains_state = false;
        
    $dest_res = dbResultSetRedirectDestsForLabel(addslashes($label));
    while (list($dest, $count) = DB_fetch_array($dest_res)) {
        // if the dest seems to have any kind of invalid characters, then skip it.
        if (isBadlySyntaxedLink($dest)) {
            continue;
        }
        
        if (strpos($dest, ",") !== false) {
            $has_comma = true;
        }
        
        $contains_state |= getContainsState($dest);
        
        $array_of_dests[] = array($dest, $count);
        $total_count += $count;
    }
    
    // something must be suggested more than 5 times in total before we consider it.
    if ($total_count < SUGGESTED_TIMES) continue;
    
    // Temporary stuff, commented out:
    // if (!$has_comma) continue;
    // if (!$contains_state) continue;
    
    // if we may need a disambiguation page
    if (sizeof($array_of_dests) > 1) {
        
        $disambig_string   = "[[$label]] may refer to:\n";
        $disambig_contents = "'''$label''' may refer to:\n";
        $disambig_edit_desc= "";
        
        // for each suggestion
        foreach ($array_of_dests as $index => $arr) {
            
            list ($dest, $count) = $arr;
            // if something accounts for more than 95% of the suggestions, then delete every other suggestion
            // and switch to using a redirect to this suggestion, not a disambig
            if ($count / $total_count >= REDIRECT_PERCENTAGE && !$has_comma) {
                unset($array_of_dests);
                $array_of_dests = array();
                $array_of_dests[] = array($dest, $count);
                break;
            }
            // if less than X things use this link in this way, then remove it
            else if ($count < DISAMBIG_CUTOFF && !$has_comma) { //= # && sizeof($array_of_dests) <= 3) {
                unset($array_of_dests[$index]);
                continue;
            }
            else {
                $disambig_string .= "* [[$dest]]  $count\n";
                $disambig_contents .= "*[[$dest]]\n";
                if ($disambig_edit_desc) $disambig_edit_desc .= ", ";
                $disambig_edit_desc .= "[[$dest]]";
            }
        }
        
        // if we're still a disambig, then end the string, and save it.
        if (sizeof($array_of_dests) > 1) {
            
            // add disambig section header, if needed
            if ($disambig_count == 0 || $disambig_count % ITEMS_PER_SECTION == 0 || $disambig_count % MAX_DISAMBIGS_PER_PAGE == 0 ) {
                fwrite ($dfp, "\n\n==$label==\n");
            }
            
            $disambig_contents .= "{{disambig}}\n";
            $disambig_string .= "----\n";
            
            // save to the disambig output file.
            $disambig_edit_desc = "Add disambig to " . $disambig_edit_desc . PROJECT_BLURB;
            fwrite($dfp, easyPreviewString($label, $disambig_contents, $disambig_edit_desc) 
                        . " "
                        . $disambig_string);
            
            // increment counter & start a new page if needed
            $disambig_count += 1;
            if ($disambig_count % MAX_DISAMBIGS_PER_PAGE == 0) {
                fclose ($dfp);
                $dfp = getDisambigFilePointer($disambig_count);
            }
        }
    }
    
    // if this is a redirect
    if (sizeof($array_of_dests) == 1) {
        list ($dest, $count) = array_pop($array_of_dests);
        
        // a redirect must be suggested 5 or times before we will consider it
        if ($count < SUGGESTED_TIMES) continue;
        
        // add redirect section header, if needed                
        if ($redirect_count == 0 || $redirect_count % ITEMS_PER_SECTION == 0 || $redirect_count % MAX_REDIRECTS_PER_PAGE == 0 ) {
             fwrite ($rfp, "\n\n==$dest==\n");  
        }
        
        // detect whether this looks like a simple pluralisation
        $plural = false;
        if (strtolower($dest) . "s" == strtolower($label)) {
            $plural = true;   
        }
        else if (strtolower($dest) . "es" == strtolower($label)) {
            $plural = true;   
        }
        // companies --> company
        else if (strtolower(substr($dest, 0, -1)) . "ies" == strtolower($label) && strtolower(substr($dest, -1)) == "y") {
            $plural = true;  
            //#print "Plural: $label --> $dest\n";
        }
        // wharves --> Wharf, and continental shelves --> Continental shelf
        else if ( strtolower(substr($dest, 0, -1)) . "ves" == strtolower($label)  && strtolower(substr($dest, -1)) == "f") {
            $plural = true;  
            //#print "Plural: $label --> $dest\n";
        }
        // half-lives --> half-life
        else if ( strtolower(substr($dest, 0, -2)) . "ves" == strtolower($label)  && strtolower(substr($dest, -2)) == "fe") {
            $plural = true;  
            //#print "Plural: $label --> $dest\n";
        }
        // theses --> thesis, and synopses --> synopsis, and semi-major axes --> semi-major axis
        else if ( strtolower(substr($dest, 0, -2)) . "es" == strtolower($label)  && strtolower(substr($dest, -2)) == "is") {
            $plural = true;  
            //#print "Plural: $label --> $dest\n";
        }
/*      // have commented out as these two forms are very rare, and are arguably incorrect anyway.
        // virii --> virus
        else if ( strtolower(substr($dest, 0, -2)) . "ii" == strtolower($label)  && strtolower(substr($dest, -2)) == "us") {
            $plural = true;  
           //# print "Plural: $label --> $dest\n";
        }
        // cacti --> cactus
        else if ( strtolower(substr($dest, 0, -1)) . "i" == strtolower($label)  && strtolower(substr($dest, -2)) == "us") {
            $plural = true;  
            print "Plural: $label --> $dest\n";
        }        
*/
        
        //# Debugging:
        //#if ($plural) print "Plural: $label --> $dest\n";
        
        // write this to the redirect file.
        fwrite ($rfp, "* " . easyPreviewString($label, "#REDIRECT [[$dest]]" . ($plural ? " {{R from plural}}" : ""), "Add redirect to [[$dest]]" . PROJECT_BLURB) . " [[$label]] &rarr; [[$dest]]  $count\n");
        
        // increment counter & start a new page if needed
        $redirect_count += 1;
        if ($redirect_count % MAX_REDIRECTS_PER_PAGE == 0) {
            fclose ($rfp);
            $rfp = getRedirectFilePointer($redirect_count);
        }
    }
}
        
// close both file pointers.
fclose($rfp);
fclose($dfp);

print "Number of redirects: $redirect_count\n";
print "Number of disambigs: $disambig_count\n";
    
?>