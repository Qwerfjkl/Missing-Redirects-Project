#!/usr/bin/php -q
<?php

/*
** @desc: return the suggested change string
*/  
function getSuggestedChange ($string, $start_pos, $end_pos, $substring, $str_strlen, $source_link, $section) {
    global $already_suggested;
    
    // don't suggest changes more than once; store suggested changes.
    if (isset($already_suggested[strtolower(standardLinkFormatting($substring))])) {
        return "";
    }
    
    // only print out good links.
    if (!isGoodLink($substring)) {
        return "";
    }
    
    $link_text = getLinkText($substring);
    
    // try to get rid of situations where the substring is all caps (e.g. ALABAMA), but the link text 
    // is the same word, but it not all uppercase (e.g. "Alabama").
    if (strtoupper($substring) == $substring && strtoupper($link_text) == $substring && $link_text !== $substring) {
        return "";
    }
        
        
    
    $retval =  "* Can link '''$substring''': ";
    
    $before_text = "";
    if ($start_pos > PRINTOUT_CONTEXT_CHARS) {
        $before_text .= "...";
    }
    
    $start_place = max(0, $start_pos-PRINTOUT_CONTEXT_CHARS);
    $before_text .= neuterWikiString(substr($string, $start_place, $start_pos - $start_place));
    
    $retval .= $before_text;
    
    // Need to do this to take care of redirects (where link_text != substring):
    if (isset($already_suggested[strtolower(standardLinkFormatting($link_text))])) {
        return "";
    }
    
    // now store that this has been visited:
    $already_suggested[strtolower(standardLinkFormatting($link_text))] = 1;
    $already_suggested[strtolower(standardLinkFormatting($substring))] = 1;

    if (ucfirst($link_text) === ucfirst($substring)) {
        $retval .= "[[$substring]]";
    }
    else {
        $retval .= "[[$link_text|$substring]]";
    }

    // take account of newlines after suggested replacement
    $space_pos = strpos($string,"\n",$end_pos);
    if ($space_pos === false) {
        $space_pos = PRINTOUT_CONTEXT_CHARS;
    }
    else {
        $space_pos -= $end_pos;
    }
    
    $after_text = neuterWikiString(substr($string, $end_pos, min($space_pos, PRINTOUT_CONTEXT_CHARS, $str_strlen - $end_pos) ) );
    if ($end_pos + PRINTOUT_CONTEXT_CHARS < $str_strlen) {
        $after_text .= "...";
    }
    
    $retval .= $after_text;
    $retval .= "\n"; 

    // Need to check that this link has not already been suggested for this article before saving it
    if (!linkHasBeenSuggestedBefore(addslashes($source_link), addslashes($link_text))) {
        // Now save it to the database:
        dbSaveSuggestedLink($source_link, $before_text, $link_text, $substring, $after_text, $section);
    }
    
    return $retval;
}


/*
** @desc: finds and returns the existing links in the article.
*/
function findExistingLinks($string, $page_title) {
    global $redirect_candidates;
    
    // reset redirect_candidates
    $redirect_candidates = array();
    
    // first convert "[[ecumenical council]]s" into "[[ecumenical council|ecumenical councils]]"
    $string = preg_replace("/\[\[([A-Za-z _'\(\)]+)\]\]([A-Za-z]+)/", "[[\\1|\\1\\2]]", $string);
    
    $matches = preg_split("((\[\[)|(\]\])|(\|))", $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $retval = array();
    $bracket_count = 0;
    $dest = "";
    $label = "";
    $save_next_link = false;
    foreach ($matches as $val) {
        // if closing wiki link with |
        if ($bracket_count>0 && $val == "|") {
            $bracket_count -= 1;
            $save_next_link = true;
        }
        // if closing wiki link with ]]
        else if ($bracket_count>0 && $val == "]]") {
            $bracket_count -= 1;
            $save_next_link = false;
        }
        // if opening wiki link
        else if ($val == "[[") {
            $bracket_count += 1;
            $save_next_link = false;
        }
        // if this is the wiki link
        else if ($bracket_count>0) {
            $dest = standardLinkFormatting(trim($val));
            $retval[strtolower($dest)] = 1;
            
            // now we also take care of any redirects in the article itself 
            // i.e. store the target of these redirects as a suggested link
            $retval[strtolower(standardLinkFormatting(getLinkText(undoLinkFormatting($dest))))] = 1;
            
        }
        // if the link text is different to the destination article, then save the link text too.
        else if ($save_next_link) {
            $label = standardLinkFormatting(trim($val));
            $retval[strtolower($label)] = 1;
            $save_next_link = false;
            
            testAndMaybeStorePossibleRedirectCandidate($dest, $label);
        }
    }
    
    // we also add the page title an already suggested link, because we don't want any self-redirects.
    $retval[strtolower(standardLinkFormatting($page_title))] = 1;
    
    return $retval;
}
    

/*
** @desc: sees whether this a redirect candidate, and if so stores it.
*/
function testAndMaybeStorePossibleRedirectCandidate($dest, $label) {
    global $redirect_candidates;
    
    // only if running in memory mode
    if (QUERY_MODE != MEMORY_INDEX) {
        return;
    }
    // only if the data is not empty
    if (empty($dest) || empty ($label)) {
        return;
    }
    
    $label = undoLinkFormatting($label);
    
    // only store if this redirect label does not already have an article.
    if (getNumExactMatches($label) > 0) {
        return ;
    }
    
    $dest = undoLinkFormatting($dest);
    
    // need to get the destination, in case the destination is a redirect too (i.e. don't create double-redirects).
    $dest_link = getLinkText($dest);
    
    // check dest_link is not blank before saving (shouldn't be, but best to be safe).
    if (empty($dest_link)) {
        return;
    }
    
    // check we aren't about to suggest a redirect to ourselves
    if (ucfirst($dest_link) === ucfirst($label)) {
        return;
    }
    
    // only if the destination already exists (this check should not be neccessary, but we'll play it safe).
    if (getNumExactMatches($dest_link) < 1) {
        return;
    }
    
    
    global $manually_excluded_pages;
    // if this page ($dest_link or label) was manually excluded, then don't suggest a redirect
    // for it (as it already has a page).
    if (isset($manually_excluded_pages[undoLinkFormatting(strtolower($dest_link))])) {
        return;
    }
    if (isset($manually_excluded_pages[undoLinkFormatting(strtolower($dest))])) {
        return;
    }
    if (isset($manually_excluded_pages[undoLinkFormatting(strtolower($label))])) {
        return;
    }
    
    // if we have already stored this redirect candidate for this page, then don't store it again.
    if (isset($redirect_candidates[$label][$dest_link])) {
        return;
    }
    
    // now we can save
    global $page_title;
    print "saving possible redirect \"$label\" --> \"$dest_link\"\n";
    dbSaveAddRedirectCandidate(addslashes($dest_link), addslashes($label), addslashes($page_title));
    
    $redirect_candidates[$label][$dest_link] = 1;
}


/*
** @desc: returns whether something is a good link or not
*/
function isGoodLink($link_text) {
    
    // in exhaustive mode, we take every link we get.
    if (EXHAUSTIVE_MODE == true) return true;
    
    $link_text = trim($link_text);
    
    // string contains two or more capital letters
    $tmp = array();
    ereg("[A-Z][a-z ]*[A-Z]",$link_text,$tmp);
    if (!empty($tmp)) {
        return true;
    }
    
    // contains one or more spaces (but if only one space, must not start with 'the')
    $num_spaces = substr_count($link_text, " ");
    if ($num_spaces >= 2 || ($num_spaces == 1 && !eregi("^the ", $link_text)) ) {
        return true;
    }
    
    // contains a dash
    if (strpos($link_text, "-") !== false) {
        return true;
    }
    
    // string ends in "ism"
    if (eregi("ism$", $link_text)) {
        return true;
    }
    
    // otherwise assume is not a good link
    return false;
}


/*
** @desc: finds all the index points at which the string can be split
*/
function findAllSplitPoints($string) {
    // cannot use PREG_SPLIT_OFFSET_CAPTURE as it not supported in PHP version <= 4.3.0 
    $words = preg_split("/([\s,;:.\(\)]+)/", $string,  -1, PREG_SPLIT_DELIM_CAPTURE);

    $split_points = array();
    $split_points[] = 0; // add an zero starting index.
    $running_total = 0;
    foreach ($words as $word) {
        $running_total += strlen($word);
        $split_points[] = $running_total; 
    }
    return $split_points;
}

               

/*
** @desc: main top-level function that takes a page title and processes it.
*/
function processPage($page_title, $str = "") { // $allow_reprocessing, , $from_web
    global $already_suggested;
    
    print "-----------------------------\n";
    print "ProcessPage: $page_title\n";

    $page_title_url = standardLinkFormatting($page_title);
    
    // if we were not passed the page contents, then retrieve it now.
    if (empty($str)) {
        
        $got_text = FETCH_METHOD == FROM_WEB
        ? getArticleTextFromWeb($page_title_url, $str)
        : getArticleTextFromDB($page_title_url, $str);
        if (!$got_text) {
            print "Could not get article text - skipping to next.\n";  //marking as done, and
            return;
        }
    }
    
    //# DEBUGGING:    
    //print "$str\n\n";    
    
    $str_strlen = strlen($str);
    
    // skip redirect pages, and mark as done.
    if (strpos($str, "#REDIRECT") !== false) {
        print "Is a redirect page, skipping.\n";
        return;
    }
    
    // skip disambiguation pages, and mark as done.
    if (strpos($str, "{{disambig}}") !== false) {
        print "Is a disambiguation page, skipping.\n";
        return;
    }
    
    
    // find the links that have already been made; Never want to suggest again a link that is already there.
    $already_suggested = findExistingLinks($str, $page_title);
    
    // find all split points in one preprocessing step.
    $split_points = findAllSplitPoints($str);
    
    //#debugging:
    //#print_r ($already_suggested);
    //#return;


    
    // Loop vars
    $formatting_stack = array();
    $next_wordsplit_index = 0;    // where the next word break is (e.g. the next space, or comma)
    $current_index = 0;           // where we currently are (our start position).
    $previous_match_index = 0;    // if we had a previous exact match, where that was.
    $previous_index = 0;          // if we had a previous space position we had to return to, records where that was.
    $output = "";                 // the output that we will eventually display to the user.
    $section = "";                // the document section that we are currently in
    $check_format_index = 0;      // so that we don't repeatedly run formatHandler on the same bit of text
    
    $line_start_pos = 0;          // where this line of text starts
    
    // reset the static vars in the format handler
    formatHandler("", $formatting_stack, true);
    
    while (true) {
    
        $current_end_index = $current_index + 1 + $next_wordsplit_index;
        // if we haven't reached the end of the string yet.
        if (!isset($split_points[$current_end_index])) {
            break;
        }
        
        $string_start_point = $split_points[$current_index];
        $string_end_point = $split_points[$current_end_index];
        
        // get the current string we are considering.
        $current_string = substr($str, $string_start_point, $string_end_point - $string_start_point);
        
        // remember whether was formatting
        $was_formatting = !empty($formatting_stack);

        $done_formatting = false;
        
        // if this line ended
        if (ereg("\n", $current_string)) {
            
            // run the format handler over the text before the newline:
            $line_end_offset = strpos($current_string, "\n");
            $rest_of_line = substr($str, $string_start_point, $line_end_offset);
            formatHandler($rest_of_line, $formatting_stack);

           
            // if we are entering a new section:
            $full_line = substr($str, $line_start_pos, $string_start_point + $line_end_offset - $line_start_pos);
            
            // check the wiki syntax of this line      
            checkLineFormatting($page_title, $full_line, $formatting_stack, $section); 

            // run the format handler over the text after the newline:
            $check_from = $string_start_point + $line_end_offset + 1;
            $next_line = substr($str, $check_from, $string_end_point - $check_from);
            formatHandler($next_line, $formatting_stack);
            $check_format_index = $current_end_index;
            
            $line_start_pos = $string_end_point;
            $current_index = $current_index + 1 + $next_wordsplit_index;
            $next_wordsplit_index = 0;
            $previous_match_index = 0;
            $previous_index = 0;
            continue;
        }



        // if we get a useless common non-format substring, skip to next.
        $trimmed_string = rtrim($current_string); // only trim from the Right-Hand-Side.
        if ($trimmed_string == "" || $trimmed_string == '.' || $trimmed_string == ',' ||  $trimmed_string == ';' || $trimmed_string == ':') {
            $current_index += 1;
            $next_wordsplit_index = 0;
            $previous_match_index = 0;
            $previous_index = 0;
            continue;
        }        
        

        // check the formatting, if we have not already done so
        if (!$done_formatting) {
            if ($check_format_index < $current_end_index) {
                $last_check_point = $split_points[$check_format_index];
                $check_string = substr($str, $last_check_point, $string_end_point - $last_check_point);
                formatHandler($check_string, $formatting_stack);
                $check_format_index = $current_end_index;
            }
        }
        
        // whilst there is or was formatting in play.
        if (!empty($formatting_stack) || $was_formatting) {
            
            // if we had an exact match before, now revert to that match.
            if ($previous_match_index != 0) { // tested
                $current_string = substr($str, $split_points[$current_index], $split_points[$previous_match_index] - $split_points[$current_index]);
                $output .= getSuggestedChange ($str, $string_start_point, $split_points[$previous_match_index], $current_string, $str_strlen, $page_title_url, $section);
                // now advance back to where we were, and continue normally from there
                $current_index = $previous_match_index; // # DO NOT ADD 1 to this.
                $next_wordsplit_index = 0;
                $previous_match_index = 0;
                $previous_index = 0;
                continue;
            }
            // otherwise skip to the next word, and don't record anything.
            else {
                $current_index += 1 + $next_wordsplit_index;
                $next_wordsplit_index = 0;
                $previous_match_index = 0;
                $previous_index = 0;
                continue;
            }
        }
        
        // never link on anything less than 5 characters, 
        // and never link on anything that ends in a space character
        if ($string_end_point - $string_start_point < 5 || ($current_string[$string_end_point-$string_start_point-1] == ' ') ) {
            // we do not alter current_pos, since we have not changed our starting place.
            $next_wordsplit_index += 1;
            // record this word-break position so that we can come back to it later.
            // Note: need special exceptions in case had a previous match index recorded.
            if ($previous_index == 0 && $previous_match_index == 0) $previous_index = $current_index + 1;
            continue;
        }
        
        // find how many partial and full matches we have:
        $partial_matches = getNumPartialMatches($current_string);
        $exact_matches = getNumExactMatches($current_string);

        
        // should never suggest that the page links to itself.
        if (strtolower($current_string) === strtolower($page_title)) {
            $exact_matches = 0;
        }
        
        if ($partial_matches <= 1 && $exact_matches >= 1) {
            // get the suggested change
            $output .= getSuggestedChange ($str, $string_start_point, $string_end_point, $current_string, $str_strlen, $page_title_url, $section);
            $current_index += 1 + $next_wordsplit_index;
            $next_wordsplit_index = 0;
            $previous_match_index = 0;
            $previous_index = 0;
            continue;
        }
        else if ($exact_matches >= 1 && $partial_matches >=1) { // tested
            // record that we had an previous exact match to the end of the current index
            $previous_match_index = $current_index + 1 + $next_wordsplit_index;
            // no change to current_index
            $next_wordsplit_index += 1;
            $previous_index = 0;
            continue;
        }
        else if (($previous_match_index != 0 && $partial_matches >=1) || $partial_matches >=1) { 
            // we had an exact match before, and we may have an exact in the future too.
            // or we may have not had an exact match before, but may get one in the future.
            // no change to previous_match_index
            // no change to current_index
            $next_wordsplit_index += 1;
            $previous_index = 0;
            continue;
        }
        else if ($previous_match_index != 0) { // tested
            // we had an exact match before, now revert to that match.
            $current_string = substr($str, $split_points[$current_index], $split_points[$previous_match_index] - $split_points[$current_index]);
            $output .= getSuggestedChange ($str, $string_start_point, $split_points[$previous_match_index], $current_string, $str_strlen, $page_title_url, $section);
            // now advance back to where we were, and continue normally from there
            $current_index = $previous_match_index + 1;
            $next_wordsplit_index = 0;
            $previous_match_index = 0;
            $previous_index = 0;
            continue;
        }
        else if ($previous_index != 0) {  // tested
            // if have to go back to a previous word break to check for matches that start there.
            // example is "to be" - need to go back and check from "be" onwards.
            $current_index = $previous_index + 1;
            $next_wordsplit_index = 0;
            $previous_match_index = 0;
            $previous_index = 0;  
            continue; 
        }
        else {
            // there is no match, and there never was, so skip to next
            $current_index += 1; // find postion after next space.
            $next_wordsplit_index = 0;
            $previous_match_index = 0;
            $previous_index = 0;  
            continue;
        }
    }
    

    saveFullPageFormattingProblems($formatting_stack, $page_title);

        
    
    // print the suggestions to stdout (makes easier to see what script is doing):
    if ($output != "") {
        print $output ;
    }
    else {
        print "No suggestions found.\n";
    }
    
}


// ------------------------------------------------------------------------

/*
** @desc: initializes and returns an index of the articles - only looks at article
**        titles, not at the articles themselves.
*/
function initMemoryIndexOfArticles() {
    
    // There are two arrays - one for partial matches:
    //      $partial($Partial_lowercase_phrase => num_courrences)
    // And one for exact matches:
    //      $exact($full_lowercase_phrase => array ("Exact mixed-case phrase" => link URL) );
    global $exact_match, $partial_match, $manually_excluded_pages;
    
    $exact_match = array();
    $partial_match = array();
    $manually_excluded_pages = array();
    
    $count = 0;
    
    print "Building index of Articles ...\n";
    
    // get the names of all articles that are NOT redirects
    $res = dbGetAllArticleNamesAndWhetherIsDisambig();

    while (list ($link, $isDisambig) = DB_fetch_array($res)) {
        
        $count += 1;
        // want lowercase
        $mixed_case_title = undoLinkFormatting($link);
        $lowercase_title = strtolower( $mixed_case_title );
       
        
        // Gives an indication of progress.
        if ($count % 10000 == 0) {
            print $count . " : " . $mixed_case_title . "\n";
        }
        
        // if this is a disambiguation page, then mark it as being excluded, and move on to the next article
        if ($isDisambig) {
            $manually_excluded_pages[$lowercase_title] = 1;

            continue;
        }

        $words = preg_split("/([\s,;:.\(\)]+)/", $lowercase_title,  -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // now store this.
        $partial_word = "";
        $could_match = false;
        
        foreach ($words as $part_word) {
            $partial_word .= $part_word;
            
            $strlen = strlen($partial_word);
            
            // skip if last char is a space
            if ($strlen > 1 && $partial_word[$strlen - 1] == ' ') continue;
            
            if (isset($partial_match[$partial_word])) {
                $partial_match[$partial_word] += 1;
            }
            else {
                $partial_match[$partial_word] = 1;
            }
            
            $could_match = true;
        }
        
        // only store exact match if we could ever match this.
        if ($could_match) {
            $exact_match[$lowercase_title][$mixed_case_title] = $link;
        }
    }
    
    
    // do 2 passes - one for finding errors, the other for saving the index
    $redirects = array();
    
    for ($i=0; $i<2; $i++) {
        
        $first_pass = ($i == 0);
        $second_pass = ($i == 1);
                
        print "Building index of Redirects ... " . ($first_pass? "Building temporary list of redirects" : "Storing errors and adding to main index") . "\n";
        
        // for every redirect
        $res = dbGetAllRedirectNamesAndContents();
        while (list ($redirect_name, $contents) = DB_fetch_array($res)) {
            
            if ($i==0) $count += 1;
            // want lowercase
            $mixed_case_title = undoLinkFormatting($redirect_name);
            $lowercase_title = strtolower( $mixed_case_title );
            
            // For redirects, we want the real link (i.e. the redirect target).
            
            
            $matches = array();
            $num_matches = preg_match("/^\#REDIRECT[ TO]?:? *\[\[(.*)\]\]/i", $contents, $matches);
            if ($num_matches == 0) {
                if ($second_pass) {
                     dbSaveMalformedPage(addslashes($redirect_name), addslashes($redirect_name), "Bad REDIRECT syntax", "");
                }
                //trigger_error("Bad redirect syntax: $link");
                continue;
            }
            
            // first match is the whole string, the second is the link
            array_shift($matches);
            $link = unhtmlentities( urldecode(undoLinkFormatting(trim(array_shift($matches)))));
            
            // if the link has a '|', remove from there on.
            if (strpos($link, "|") !== false) {
                $link = substr($link,0,strpos($link, "|"));
            }
            

            // check the redirect target exists, unless it has a # or a :, in which case we assume the authors knew what they were doing.
            if (!getNumExactMatches($link) && strpos($link, "#") === false && strpos($link, ":") === false) {
                // if this is the second pass, and this target was not manually excluded previously (e.g. as a disambig page).
                if ($second_pass && !isset($manually_excluded_pages[strtolower($link)])) {
                    if (isset($redirects[strtolower($link)])) {
                        dbSaveMalformedPage(addslashes($redirect_name), addslashes($redirect_name), "Double-redirect", addslashes(neuterWikiString($link))); //, "");
                    }
                    else {
                        dbSaveMalformedPage(addslashes($redirect_name), addslashes($redirect_name), "Target of Redirect does not exist", addslashes(neuterWikiString($link))); //, "");
                    }
                    continue;
                }
                else if ($second_pass && isset($manually_excluded_pages[strtolower($link)])) {
                    // if this a redirect to an exlcuded page (such as a disambiguation page), then exclude this page too
                    $manually_excluded_pages[$lowercase_title] = 1;
                    continue;
                }
            }
            
            // now the rest as per the normal article case above.
            $words = preg_split("/([\s,;:.\(\)]+)/", $lowercase_title,  -1, PREG_SPLIT_DELIM_CAPTURE);
            
            // Gives an indication of progress.
            if ($count % 10000 == 0) {
                print $count . " : " . $mixed_case_title . "\n";
            }
            
            // now store this.
            $partial_word = "";
            $could_match = false;
            
            foreach ($words as $part_word) {
                $partial_word .= $part_word;
                
                $strlen = strlen($partial_word);
                
                // skip if last char is a space
                if ($strlen > 1 && $partial_word[$strlen - 1] == ' ') continue;
                
                // store in $partial_match if this is the second pass
                if ($second_pass) {
                    if (isset($partial_match[$partial_word])) {
                        $partial_match[$partial_word] += 1;
                    }
                    else {
                        $partial_match[$partial_word] = 1;
                    }
                }
                
                $could_match = true;
            }
            
            // only store exact match if we could ever match this.
            if ($could_match) {
                if ($first_pass) {
                    $redirects[$lowercase_title] = 1;
                }
                else if ($second_pass) {
                    $exact_match[$lowercase_title][$mixed_case_title] = $link;
                }
            }
        }
    }
    
    // don't need the list of redirects any more
    unset($redirects);
    

    
    print "Removing Bad Links ...\n";
    // remove links to articles we never want to suggest links to:    
    $res = dbResultSetNeverLinkToArticles();
    while (list($mixed_case_title) = DB_fetch_array($res)) {
        $lowercase_title = strtolower( $mixed_case_title );

        // update the partial links
        if (isset($partial_match[$lowercase_title])) {
             if ($partial_match[$lowercase_title] <= 1) {
                 unset($partial_match[$lowercase_title]);
             }
             else {
                 $partial_match[$lowercase_title] -= 1;
             }
        }
        
        unset($exact_match[$lowercase_title]);
        
        // store that this a manually excluded page
        $manually_excluded_pages[$lowercase_title] = 1;
    }

    print "Completed creating memory index ...\n";
}


/*
** @desc: returns the number of partial matches on the string. Mechamism depends on query mode.
*/
function getNumPartialMatches($string) {
    
	switch (QUERY_MODE) {
		case DATABASE:	   return dbCountNumSubstringMatchesOnName (addslashes(standardLinkFormatting($string)));
		case NO_QUERY:     return 0;
		case MEMORY_INDEX: global $partial_match;
			                 $lowercase = strtolower(undoLinkFormatting($string));
		                   if (!isset($partial_match[$lowercase])) return 0;
		                   return $partial_match[$lowercase];
		default:           trigger_error("undefined query_mode: " . QUERY_MODE);
		                   exit();
	}
	
    return 0;
}


/*
** @desc: returns the number of exact matches on the string. Mechanism depends on query mode.
*/
function getNumExactMatches($string) {
	switch (QUERY_MODE) {
		case DATABASE:	   return dbCountNumExactMatchesOnName (addslashes(standardLinkFormatting($string)));
		case NO_QUERY:     return 0;
		case MEMORY_INDEX: global $exact_match;
			                 $lowercase = strtolower(undoLinkFormatting($string));
		                   if (!isset($exact_match[$lowercase])) return 0;
		                   return count($exact_match[$lowercase]);
		default:           trigger_error("undefined query_mode: " . QUERY_MODE);
		                   exit();
	}
	
	return 0;
}


/*
** @desc: returns the right link text. The memory index case takes care of redirects.
*/
function getLinkText($string) {
	switch (QUERY_MODE) {
	  case DATABASE: return undoLinkFormatting( $string );
		case NO_QUERY: return $string;	    
		case MEMORY_INDEX: global $exact_match;
		                 $lowercase = strtolower($string);
	                   if (!isset($exact_match[$lowercase])) return "";
	                   // if we have an exact match, return that value
	                   if (isset($exact_match[$lowercase][$string])) return undoLinkFormatting($exact_match[$lowercase][$string]);
	                   // else return the first value we get
	                   foreach ($exact_match[$lowercase] as $retval) {
	                       return undoLinkFormatting($retval);
	                   }
	                   break;
		default:       trigger_error("undefined query_mode: " . QUERY_MODE);
		               exit();
	}
	
	return "";	
}	


// #####################################################################
// ##########################    GLOBAL CODE    ########################
// #####################################################################


// include library functions
include_once ("wiki.php");
require_once ("sql-queries.php");

// Include the code for checking wiki syntax.
require_once ("wiki-syntax.php");

// define the markers for when content starts and ends (inside the textarea)
define ("START_CONTENT", "<textarea tabindex='1' accesskey=\",\" name=\"wpTextbox1\"");
define ("END_CONTENT", "</textarea>");

define ("URL_PREFIX", "http://en.wikipedia.org/w/wiki.phtml?title=");
define ("URL_SUFFIX", "&action=edit");

define ("PRINTOUT_CONTEXT_CHARS", 60);

// whether to return every wikilink or not (very rarely useful).
define ("EXHAUSTIVE_MODE", false);

// Define query modes, and the mode we are using:
define ("DATABASE", 1);      // quick to start up, slow to run.
define ("NO_QUERY", 2);      // for testing only, will not find any links, but will check syntax.
define ("MEMORY_INDEX", 3);  // slow to start up, much quicker to run.

// MEMORY_INDEX
define ("QUERY_MODE", MEMORY_INDEX);

// how we want to fetch the article text - from the web or from the database ?
define ("FROM_WEB", 1);
define ("FROM_DB", 2);
define ("FETCH_METHOD", FROM_DB);

// whether debugging is on or not.
define ("DEBUG", false);

    
if (QUERY_MODE == MEMORY_INDEX) {
    initMemoryIndexOfArticles();
}

$total_count = dbCountNumArticles();
Print "Commencing processing of $total_count articles.\n";
$count = 0;
print "Querying for articles and their contents ...\n";
$res = dbGetAllArticleNames();
while (list($page_title) = DB_fetch_array($res)) {
    $count+=1;
    $page_title = undoLinkFormatting($page_title);
    print "Count: $count / $total_count  - page_title: $page_title\n";
    processPage($page_title);
}

// There tend to be around 5 or so self-redirects suggested, due to acute-E's, umlauts, etc
// E.g. where the source article has an acute-E in the title, but has a redirect back to that article just use ASCII characters
// This query catches any such situations that slip through the net.
dbDeleteSuggestedSelfRedirects();

print "Done processing.\n";

?>
