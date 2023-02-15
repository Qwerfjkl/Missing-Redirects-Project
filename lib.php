<?php

// Purpose:    A project-independent store of useful PHP functions and directives.

// report any errors at all
error_reporting(E_ALL);

// ignore 'STOP' button, any code after this will always run to completion
set_time_limit(0);
ignore_user_abort(true); 

// force the arg separator to be "&amp;", not just "&", which is incorrect under the HTML spec.
ini_set ("arg_separator.output", "&amp;");


/*
** @desc: sends headers to disable caching in many proxies and clients.
*/
function force_no_cache() {
  header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");    	  		// Date in the past
	header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header ("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");  	// HTTP/1.1
	header ("Pragma: no-cache");                          	  		// HTTP/1.0
}




// ------------------ DB operations --------------------------
// all database interactions are only via a set of functions defined in the appropriate RDBMS .php file.
// This makes any potential database migrations significantly easier, such as to Postgresql
// This file should be included in the app's .php file, not here, and DB access should be done using 
// functions defined in that file, never directly or hard-coded to use just one RDBMS


// - DB layer, but RDBMS-independent:

/*
** @desc: make a string safe for insertion in a database and subsequent display back in the browser
*/
function DB_safe_string($str, $addSlashes = true, $addTicks = false) {
	$retval = $addSlashes ? addslashes(htmlentities($str)) : htmlentities($str);
	if ($addTicks) return ("'" . $retval . "'");
	else           return $retval;
}

// ----------------------------------------------------------

/*
** @desc: takes a string with html entities (e.g. "&nbsp;" instead of " "), and converts it back into straight text
**        Superceded by the "html_entity_decode" function, present in PHP >= 4.3.0
*/
function unhtmlentities($str) {
    $trans = get_html_translation_table (HTML_ENTITIES);
    $trans = array_flip ($trans);
    $trans["<br>"] = "\n";     // required, as for some reason this is not part ofthe html_translation_table
    $ret = strtr ($str, $trans);
    return preg_replace('/&#(\d+);/me', "chr('\\1')",$ret);
}


/*
** @desc: starts a form
*/
function form_begin ($target="", $form_name = "mainForm") {
	global $PHP_SELF;
	if (!$target) $target = $PHP_SELF;
	// DEBUGGING URL:

	// this is the best form encoding to use - works for files and allow lots of arguments, beyond the limits of 
	// some other ENCTYPE/METHODs
	return "<form enctype=\"multipart/form-data\" action=\"$target\" method=\"post\" name=\"$form_name\" id=\"$form_name\">";
}


/*
** @desc: returns the HTML code for a button
*/
function add_button ($buttonLabel, $varName) {
	return ("<input type=\"submit\" name=\"$varName\" value=\"".htmlSpecialChars($buttonLabel)."\">");
}


/*
** @desc: returns the HTML code for a text area
*/
function textarea ($name, $contents, $cols=70, $rows=8) {
	return "<textarea name=\"$name\" cols=\"$cols\" rows=\"$rows\">\n$contents</textarea>";
}


/*
** @desc: a class that encapsulates HTML tables, and common operations on them
*/
class table {
	var $inHeader; 
	var $nextCellProperty= "";
	var $tableProperty   = "";
	var $allCellProperty = "";
	var $nextRowProperty = "";

    function beginNewRow() {  // this is a private function, not to be called externally
        print "\n<tr" . ($this->nextRowProperty?" $this->nextRowProperty":"") . ">";
				$this->nextRowProperty = "";
    }

    function begin ($str = "no header row") {
				switch ($str) {
					case "no header row" :	$this->inHeader = false;
																	break;
					case "has header row":	$this->inHeader = true;
																	break;
					default              :	trigger_error("Unknown header case: $str\n");
					                        $this->inHeader = false;
				}
				print "\n<table $this->tableProperty>";
				$this->beginNewRow();	
    }

    function newRow() {
				print "\n</tr>";
				$this->inHeader = false;
				$this->beginNewRow();
    }

    function end($include_blank_line = true) {
				print "\n</tr>";
				print "\n</table>\n";
        if ($include_blank_line) print "<br>\n";
    }

    function setInHeader($val) {
        $this->inHeader = $val;
    }

		function addCell($str = "", $hasError = false) {
				if ($this->inHeader) { 
					$start_tag = "th"; // table header cell
					$end_tag   = "th";
				}
				else {
					$start_tag = "td class=\"visTD\""; // table element cell
					$end_tag   = "td";
				}
				if ($str == "" && $this->nextCellProperty == "" && $this->allCellProperty == "") {
					print "\n<$start_tag>" . blankSpace() . "</$end_tag>";
					return;
				}
				print ("\n<$start_tag");
				if ($this->nextCellProperty != "") print " $this->nextCellProperty";
				if ($this->allCellProperty != "")  print " $this->allCellProperty";
				print (">");
				if ($hasError) print "<div class=\"error\">";	// the "error" style
				if ($str == "") print blankSpace();
				else print ($str);
				if ($hasError) print "</div>";	// the "error" style
				print "</$end_tag>";
				$this->nextCellProperty = "";
    }

    /*
    ** @desc: prints a cell without all the formatting gumph
    */
    function addNonFormattedCell($contents = "", $property = "") {
        $tag = $this->inHeader ? "th" : "td";
        if ($contents == "") $contents = blankSpace();
				print "\n<$tag" . ($property ? " $property" : "") . ">$contents</$tag>";
    }

    function addCellProperty($str) {
				if ($this->nextCellProperty != "") $this->nextCellProperty .= " ";
				$this->nextCellProperty .= $str;
    }

    // CTOR
    function table($tableProperty = "border=\"2\"") { 
	  	 	if ($this->tableProperty != "") $this->tableProperty .= " ";
				$this->tableProperty .= $tableProperty;
    }

    function setAllCellProperty($str) {
				$this->allCellProperty = $str;
    }

    function setNextRowProperty($str) {
				$this->nextRowProperty = $str;
    }
}

?>