<?php

use HarUtils\HarTime;
use HarUtils\HarHttpRequest;
use HarUtils\HarHttpResponse;

namespace HarUtils;

class HarRequest
{
    private $url = null;
    private $timings = null;
    private $started = null;

    public function __construct($entry)
    {
        $times = array();

        if(array_key_exists('timings', $entry)){
            foreach($entry['timings'] as $type => $time)
            {
                if($time != -1)
                {
                    $times[ $type ] = $time;
                }
            }
        }

        $this->timings = $times;

        $this->started = new HarTime($entry['startedDateTime']);
        
        $this->request = new HarHttpRequest($entry['request']);
        $this->response = new HarHttpResponse($entry['response']);
        $this->cache = $entry['cache'];
        
        $this->url = new Url($this->request->getUrl());
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getSize()
    {
        return $this->getResponse()->getSize();
    }
    
    public function getResponse()
    {
        return $this->response;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getTimings()
    {
        return $this->timings;
    }

    public function getTotalTime()
    {
        return array_sum($this->timings);
    }
    
    public function getStarted()
    {
        return $this->started;
    }
	
	public function getElapsed($start_time)
	{
		return $this->getStarted()->asTimestamp() - $start_time;
	}

}

