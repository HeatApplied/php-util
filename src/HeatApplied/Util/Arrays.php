<?php

namespace HeatApplied\Util;

class Arrays {
    
    public static function passByReference(&$arrayWithValues = null) {   
        $arrayWithReferences = []; 
        if($arrayWithValues !== null && is_array($arrayWithValues)) {
            foreach ($arrayWithValues as $key => $value) {
                $arrayWithReferences[$key] = &$arrayWithValues[$key];
            }
        }
        return $arrayWithReferences;
    }

    public static function getAsDataUri($array = [], $format = '%s.%s') {
        $dataUri = '';
        $flattened = self::flatten($array, $format);
        foreach($flattened as $key => $value) {
            $dataUri .= (strlen($dataUri) > 0 ? '&' : '?') . $key . '=' . Strings::rawUrlEncode($value);
        }
        return $dataUri;
    }
    
    public static function flatten($array = [], $format = '%s.%s', $prefix = '', $encode = true) {
        $final = [];
        foreach($array as $key => $value) {
            if(is_array($value) || is_object($value)) {
                $flattened = self::flatten($value, $format, '', $encode);
                foreach($flattened as $fkey => $fvalue) {
                    $subKey = sprintf($format, $key, $fkey);
                    $newKey = $encode ? Strings::rawUrlEncode($subKey) : $subKey;
                    $final[$newKey] = $encode ? Strings::rawUrlEncode($fvalue) : $fvalue;
                }
            }
            else {
                $newKey = $encode ? Strings::rawUrlEncode($key) : $key;
                $final[$newKey] = $encode ? Strings::rawUrlEncode($value) : $value;
            }
        }
        if(!empty($prefix)) {
            $finalWithPrefix = [];
            foreach($final as $key => $value) {
                $newKey = sprintf($format, $prefix, $key);
                $finalWithPrefix[$newKey] = $value;
            }
            $final = $finalWithPrefix;
        }
        return $final;
    }
    
    public static function mergeAndFlatten($array1, $array2, $format = '%s.%s', $prefix = '', $encode = true) {
        return array_merge($array1, self::flatten($array2, $format, $prefix, $encode));
    }
    
}
