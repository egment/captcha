<?php

function deepScandir($dir, $prefix = '')
{
    $files = [];
    if (is_dir($dir)) {
        if ($handle = opendir($dir)) {
            //返回当前文件的条目
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != "..") {
                    if (is_dir($dir . "/" . $file)) {
                        $files[$file] = deepScandir($dir . "/" . $file, $prefix);
                    } else {
                        $files[] = $prefix ? $prefix . '/' . $file : $file;
                    }
                }
            }
            closedir($handle);
            return $files;
        }
    }
}

function shallowScanDir($dir, $prefix = '')
{
    $files = [];
    if (is_dir($dir)) {
        if ($handle = opendir($dir)) {
            //返回当前文件的条目
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != "..") {
                    if (is_dir($dir . "/" . $file)) {
                        continue;
                    } else {
                        $files[] = $prefix ? $prefix . '/' . $file : $file;
                    }
                }
            }
            closedir($handle);
            return $files;
        }
    }
}

function arrayFilter(array $haystack, $compare = [], \Closure $closure = null)
{
    $needed = [];
    if ($closure && is_callable($closure)) {
        foreach ($haystack as $item) {
            $wantItem = $closure($item);
            if (in_array($wantItem, $compare)) {
                $needed[] = $item;
            }
        }
        unset($haystack);
        return $needed;
    } else {
        foreach ($haystack as $item) {
            if (in_array($item, $compare)) {
                $needed[] = $item;
            }
        }
        unset($haystack);
    }
    return $needed;
}

/*
 * 产生随机数
 * @param integer $length
 * @return string
 */
function eg_str_random($length = 6)
{
    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
}
