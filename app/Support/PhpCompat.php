<?php
declare(strict_types=1);

if (!function_exists("array_is_list")) {
    function array_is_list(array $array): bool
    {

        $expected = 0;
        foreach ($array as $key => $_value) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }
}
