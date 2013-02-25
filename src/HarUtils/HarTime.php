<?php

namespace HarUtils;

class HarTime
{

    static public function getTimeInSeconds(\DateTime $date)
    {
        return floatval($date->format('U.u'));
    }
    
    static public function parseDate($date)
    {
        return \DateTime::createFromFormat('Y-m-d?H:i:s?u', substr($date, 0, 23));
    }

    public function __construct($date)
    {
        $this->date = self::parseDate($date);
        $this->value = self::getTimeInSeconds($this->date);
    }

    public function __toString()
    {
        return $this->date->format('c');
    }
    
    public function asTimestamp()
    {
        return $this->value;
    }

    public function format($format)
    {
        return $this->date->format($format);
    }
}

