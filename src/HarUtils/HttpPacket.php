<?php

namespace HarUtils;

abstract class HttpPacket
{
    //protected $packet;
    
    public function __construct($packet)
    {
        $this->packet = $packet;
    }

    public function getSize()
    {
        return $this->getBodySize();// + $this->getHeadersSize();
    }
    
    public function getBodySize()
    {
        return $this->packet['bodySize'];
    }
    
    public function getHeadersSize()
    {
        return $this->packet['headersSize'];
    }
    
    public function getCookies()
    {
        return $this->packet['cookies'];
    }
    
    public function getHeaders()
    {
        return $this->packet['headers'];
    }
    
    public function getHttpVersion()
    {
        return $this->packet['httpVersion'];
    }
}

