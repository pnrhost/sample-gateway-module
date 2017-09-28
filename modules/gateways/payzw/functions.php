<?php
/* * *******************************************
  Helper Functions
 * ******************************************* */

function ParseMsg($msg) {
    $parts = explode("&", $msg);
    $result = array();
    foreach ($parts as $i => $value) {
        $bits = explode("=", $value, 2);
        $result[$bits[0]] = urldecode($bits[1]);
    }

    return $result;
}

function UrlIfy($fields) {
    $delim = "";
    $fields_string = "";
    foreach ($fields as $key => $value) {
        $fields_string .= $delim . $key . '=' . $value;
        $delim = "&";
    }

    return $fields_string;
}

function CreateHash($values, $MerchantKey) {
    $string = "";
    foreach ($values as $key => $value) {
        if (strtoupper($key) != "HASH") {
            $string .= $value;
        }
    }
    $string .= $MerchantKey;

    $hash = hash("sha512", $string);
    return strtoupper($hash);
}

function CreateMsg($values, $MerchantKey) {
    $fields = array();
    foreach ($values as $key => $value) {
        $fields[$key] = urlencode($value);
    }

    $fields["hash"] = urlencode(CreateHash($values, $MerchantKey));

    $fields_string = UrlIfy($fields);
    return $fields_string;
}

