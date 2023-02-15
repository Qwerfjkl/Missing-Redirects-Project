<?php


////////////////////////  never_link_to table ////////////////////////////

/*
** @desc: records that a page has been visited into the database.
*/
function dbSaveNeverLinkTo($name) {
    safeQuery ("INSERT INTO never_link_to (name) VALUES ('$name')");
}

/*
** @desc: returns a result set of the articles that we should never link to
*/
function dbResultSetNeverLinkToArticles()  {
    return safeQuery("SELECT name
    				  FROM   never_link_to");
}

////////////////////////  malformed_page table ////////////////////////////

/*
** @desc: saves a new malformed_page entry.
*/
function dbSaveMalformedPage($page_link, $page_title, $format, $context, $section = "") {
    safeQuery ("INSERT INTO malformed_page (page_link,page_title,format,context,section) VALUES
               ('$page_link','$page_title','$format','$context','$section')");
}


////////////////////////  suggested_link table ////////////////////////////


/*
** @desc: saves a new suggested_link entry.
*/
function dbSaveSuggestedLink($source_link, $before_text, $dest_link, $dest_label, $after_text, $section = "") {
    // add slashes to all args
    $source_link = addslashes($source_link);
    $before_text = addslashes($before_text);
    $dest_link   = addslashes($dest_link);
    $dest_label  = addslashes($dest_label);
    $after_text  = addslashes($after_text);
    $section     = addslashes($section);
    
    // now save
    safeQuery ("REPLACE INTO suggested_link (source_link,before_text,dest_link,dest_label,after_text,found,section) VALUES
               ('$source_link','$before_text','$dest_link','$dest_label','$after_text',now(),'$section')");
}


/*
** @desc: returns whether or not this link has been suggested before
*/
function linkHasBeenSuggestedBefore($source_link, $dest_link) {
    return safeCountQuery ("SELECT count(*)
                            FROM   suggested_link 
                            WHERE  source_link = '$source_link' AND 
                                   dest_link = '$dest_link'");
}

/*
** @desc: returns the most suggested links above a cutoff point
*/
function dbResultSetMostSuggestedLinks($cutoff) {
    return safeQuery ("SELECT   dest_link, 
                                COUNT(*) AS count
                       FROM     suggested_link
                       GROUP BY dest_link
                       ORDER BY count DESC
                       LIMIT    $cutoff");
}


/*
** @desc: returns the suggestions that link to a page, up a certain limit.
*/
function dbResultSetGetSuggestedLinks($dest_link, $limit) {
    return safeQuery ("SELECT   source_link, 
                                before_text,
                                dest_link,
                                dest_label,
                                after_text
                       FROM     suggested_link
                       WHERE    dest_link = '$dest_link'
                       LIMIT    $limit");
}

/*
** @desc: returns all the suggestions that link from a page (i.e. the suggestions for that page).
*/
function dbResultSetGetArticleSuggestions($article) {
    return safeQuery ("SELECT   source_link, 
                                before_text,
                                dest_link,
                                dest_label,
                                after_text,
                                section
                       FROM     suggested_link
                       WHERE    source_link = '$article' AND
                                save_to_source_time = '00000000000000'
                       ORDER BY suggested_link_id");
}


/*
** @desc: returns all the suggestions that link TO a page.
*/
function dbResultSetGetLinksToPage($article) {
    return safeQuery ("SELECT   source_link, 
                                before_text,
                                dest_link,
                                dest_label,
                                after_text,
                                section
                       FROM     suggested_link
                       WHERE    dest_link = '$article' AND
                                save_to_dest_time = '00000000000000'
                       ORDER BY suggested_link_id");
}


/*
** @desc: deletes the suggested links to a page.
*/
function dbDeleteSuggestedLinksToPage($page_link) {
    safeQuery ("DELETE from suggested_link 
                WHERE  dest_link = '$page_link'");
}


/*
** @desc: deletes the suggested self-redirects.
*/
function dbDeleteSuggestedSelfRedirects() {
    safeQuery ("DELETE from suggested_link 
                WHERE  source_link = REPLACE(dest_link,' ','_')");
}
    

/*
** @desc: Records when the suggestions for links from this article were saved.
*/
function dbSaveEditHaveUploadedArticleSuggestionsFrom($page_link) {
    safeQuery ("UPDATE suggested_link 
                SET    found = found,
                       save_to_source_time = now()
                WHERE  source_link = '$page_link'");
}


/*
** @desc: Records when the suggestions for links to this article were saved.
*/
function dbSaveEditHaveUploadedArticleSuggestionsTo($page_link) {
    safeQuery ("UPDATE suggested_link 
                SET    found = found,
                       save_to_dest_time = now()
                WHERE  dest_link = '$page_link'");
}


/*
** @desc: Returns the different bits of text that point or redirect to the specified page title.
*/
function dbGetAllLabelsForDestLink($page_title) {
    return safeQuery("SELECT DISTINCT(dest_label)
                      FROM   suggested_link
                      WHERE  dest_link = '$page_title'");
}



///////////////////////      cur table          //////////////////////////////

/*
** @desc: returns the contents of the requested page.
*/
function dbGetCurContents($title) {
    return safeCountQuery ("SELECT cur_text
                            FROM   cur
                            WHERE  cur_title = '$title' AND
                                   cur_namespace = '0'");
}


/*
** @desc: returns the number of article titles starting with this string
*/
function dbCountNumSubstringMatchesOnName ($name) {
    return safeCountQuery("SELECT count(*)
						   FROM   cur
						   WHERE  cur_title LIKE '$name%' AND
                                  cur_namespace = '0'");
}

/*
** @desc: returns the number of exact article title matches
*/
function dbCountNumExactMatchesOnName ($name) {
    return safeCountQuery("SELECT count(*)
    					   FROM   cur
    					   WHERE  cur_title = '$name' AND
                                  cur_namespace = '0'");
}


/*
** @desc: returns a result set of all the article names.
*/
function dbGetAllArticleNames() {
    return safeQuery ("SELECT cur_title
				       FROM   cur
					   WHERE  cur_namespace = '0' AND
                              cur_is_redirect = '0'");
}


/*
** @desc: returns a result set of all the article names and whether is disambig or not.
*/
function dbGetAllArticleNamesAndWhetherIsDisambig() {
    return safeQuery ("SELECT cur_title,
                              cur_text LIKE '%{{disambig}}%'
				       FROM   cur
					   WHERE  cur_namespace = '0' AND
                              cur_is_redirect = '0'");
}


/*
** @desc: returns a result set of all the article names and their contents.
*/
function dbGetAllArticleNamesAndContents() {
    return safeQuery ("SELECT cur_title,
                              cur_text
				       FROM   cur
					   WHERE  cur_namespace = '0' AND
                              cur_is_redirect = '0'
                       ORDER BY cur_title");
}


/*
** @desc: returns a result set of the titles of disambiguation pages.
*/
function dbResultSetDisambiguationPageTitles(){
    return safeQuery ("SELECT cur_title
                       FROM   cur
                       WHERE  cur_namespace = '0' AND
                              cur_is_redirect = '0' AND 
                              cur_text LIKE '%{{disambig}}%'");
}


/*
** @desc: returns the title & text of all redirects.
*/
function dbGetAllRedirectNamesAndContents(){
    return safeQuery ("SELECT cur_title,
                              cur_text
                       FROM   cur
                       WHERE  cur_namespace = '0' AND
                              cur_is_redirect = '1'");
}


/*
** @desc: returns the total number of articles that we need to suggest links for.
*/
function dbCountNumArticles() {
    return safeCountQuery ("SELECT COUNT(*)
                            FROM   cur
                            WHERE  cur_namespace = '0' AND
                                   cur_is_redirect = '0'");
}


//////////////////////////// error_log table ////////////////////////////

/*
** @desc: saves a new entry into the error_log
*/
function dbSaveAddErrorLog ($page, $page_title_string, $type, $message, $file, $line ) {
    safeQuery ("INSERT INTO error_log (script, article_title, type, message, file, line) VALUES
                ('$page',$page_title_string,'$type','$message','$file','$line')");
}


//////////////////////////// redirect_candidate table ////////////////////////////


/*
** @desc: saves a redirect candidate.
*/
function dbSaveAddRedirectCandidate($dest, $label, $found_on_page) {
    safeQuery ("INSERT INTO redirect_candidate (dest, label, found_on_page) VALUES
                ('$dest','$label','$found_on_page')");
}


////////////////////////// uploaded_article_suggestions table ///////////////////

/*
** @desc: saves that the article suggestions have been uploaded.
*/
function dbSaveAddUploadedArticleSuggestions($article) {
    safeQuery ("INSERT INTO uploaded_article_suggestions (article) VALUES
                ('$article')");
}


/*
** @desc: returns whether the article suggestions have already been uploaded.
*/
function dbCountHaveUploadedArticleSuggestions($article) {
    return safeCountQuery("SELECT count(*) 
                           FROM   uploaded_article_suggestions
                           WHERE  article = '$article'");
}
    

?>