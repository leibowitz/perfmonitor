<?php

use HarUtils\HttpPacket;
namespace HarUtils;

class HarHttpRequest extends HttpPacket
{
    public function getMethod()
    {
        return $this->packet['method'];
    }
    
    public function getQueryString()
    {
        return $this->packet['queryString'];
    }
    
    public function getUrl()
    {
        return $this->packet['url'];
    }
    
}


