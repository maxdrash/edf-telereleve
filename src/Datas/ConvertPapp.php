<?php

namespace Mactronique\TeleReleve\Datas;

class ConvertPapp implements ConverterInterface
{
    /**
     * @param string $code
     * @param string $value
     */
    public static function convert($code, $value)
    {
        return intval($value);
    }
}
