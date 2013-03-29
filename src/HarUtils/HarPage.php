<?php

use HarUtils\HarTime;
use HarUtils\HarRequest;

namespace HarUtils;

class HarPage
{
    private $requests = array();
    private $page = null;

    public function __construct($page)
    {
        $this->page = $page;
        $this->started = new HarTime($this->page['startedDateTime']);
    }

    public function addEntry($request)
    {
        $request = new HarRequest($request);

        $this->requests[ ] = $request;
	}
       
    public function setStartedFromEntry()
    {
        $entry = $this->getFirstEntry();
        if($entry)
        {
            $this->started = $entry->getStarted();
        }
    }

	public function getElapsed(HarRequest $entry)
	{
		return $entry->getElapsed($this->getStarted()->asTimestamp());
	}

	public function getElapsedAsPercentage(HarRequest $entry)
	{
        $total = $this->getTotalTime();
		return $total ? max($this->getElapsed($entry) / ($total / 1000) * 100, 0) : 0;
	}

    public function getFirstEntry()
    {
        reset($this->requests);
        return count($this->requests) ? $this->requests[key($this->requests)] : null;
    }

    public function getEntries()
    {
        reset($this->requests);
        return $this->requests;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function getName()
    {
        return $this->page['title'];
    }

    public function getUrl()
    {
        $entry = $this->getFirstEntry();
        return $entry ? $entry->getUrl() : null;
    }

    public function getId()
    {
        return $this->page['id'];
    }

    public function getLoadTime()
    {
        return array_key_exists('onLoad', $this->page['pageTimings']) ? 
            $this->page['pageTimings']['onLoad'] : 0;
    }

    public function getLoadTimeAndDate()
    {
        return array(
            'date' => $this->getStarted()->asTimestamp(), 
            'value' => $this->getLoadTime()/1000
        );
    }
    
    public function getTotalTime()
    {
        // Find latest request in term of end time
        $time = 0;
        $start = $this->getStarted()->asTimestamp();
        foreach($this->requests as $request)
        {
            $reqtime = $request->getElapsed($start) + $request->getTotalTime()/1000;
            
            if($reqtime > $time)
            {
                $time = $reqtime;
            }
        }
        return $time*1000;
    }

    public function getTotalSize()
    {
        $size = 0;
        foreach($this->requests as $request)
        {
            $size += $request->getSize();
        }
        return $size;
    }

    public function getStarted()
    {
        return $this->started;
    }

    public static function getDayTimestamp($ts)
    {
        return intval($ts / 86400) * 86400;
    }
}

