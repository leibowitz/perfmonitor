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
        return new \DateTime($date);
    }

    public function __construct($date)
    {
        $this->date = self::parseDate($date);
    }

    public function getDate()
    {
        return $this->date;
    }

    public function asSeconds()
    {
        return HarTime::getTimeInSeconds($this->getDate());
    }

    public function __toString()
    {
        return $this->date->format('c');
    }
    
    public function asTimestamp()
    {
        return floatval($this->getDate()->format('U.u'));
    }

    public function format($format)
    {
        return $this->date->format($format);
    }
}

