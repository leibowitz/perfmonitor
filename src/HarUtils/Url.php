<?php

namespace HarUtils;

class Url
{
    public function __toString()
    {
        return $this->getUrl();
    }

    public function __construct($url)
    {
        $this->url = $url;
        $this->urlinfo = parse_url($this->url);
        
        $path = trim($this->getPath(), '/');
        if($path)
        {
            $pos = strrpos($path, '/');

            $this->filename = $pos === false ? $path : substr($path, $pos+1);
        }
        else
        {
            $this->filename = null;
        }

    }

    public function getUid()
    {
        return substr(md5($this->getUrl()), 4, 10);
    }

    public function getFileName()
    {
        return $this->filename;
    }

    public function getHost()
    {
        return array_key_exists('host', $this->urlinfo) ? $this->urlinfo['host'] : null;
    }
    
    public function getPath()
    {
        return $this->urlinfo['path'];
    }

    public function getUrl()
    {
        return $this->url;
    }
}

