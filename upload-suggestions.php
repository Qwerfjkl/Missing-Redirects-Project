#!/usr/bin/php -q
<?php

// include library functions
include_once ("wiki.php");
require_once ("sql-queries.php");



/*
** @desc: saves suggestions to the requested page.
*/
function saveTextToWikipedia($page_type, $page_title, $wpSummary, $text, $new_section = true) {
    
   $url = "http://en.wikipedia.org/w/wiki.phtml?title=" . $page_type . ":" . urlencode(standardLinkFormatting($page_title));
   
   $params = array ("action"      => "submit",
                    "wpMinoredit" => "1",
                    "wpSave"      => "Save page",
                    "wpSection"   => ($new_section ? "new" : ""),
                    "wpEdittime"  => "",
                    "wpSummary"   => $wpSummary,
                    "wpTextbox1"  => $text);
   
   $ch = curl_init();
   
   curl_setopt($ch, CURLOPT_COOKIEFILE, "./linkbot-cookie.txt");   //load cookie values
   curl_setopt($ch, CURLOPT_POST, 1);                    // save form using a POST
   curl_setopt($ch, CURLOPT_POSTFIELDS, $params);        // load the POST variables
   curl_setopt($ch, CURLOPT_URL, $url);                  // set url to post to
   curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);           // return into a variable
   curl_setopt($ch, CURLOPT_COOKIEJAR, "./linkbot-cookie.txt"); // save cookie values

   $result=curl_exec ($ch);
   
   // if we encountered an error, then log it, and exit.
   if (curl_error($ch)) {
        trigger_error("Curl error #: " . curl_errno($ch) . " - " . curl_error ($ch) );
        print "Curl error #: " . curl_errno($ch) . " - " . curl_error ($ch) . " - exiting.\n";
        exit();
   }
   
   curl_close ($ch);
   
   return $result;
}



/*
** @desc: Sleeps for the required amount of time, to give the required transaction rate.
*/
function sleepIfRequired($counter, $start_time, $current_time) {
    $expected_time = ($counter * MIN_SECS_DELAY_UNTIL_NEXT_SAVE) + $start_time;
    
    // if we are ahead of schedule, then sleep.
    if ($current_time < $expected_time) {
        sleep ($expected_time - $current_time);
    }
}


/*
** @desc: returns the body of the suggestion text
*/
function getSuggestionText($page_title, &$num_links, &$num_backlinks) {
    
    // start off with blank suggestions
    $suggestions = "";
    
    // initially have no links
    $num_links = 0;
    $num_backlinks = 0;
    
    // first get links from this page.
    $res = dbResultSetGetArticleSuggestions(standardLinkFormatting($page_title));
    while (list( , $before_text, $d_link, $dest_label, $after_text, $section) = DB_fetch_array($res)) {
        
        // if this the first outward link
        if ($num_links == 0) {
            $suggestions .= "===Outward links===\n";
        }
        
        $section = getSectionLink($section);
        $suggestions .= "* Can link '''$dest_label''': <nowiki>" . $before_text . "</nowiki> ";
        if (ucfirst($d_link) === urlencode(ucfirst($dest_label))) {
            $suggestions .= "[[$dest_label]]";
        }
        else {
            $suggestions .= "[[$d_link|$dest_label]]";
        }
        $suggestions .= "<nowiki>" . $after_text . "</nowiki>" . ($section ? " ([[" . urlencode($page_title) . $section . "|link to section]])" : "") . "\n";
        $num_links += 1;
    }
    
    // if there are no suggestions, then return a blank string.
    if ($suggestions == "") {
        return $suggestions;
    }
    
    $first_to = true;
    // then get links to this page.
    $res = dbResultSetGetLinksToPage(undoLinkFormatting($page_title));
    while (list($source_link, $before_text, $d_link, $dest_label, $after_text, $section) = DB_fetch_array($res)) {
        
        $section = getSectionLink($section);
                
        if ($first_to) {
            $suggestions .= "\n\n===Inward links===\n";
            $suggestions .= "Additionally, there are some other articles which may be able to linked to this one (also known as \"backlinks\"):\n";
            $first_to = false;
        }
        
        $suggestions .= "* In [[" . undoLinkFormatting(urlencode($source_link)) . $section . "|" . undoLinkFormatting($source_link) . "]], can backlink '''$dest_label''': <nowiki>" . $before_text . "</nowiki> ";
        if (ucfirst($d_link) === urlencode(ucfirst($dest_label))) {
            $suggestions .= "[[$dest_label]]";
        }
        else {
            $suggestions .= "[[$d_link|$dest_label]]";
        }
        $suggestions .= "<nowiki>" . $after_text . "</nowiki>\n"; 
        $num_backlinks += 1;
    }
    
    
    return ($suggestions);
}


/*
** @desc: Logs the LinkBot in to the Wikipedia.
*/
function loginLinkBot() {
   $url = "http://en.wikipedia.org/w/wiki.phtml?title=Special:Userlogin";
   
   $params = array ("action"         => "submit",
                    "wpName"         => "LinkBot",
                    "wpLoginattempt" => "Log in",
                    "wpPassword"     => "XXXX",  // insert the real password here.
                    "wpRemember"     => "1");
   

   $ch = curl_init();
   
   curl_setopt($ch, CURLOPT_POST, 1);                    // save form using a POST
   curl_setopt($ch, CURLOPT_POSTFIELDS, $params);        // load the POST variables
   curl_setopt($ch, CURLOPT_URL, $url);                  // set url to post to
   curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);           // return into a variable
   curl_setopt($ch, CURLOPT_TIMEOUT, 30);                // times out after 30 seconds
   curl_setopt($ch, CURLOPT_COOKIEJAR, "./linkbot-cookie.txt"); // save cookie values

   $result=curl_exec ($ch);
   
   // if we encountered an error, then log it, and exit.
   if (curl_error($ch)) {
        trigger_error("Curl error #: " . curl_errno($ch) . " - " . curl_error ($ch) );
        exit();
   }
   
   curl_close ($ch);
   
   return $result;
}


// #####################################################################
// ##########################    GLOBAL CODE    ########################
// #####################################################################


// login the linkbot to the wikipedia.
if (!loginLinkBot()) {
    print "Could not log in.\n";   
    exit();
}

// the delay until the next save
define("MIN_SECS_DELAY_UNTIL_NEXT_SAVE", 9);

// whether running in test mode or not
define("TEST", false);

// upper limit on the number of pages to upload suggestions to.
define("NUM_PAGES", 95);

// loop vars
$start_time = mktime();
$counter = 0;

// get articles
$res = dbGetAllArticleNames();
while (list($page_title) = DB_fetch_array($res)) {

    if ($counter>=NUM_PAGES) break;
    
    // if we doing a real run, and we have already done this article, then skip to next.
    if (!TEST) {
        if (dbCountHaveUploadedArticleSuggestions(addslashes($page_title))) {
            continue;
        }
    }
   
    $suggestions = getSuggestionText($page_title, $num_links, $num_backlinks);
    
    // if no suggestions, then skip to next.
    if ($suggestions == "") {
        print "No suggestions for: $page_title\n";
        continue;
    }
    
    $counter+=1;
    
    $timestring = date("D jS of F Y h:i:s A");
    print "$counter - Saving suggestions for: $page_title  - " . $timestring . " - Num Links: $num_links ; Backlinks: $num_backlinks\n";
    
    
    $suggestions  = "An [[User:Nickj/Link_Suggester|automated Wikipedia link suggester]] has suggested [[#Outward links|$num_links possible wiki link" . ($num_links>1 ? "s" : "") . "]]" . ($num_backlinks ? " and [[#Inward links|$num_backlinks possible backlink" . ($num_backlinks>1 ? "s" : "") . "]]" : "") . " for the [[$page_title]] article:\n\n"
                   . "{{User:LinkBot/notes}}\n"
                   . "{{User:LinkBot/feedback}} &mdash; ~~~~\n\n\n"
                   . $suggestions;


    $talk_page_note = "An [[User:LinkBot|automated Wikipedia link suggester]] has some possible wiki link suggestions for the [[$page_title]] article, and they have been placed in [[User:LinkBot/suggestions/$page_title|a handy list]] for your convenience."
                      . " &mdash; ~~~~";


    // Save the suggestions to the Wikipedia

    // if testing, put on my talk page
    if (TEST) {
        saveTextToWikipedia("User", "Nickj/sandbox", "Testing saving suggestions", $suggestions, false);
        saveTextToWikipedia("User_talk", "Nickj/sandbox", "Testing saving talk page note", $talk_page_note);
        exit();
    }
    // if this is the real deal
    else {
        saveTextToWikipedia("User", "LinkBot/suggestions/$page_title", "[[User:LinkBot/suggestions/$page_title|Link suggestions]]", $suggestions, false);
        saveTextToWikipedia("Talk", $page_title, "[[User:LinkBot/suggestions/$page_title|Link suggestions]]", $talk_page_note);
        
        dbSaveAddUploadedArticleSuggestions(addslashes($page_title));
    
        dbSaveEditHaveUploadedArticleSuggestionsFrom(addslashes(standardLinkFormatting($page_title)));
        dbSaveEditHaveUploadedArticleSuggestionsTo(addslashes($page_title));
    }
  
    // sleep if needed
    $current_time = mktime();
    sleepIfRequired($counter, $start_time, $current_time);
}

?>
