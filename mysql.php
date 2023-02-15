<?php

// The MySQL DB layer

define ("RDBMS", "MySQL");

// Connect to the database server using the specified parameters
function DB_connect ($database_name, $user_name, $passwd, $host = "localhost") {
    $link = @mysql_pconnect ($host, $user_name, $passwd);
    if ($link && mysql_select_db ($database_name,$link))
        return ($link);
    return (FALSE);
}


function safeQuery($sqlQuery) {
    $result = mysql_query($sqlQuery);
    if ($result === false) {   // the query failed for some reason
        $error = "MySQL Query failed: $sqlQuery ; Error was: " . mysql_error();
        user_error ($error);
    }
    else return $result;
}


function safeCountQuery($sqlQuery) {
    $result = mysql_fetch_row(safeQuery($sqlQuery));
    if (!isset($result)) return 0;
    return ($result[0]);
}


function DB_fetch_array($DBresult) {
    return mysql_fetch_array($DBresult);
}


// the number of affected rows from the __last__ operation (the DBresult parameter is there to create a compatible
// interface with the postgresql commands, but is not used)
function DB_affected_rows() {
    return mysql_affected_rows();
}


function DB_get_new_row_ID($id_field_name) {
    $retval = safeCountQuery ("select last_insert_id()");
    if ($retval == 0) {
        user_error ("Unexpected zero identifier value in DB_new_row_ID($id_field_name), MySQL version");
    }
    return $retval;
}


function format_raw_DB_timestamp( $timestamp ) {
    return safeCountQuery ("select concat(date_format(left($timestamp,8),'%W %D %M %Y'),', ',time_format(right($timestamp,6),'%H:%i:%s'))");
}


function DB_fetch_row($DBresult) {
    return mysql_fetch_row($DBresult);
}


function DB_field_name($DBresult, $field_index) {
    return mysql_field_name($DBresult, $field_index);
}

?>