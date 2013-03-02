<?php

namespace DbUtils;

use HarUtils\HarFile;
use HarUtils\HarResults;

class SitesDb
{
    static public function getSites()
    {
        $db = SitesDb::getDb();  
        return $db->har->distinct('site');
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

    static public function getFilesFromDB($find, $sort = array())
    {
        $db = SitesDb::getDb();  
        $cursor = $db->har->find($find);
        if($sort)
        {
            $cursor = $cursor->sort($sort);
        }
        $result = new HarResults($cursor);
        return $result->getFiles();
    }

    static public function getAllFilesFromDB($find = array(), $sort = array())
    {
        return SitesDb::getFilesFromDB($find, $sort);
    }

    static public function getMostRecentFilesFromDB($find = array(), $sort = array('log.pages.startedDateTime' => -1))
    {
        return SitesDb::getFilesFromDB($find, $sort);
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

};

