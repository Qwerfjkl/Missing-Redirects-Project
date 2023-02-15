<?php

/*
** @desc: starts HTML document
*/
function html_start($title = "") {
    // Build the HTML header of the page
    print ("<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n");
    // Fixed IE6 rendering bug by uncommenting this from the DOCTYPE: \"http://www.w3.org/TR/REC-html40/loose.dtd\">\n");
    print ("<html lang=\"en\">\n");
    
    print ("<head>\n");
    print ("<title>$title</title>\n");
    print ("</head>\n");
    
    // Build the header strip that the user sees at the top of the page.
    // Non HTML-standards-compliant MARGINWIDTH & MARGINHEIGHT tags required for Netscape 4.x
    // Unfortunately there does not seem to be CSS standards-compliant way to achieve this in NN4.
    print ("<body marginwidth=\"0\" marginheight=\"0\">\n");
}


/*
** @desc: ends an HTML document
*/
function html_end() {
    print "\n</body>";
    print "\n</html>";
    print "\n";
}


/*
** @desc: end the form
*/
function form_end() {
    return "</form>\n";
}

/*
** @desc: function to show the most common suggestions. 
*/
function showTableOfMostCommonSuggestions() {
    global $PHP_SELF;
    
    // only look at links suggested this many times or more:
    define ("NUM_SUGGESTIONS", 220);
    
    
    $first = true;
    $res = dbResultSetMostSuggestedLinks(NUM_SUGGESTIONS);
    while (list($dest_link, $count) = DB_fetch_array($res)) {
        
        // start the table if needed
        if ($first) {
            print form_begin();
            
            $table = new table();
            $table->begin("has header row");
            $table->addCell("Linked Page");
            $table->addCell("Num Links");
            $table->addCell("Delete");
            $table->addCell("Context of Suggestions");
            $first = false;
        }
        
        $table->newRow();
        
        // display the name of the class
        $table->addCell(getHrefToWikipedia($dest_link));
        $table->addCell($count);
        $table->addCell(checkBox (0, "delete[$dest_link]", 1) . " Delete");
        $table->addCell("<a href=\"$PHP_SELF?suggestions_for=". urlencode($dest_link) . "&limit=40\" target=\"_blank\">Show Suggestions</a>");
    }
    
    if (!$first) {
        $table->end();
        
        print "<p>Names of articles to delete (separate with newlines):</p>\n";
        print textarea("article_names", "");
        
        print "<br>\n";
        
        print add_button ("Remove marked suggestions", "save");
        
        print form_end();
    }
    else {
        print "No suggestions found.\n";
    }
}


/*
** @desc: returns an HREF string into the wikipedia, in a new window.
*/
function getHrefToWikipedia($link, $label = "") {
    if ($label == "") $label = undoLinkFormatting($link);
    return "<a href=\"http://en.wikipedia.org/w/wiki.phtml?title=$link\" target=\"_blank\">" . $label . "</a>";
}
    

function showSuggestionsFor($dest_link, $limit) {
    global $PHP_SELF;
    
    $first = true;
    $res = dbResultSetGetSuggestedLinks($dest_link, $limit);
    while (list($source_link, $before_text, $d_link, $dest_label, $after_text) = DB_fetch_array($res)) {
        
        // start the table if first time
        if ($first) {
            
            $table = new table();
            $table->begin("has header row");
            $table->addCell("For Page");
            $table->addCell("Context");
            $table->addCell("Show page suggestions");
            $first = false;
        }
        
        $table->newRow();
        $table->addCell(getHrefToWikipedia($source_link));
        $table->addCell($before_text . " " . getHrefToWikipedia($d_link, $dest_label) . $after_text);
        $table->addCell("<a href=\"$PHP_SELF?article_suggestions=$source_link\" target=\"_blank\">View page's suggestions.</a>");
    }
    
    
    if (!$first) {
        $table->end();
    }
    else {
        print "No were suggestions found for '$dest_link'.\n";
    }
}


/*
** @desc: marks the specified pages as ones that should never be suggested.
*/
function markAsNeverSuggest($pages) {
    foreach (array_keys($pages) as $page_link) {
        
        $page_link = addslashes($page_link);
        $page_title = undoLinkFormatting($page_link);
        
        $res = dbGetAllLabelsForDestLink($page_title);
        while (list($label) = DB_fetch_array($res)) {
            dbSaveNeverLinkTo( addslashes($label) );
        }
        
        dbDeleteSuggestedLinksToPage($page_link);
    }
}




function neverSuggestNamedArticles($article_names) {
    $article_array = split("\n", $article_names);
    
    foreach ($article_array as $title) {
        $title = trim ($title);
        if (empty ($title)) continue;

        $page_title = undoLinkFormatting($title);
        
        print "Page_title: $page_title<br>\n";
        $page_link = standardLinkFormatting($page_title);
        
        $first = true;
        $res = dbGetAllLabelsForDestLink($page_title);
        while (list($label) = DB_fetch_array($res)) {
            print "- deleting links to: $label<br>\n";
            dbSaveNeverLinkTo( addslashes($label) );
            $first = false;
        }
        
        if ($first) {
             dbSaveNeverLinkTo( $title );  
        }
        
        dbDeleteSuggestedLinksToPage($page_title);
    }
}


/*
** @desc: shows all the suggestions for an article.
*/
function showArticleSuggestions($article) {
    
    print "<h2>Suggestions for page: " . getHrefToWikipedia($article) . "</h2>\n";
    
    $first = true;
    $res = dbResultSetGetArticleSuggestions($article);
    while (list($source_link, $before_text, $d_link, $dest_label, $after_text, ) = DB_fetch_array($res)) {
        
        // start the table if first time
        if ($first) {
            
            $table = new table();
            $table->begin("has header row");
            $table->addCell("For Page");
            $table->addCell("Context");
            $first = false;
        }
        
        $table->newRow();
        $table->addCell(getHrefToWikipedia($source_link));
        $table->addCell($before_text . " " . getHrefToWikipedia($d_link, $dest_label) . $after_text);
    }
    
    
    if (!$first) {
        $table->end();
    }
    else {
        print "No were suggestions found for this page.\n";
    }
}


/*
** @desc: main top-level function.
*/
function main() {
    global $delete, $save, $suggestions_for, $limit, $article_suggestions, $article_names;
    
    html_start("Most Common Suggestions");
    
    if (isset($delete) && isset($save)) {
        markAsNeverSuggest($delete);    
    }
    
    if (isset($article_names) && isset($save)) {
        neverSuggestNamedArticles($article_names);
    }
    
    if (isset($suggestions_for) && isset($limit)) {
        showSuggestionsFor($suggestions_for, $limit);
    }
    else if (isset($article_suggestions)) {
        showArticleSuggestions($article_suggestions);   
    }
    else {
        showTableOfMostCommonSuggestions();
    }
    
    html_end();
}

// #####################################################################
// ##########################    GLOBAL CODE    ########################
// #####################################################################


// include library functions
include_once ("wiki.php");
require_once ("sql-queries.php");

main();

?>