<?php

use HarUtils\HttpPacket;
namespace HarUtils;

class HarHttpResponse extends HttpPacket
{

    public function getStatus()
    {
        return $this->packet['status'];
    }
    
    public function getStatusText()
    {
        return $this->packet['statusText'];
    }
    
    public function getRedirectUrl()
    {
        return $this->packet['redirectURL'];
    }
    
    public function getContent()
    {
        return $this->packet['content'];
    }
    
}

