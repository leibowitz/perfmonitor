<?php

namespace DbUtils;

use HarUtils\HarFile;
use HarUtils\HarTime;
use HarUtils\HarResults;

class SitesDb
{

    static public function find($find, $fields)
    {
        $db = SitesDb::getDb();  
        return $db->har->find($find, $fields);
    }

    static public function findSort($find, $fields = array(), $sort = array(), $limit = 0)
    {
        $cursor = self::find($find, $fields);
        if($sort)
        {
            $cursor = $cursor->sort($sort);
        }

        if($limit)
        {
            $cursor->limit($limit);
        }
        
        return $cursor;
    }

    static public function filterBySiteAndUrl($site, $url)
    {
        $find = array();
        if($site)
        {
            $find['site'] = $site;

            if($url)
            {
                $find['log.entries.request.url'] = $url;
            }

        }
        return $find;
    }


    static public function aggregate($query, $fields = array(), $unwind = null, $groupby = null)
    {

        $db = SitesDb::getDb();  
        return $db->har->aggregate(
            array(
                '$project' => $fields,
                '$match' => $query,
            ), 
            array('$unwind' => '$'.$unwind), 
            array('$group' => array('_id' => '$'.$unwind, 'times' => array('$push' => '$'.$groupby)))
            );
    }

    static public function getLoadTimes($site, $url)
    {
        $find = array('site' => $site); 
        if($url)
        {
            $find['log.entries.request.url'] = $url;
        }

        $db = SitesDb::getDb();  

        return $db->har
            ->find(
                $find,
                array(
                    'log.pages.pageTimings.onLoad'=>1, 
                    'log.pages.startedDateTime'=>1, 
                    'site'=>1, 
                    'log.entries'=>
                        array(
                            '$slice'=>array(0,1)),
                    'log.entries.request.url'=>1))
            ->sort(array('log.pages.startedDateTime' => -1));
    }

    static public function groupByUrl($rows)
    {
        $urls = array();

        foreach($rows as $row)
        {
            $urls[ $row['log']['entries'][0]['request']['url'] ][] = $row['log']['pages'][0]['pageTimings']['onLoad'] / 1000;
        }
        
        return $urls;
    }
    
    static public function groupByUrlWithDate($rows)
    {
        $urls = array();

        foreach($rows as $row)
        {
            $date = new HarTime($row['log']['pages'][0]['startedDateTime']);
            $urls[ $row['log']['entries'][0]['request']['url'] ][] = 
                array(
                    'value' => $row['log']['pages'][0]['pageTimings']['onLoad'] / 1000,
                    'date' => $date->asTimestamp(),
                );
        }
        
        return $urls;
    }

    static public function getLoadTimesPerUrl($site, $url)
    {
        return self::groupByUrl(self::getLoadTimes($site, $url));
    }
    static public function getLoadTimesAndDatePerUrl($site, $url)
    {
        return self::groupByUrlWithDate(self::getLoadTimes($site, $url));
    }

    static public function getSites()
    {
        $db = SitesDb::getDb();  
        return $db->har->distinct('site');
    }

    static public function getRecentRequestsList($site, $url, $limit = 0)
    {
        $find = self::filterBySiteAndUrl($site, $url);
        $fields = array('log.pages.startedDateTime' => 1, 'log.entries.request.url' => 1);
        $sort['log.pages.startedDateTime'] = -1;
        return self::findSort($find, $fields, $sort, $limit);
    }

    static public function getSitesAndUrls()
    {
        $sites = SitesDb::getSites();
        $results = array();
        foreach($sites as $name)
        {
            $results[$name] = SitesDb::getUrls($name);
        }
        return $results;
    }

    static public function getUrls($name)
    {
        $db = SitesDb::getDb();
        $rows = $db->har->find(array('site' => $name), array('log.entries.request.url' => 1));
        $urls = array();
        foreach($rows as $row)
        {
            $urls[] = $row['log']['entries'][0]['request']['url'];
        }

        $urls = array_unique($urls);

        return $urls;
    }
    
    static public function getManagedSites()
    {
        $db = SitesDb::getDb();  
        return $db->sites->distinct('site');
    }
    
    static public function getSitesConfig($find = array())
    {
        $db = SitesDb::getDb();  
        if($find && array_key_exists('_id', $find))
        {
            return $db->sites->findOne($find);
        }
        
        return $db->sites->find($find);
    }

    static public function getFilesFromDB($find, $fields = array(), $sort = array(), $limit = 0)
    {
        $db = SitesDb::getDb();  
        $cursor = $db->har->find($find);
        if($sort)
        {
            $cursor = $cursor->sort($sort);
        }

        if($limit)
        {
            $cursor->limit($limit);
        }

        $result = new HarResults($cursor);
        return $result->getFiles();
    }

    static public function getAllFilesFromDB($find = array(), $fields = array(), $sort = array(), $limit = 0)
    {
        return SitesDb::getFilesFromDB($find, $fields = array(), $sort, $limit);
    }

    static public function getMostRecentRequests($find = array(), $sort = array(), $limit = 0)
    {
        return SitesDb::getFilesFromDB($find, $sort, $limit);
    }

	static public function getHarFiles($glob = '../harfiles/inline-scripts-block.har')
	{
		$files = glob($glob);
		$harfiles = array();
		foreach($files as $file)
		{
			$harfiles[$file] = HarFile::fromFile($file);
		}
		return $harfiles;
	}

    static public function getDb()
    {
        $m = new \MongoClient();
        return $m->selectDB("perfmonitor");  
    }

    static public function getFilesFromFilter($site = null, $url = null, $limit = 0)
    {
        $files = null;

        if($site)
        {
            $find = array('site' => $site);

            if($url)
            {
                $find['log.entries.request.url'] = $url;
            }

            $files = SitesDb::getMostRecentRequests($find, array('log.pages.startedDateTime' => -1), $limit);
        }
        
        return $files;
    }

};

