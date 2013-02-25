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
        $this->requests[] = new HarRequest($request);
	}

	public function getElapsed(HarRequest $entry)
	{
		return $entry->getElapsed($this->getStarted()->asTimestamp());
	}

	public function getElapsedAsPercentage(HarRequest $entry)
	{
		return $this->getElapsed($entry) / ($this->getLoadTime() / 1000) * 100;
	}

    public function getEntries()
    {
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
        if(count($this->requests) != 0)
        {
            return $this->requests[0]->getUrl();
        }
        return null;
    }

    public function getId()
    {
        return $this->page['id'];
    }

    public function getLoadTime()
    {
        return $this->page['pageTimings']['onLoad'];
    }

    public function getLoadTimeAndDate()
    {
        return array(
            'date' => $this->getStarted()->asTimestamp(), 
            'value' => $this->getLoadTime()/1000
        );
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

