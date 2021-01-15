<?php

namespace HeatApplied\Util;

class Strings {
    
    public static function rawUrlEncode($string) {
        return str_replace('%5D', ']', str_replace('%5B', '[', rawurlencode(rawurldecode($string))));
    }
    
    public static function makeRegexValueCompatible($string = '') {
        return str_replace('$', '\\$', str_replace('\\$', '$', $string));
    }
    
    public static function makeRegexCompatible($string = '', $wildcard = false) {
        $unescaped = str_replace(['\\.', '\\^', '\\$', '\\(', '\\|', '\\)', '\\?', '\\*', '\\+', '\\{', '\\}', '\\[', '\\]',  '\\/']
                , ['.', '^', '$', '(', '|', ')', '?', '*', '+', '{', '}', '[', ']', '/']
                , $string);
        $escaped = str_replace(['.', '^', '$', '(', '|', ')', '?', '*', '+', '{', '}', '[', ']', '/']
                , ['\\.', '\\^', '\\$', '\\(', '\\|', '\\)', '\\?', '\\*', '\\+', '\\{', '\\}', '\\[', '\\]', '\\/']
                , $unescaped);
        if($wildcard !== false) {
            $escaped = str_replace($wildcard, '.*', $escaped);
        }
        return $string;
    }
    
    public static function startsWith($needle = '', $haystack = '', $ignoreCase = false) {
        return preg_match('/^' . static::makeRegexCompatible($needle) . '.*/' . ($ignoreCase ? 'i' : ''), $haystack) == 1;
    }
    
    public static function endsWith($needle = '', $haystack = '', $ignoreCase = false) {
        return preg_match('/.*' . static::makeRegexCompatible($needle) . '$/' . ($ignoreCase ? 'i' : ''), $haystack) == 1;
    }

    public static function wildcardCompare($needle = '', $haystack = '', $wildcard = '%', $ignoreCase = false) {
        return preg_match('/^' . static::makeRegexCompatible($needle, $wildcard) . '$/' . ($ignoreCase ? 'i' : ''), $haystack) == 1;
    } 
    
}