<?php

namespace DbUtils;

use HarUtils\HarFile;
use HarUtils\HarTime;
use HarUtils\HarResults;
use HarUtils\Url;

class SitesDb
{

    static public function getRecentRequests($rows)
    {
        $requests = array();

        foreach($rows as $row)
        {
            $requests[ (string)$row['_id'] ] = array(
                'url' => $row['log']['entries'][0]['request']['url'],
                'date' => new HarTime($row['log']['pages'][0]['startedDateTime']),
                'agent' => SitesDb::getRowField($row, 'agent'),
                'loadtime' => SitesDb::getRowField($row['log']['pages'][0]['pageTimings'], 'onLoad'),
                );
        }

        return $requests;
    }
    
    static public function createUrl($url)
    {
        return new Url($url);
    }

    static public function getUrlsFromTimesList($times)
    {
        $urls = array_unique(array_keys($times));
        return array_combine($urls, array_map('DbUtils\\SitesDb::createUrl', $urls));
    }

    static public function getUrlTimes($rows)
    {
        $times = array();

        foreach($rows as $row)
        {
            $times[ $row['url'] ][] = $row['time']/1000;
        }

        return $times;
    }
            

    static public function getLoadTimeGroupBySites($site, $datas, $from, $to)
    {
        if(count($datas) == 0)
        {
            $sites = SitesDb::getSitesAndUrls();
            if($sites && isset($sites[$site]))
            {
                $values = array_pad(array(), count($sites[$site]), array());
                $datas = array_combine($sites[$site], $values);
            }
        }

        $to->modify('-1 day');
        array_walk($datas, array('DbUtils\\SitesDb', 'groupValuesByDate'), array('from' => $from, 'to' => $to));
        return $datas;
    }

    static public function getRowField($row, $field, $default = null)
    {
        return array_key_exists($field, $row) ? $row[$field] : $default;
    }
    
    static public function sumUp($rows)
    {
        $timings = array();
        foreach($rows as $row)
        {
            $url = $row['url'];

            foreach($row['timings'] as $name => $time)
            {
                if(!array_key_exists($url, $timings) || !array_key_exists($name, $timings[$url]))
                {
                    $timings[$url][$name] = 0;
                }
                if($time != -1)
                {
                    $timings[$url][$name] += $time;
                }
            }
        }
        return $timings;
    }
       
    static public function getAvgValues($data)
    {
        $values = array();
        foreach($data as $url => $timings)
        {
            $nbentries = count($timings);

            foreach($timings as $name => $value)
            {
                $value = $value / $nbentries;
                if($value > 0)
                {
                    $values[$url][] = array('name' => $name, 'val' => $value);
                }
            }
        }
        return $values;
    }

    static public function addOrderByAndDate($query, $field, $value, $sort = 1)
    {
        $operator = $sort == 1 ? '$gt' : '$lt';
        $query[$field] = array($operator => $value);
        return array('query' => $query, 'orderby' => array($field => $sort));
    }

    static public function getPreviousNext($item)
    {
        $db = SitesDb::getDb();
        $find = SitesDb::getRelatedFinder($item);
        $date = $item['log']['pages'][0]['startedDateTime'];
        $findNext = SitesDb::addOrderByAndDate($find, 'log.pages.startedDateTime', $date, 1);
        $findPrevious = SitesDb::addOrderByAndDate($find, 'log.pages.startedDateTime', $date, -1);
        $next = $db->har->findOne($findNext, array('_id' => 1));
        $previous = $db->har->findOne($findPrevious, array('_id' => 1));
        return array($previous, $next);
    }

    static public function getRelatedFinder($item)
    {
        $site = $item['site'];
        $url = $item['log']['entries'][0]['request']['url'];
        return array(
            '_id' => array('$ne' => $item['_id']),
            'site' => $site, 
            'log.entries.request.url' => $url
        );
    }

    static public function getObjectId($item)
    {
        return $item ? $item['_id'] : null;
    }


    static public function groupValuesByDate(&$values, $url, $userdata)
    {
        $interval = new \DateInterval('P1D');
        
        $times = array();

        foreach($values as $data)
        {
            $tz = new \DateTimeZone('Europe/London');
            $data['date']->getDate()->setTimeZone($tz);
            $data['date']->getDate()->setTime(0, 0);
            $ts = $data['date']->asTimestamp();

            $times[ $ts ][] = $data['value'];
        }

        $values = $times;
    }
    
    static public function deleteAll($site, $url = null)
    {
        $db = SitesDb::getDb();
        $find = array('site' => $site);
        if($url)
        {
            $find['url'] = $url;
        }
        return $db->har->remove($find);
    }

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


    static public function getLoadTimes($site, $url, $from = null, $to = null)
    {
        $find = array(
            'site' => $site, 
            'log.pages.pageTimings.onLoad' => array('$exists' => true)
            ); 
        if($from)
        {
            $find['log.pages.startedDateTime']['$gt'] = $from->format(\DateTime::ISO8601);
        }
        if($to)
        {
            $find['log.pages.startedDateTime']['$lt'] = $to->format(\DateTime::ISO8601);
        }
        if($url)
        {
            $find['log.entries.request.url'] = $url;
        }

        $db = SitesDb::getDb();  

        $db->har->ensureIndex(array('log.pages.startedDateTime'=>1), array('background' => true));

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
            $onload = $row['log']['pages'][0]['pageTimings']['onLoad'];
            if($onload)
            {
                $urls[ $row['log']['entries'][0]['request']['url'] ][] = $onload / 1000;
            }
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
                    'date' => $date,
                );
        }
        
        return $urls;
    }

    static public function getLoadTimesPerUrl($site, $url, $from = null, $to = null)
    {
        return self::groupByUrl(self::getLoadTimes($site, $url, $from, $to));
    }
    static public function getLoadTimesAndDatePerUrl($site, $url, $from, $to)
    {
        return self::groupByUrlWithDate(self::getLoadTimes($site, $url, $from, $to));
    }

    static public function getSites()
    {
        $db = SitesDb::getDb();  
        return $db->har->distinct('site');
    }

    static public function getRecentRequestsList($site, $url, $limit = 0)
    {
        $find = self::filterBySiteAndUrl($site, $url);
        $fields = array(
            'log.pages.startedDateTime' => 1, 
            'log.entries' => array('$slice' => array(0, 1)),
            'log.entries.request.url' => 1,
            'log.pages.pageTimings.onLoad' => 1,
            'agent' => 1);
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
        return $db->har->distinct('log.entries.request.url', array('site' => $name));
    }
    
    static public function getManagedSites()
    {
        $db = SitesDb::getDb();  
        return $db->sites->distinct('site');
    }
    
    static public function getSitesConfig($find = array(), $sort = array('interval' => 1))
    {
        $db = SitesDb::getDb();  
        if($find && array_key_exists('_id', $find))
        {
            return $db->sites->findOne($find);
        }
        
        return $db->sites->find($find)->sort($sort);
    }

    static public function getSiteField($find, $field)
    {
        $db = SitesDb::getDb();  
        $row = $db->sites->findOne($find, array($field => 1));
        return $row && array_key_exists($field, $row) ? $row[$field] : null;
    }
    
    static public function getTypeForSite($site)
    {
        return SitesDb::getSiteField(array('site' => $site, 'agent' => 1), 'agent');
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

    static public function getDb($db = 'perfmonitor')
    {
        $m = new \MongoClient();
        return $m->selectDB($db);  
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
    
    static public function insertToDb($data)
    {
        $db = self::getDb();
        $key = array(
            'site' => $data['site'],
            'interval' => $data['interval'],
        );
        
        return $db->sites->update($key, 
            array(
                '$set' => array(
                    'nb' => $data['nb'],
                    'agent' => $data['agent'],
                ), 
                '$addToSet' => array('urls' => array('$each' => $data['urls']))), 
            array('upsert' => true));
    }
    
    static public function updateToDb($id, $data)
    {
        $db = self::getDb();
        $key = array(
            '_id' => $id
        );
        
        return $db->sites->update($key, array('$set' => $data)); 
    }

    static public function getStatsForUrl($url)
    {
        $db = SitesDb::getDb();  
        $cursor = $db->har->aggregate(
            array(
                array('$match'=>array('log.entries.request.url'=>$url)), 
                array('$unwind'=>'$log.entries'), 
                array('$match'=> array('log.entries.request.url'=>$url)), 
                array('$project'=> array('url'=>'$log.entries.request.url', 'timings'=> '$log.entries.timings', 'time'=> '$log.entries.time'))
            )
       );

       return $cursor['result'];
    }

    static public function getStatsForHost($host)
    {
        return self::getStatsForUrl(array('$regex'=>'^http://'.$host));

    }

}

