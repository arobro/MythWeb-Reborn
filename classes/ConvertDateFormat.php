<?php
/**
 * Convert legacy strftime() format strings to standard PHP date() format strings.
 */
class ConvertDateFormat {

    public static function convert($format, $default = 'Y-m-d H:i') {
        if (empty($format)) {
            return $default;
        }

        $map = [
            '%Y' => 'Y', '%y' => 'y',
            '%m' => 'm', '%b' => 'M', '%B' => 'F', '%h' => 'M',
            '%d' => 'd', '%e' => 'j', '%a' => 'D', '%A' => 'l',
            '%H' => 'H', '%k' => 'G', '%I' => 'h', '%l' => 'g',
            '%M' => 'i', '%S' => 's',
            '%p' => 'A', '%P' => 'a',
        ];

        return str_replace(array_keys($map), array_values($map), $format);
    }
}