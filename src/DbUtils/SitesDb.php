<?php

namespace DbUtils;

use HarUtils\HarFile;
use HarUtils\HarTime;
use HarUtils\HarResults;
use HarUtils\Url;

class SitesDb
{

    public function getId($id)
    {
        return new \MongoId($id);
    }

    public function getRecentRequests($rows)
    {
        $requests = array();

        foreach($rows as $row)
        {
            $requests[ (string)$row['_id'] ] = array(
                'url' => $row['log']['entries'][0]['request']['url'],
                'date' => new HarTime($row['log']['pages'][0]['startedDateTime']),
                'agent' => $this->getRowField($row, 'agent'),
                'loadtime' => $this->getRowField($row['log']['pages'][0]['pageTimings'], 'onLoad'),
                );
        }

        return $requests;
    }
    
    public function createUrl($url)
    {
        return new Url($url);
    }

    public function getUrlsFromTimesList($times)
    {
        $urls = array_unique(array_keys($times));
        return array_combine($urls, array_map(array($this, 'createUrl'), $urls));
    }

    public function getUrlTimes($rows)
    {
        $times = array();

        foreach($rows as $row)
        {
            $times[ $row['url'] ][] = $row['time']/1000;
        }

        return $times;
    }
            

    public function getLoadTimeGroupBySites($site, $datas, $from, $to)
    {
        if(count($datas) == 0)
        {
            $sites = $this->getSitesAndUrls();
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

    public function getRowField($row, $field, $default = null)
    {
        return array_key_exists($field, $row) ? $row[$field] : $default;
    }
    
    public function sumUp($rows)
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
       
    public function getAvgValues($data)
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

    public function addOrderByAndDate($query, $field, $value, $sort = 1)
    {
        $operator = $sort == 1 ? '$gt' : '$lt';
        $query[$field] = array($operator => $value);
        return array('query' => $query, 'orderby' => array($field => $sort));
    }

    public function getPreviousNext($item)
    {
        $db = $this->getDb();
        $find = $this->getRelatedFinder($item);
        $date = $item['log']['pages'][0]['startedDateTime'];
        $findNext = $this->addOrderByAndDate($find, 'log.pages.startedDateTime', $date, 1);
        $findPrevious = $this->addOrderByAndDate($find, 'log.pages.startedDateTime', $date, -1);
        $next = $db->har->findOne($findNext, array('_id' => 1));
        $previous = $db->har->findOne($findPrevious, array('_id' => 1));
        return array($previous, $next);
    }

    public function getRelatedFinder($item)
    {
        $site = $item['site'];
        $url = $item['log']['entries'][0]['request']['url'];
        return array(
            '_id' => array('$ne' => $item['_id']),
            'site' => $site, 
            'log.entries.request.url' => $url
        );
    }

    public function getObjectId($item)
    {
        return $item ? $item['_id'] : null;
    }


    public function groupValuesByDate(&$values, $url, $userdata)
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
    
    public function deleteAll($site, $url = null)
    {
        $db = $this->getDb();
        $find = array('site' => $site);
        if($url)
        {
            $find['url'] = $url;
        }
        return $db->har->remove($find);
    }

    public function find($find, $fields)
    {
        $db = $this->getDb();  
        return $db->har->find($find, $fields);
    }

    public function findSort($find, $fields = array(), $sort = array(), $limit = 0)
    {
        $cursor = $this->find($find, $fields);
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

    public function aggregate($query, $fields = array(), $unwind = null, $groupby = null)
    {

        $db = $this->getDb();  
        return $db->har->aggregate(
            array(
                '$project' => $fields,
                '$match' => $query,
            ), 
            array('$unwind' => '$'.$unwind), 
            array('$group' => array('_id' => '$'.$unwind, 'times' => array('$push' => '$'.$groupby)))
            );
    }

    public function filterBySiteAndUrl($site, $url)
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


    public function getLoadTimes($site, $url, $from = null, $to = null)
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

        $db = $this->getDb();  

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

    public function groupByUrl($rows)
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
    
    public function groupByUrlWithDate($rows)
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

    public function getLoadTimesPerUrl($site, $url, $from = null, $to = null)
    {
        return $this->groupByUrl($this->getLoadTimes($site, $url, $from, $to));
    }
    public function getLoadTimesAndDatePerUrl($site, $url, $from, $to)
    {
        return $this->groupByUrlWithDate($this->getLoadTimes($site, $url, $from, $to));
    }

    public function getSites()
    {
        $db = $this->getDb();  
        return $db->har->distinct('site');
    }

    public function getRecentRequestsList($site, $url, $limit = 0)
    {
        $find = $this->filterBySiteAndUrl($site, $url);
        $fields = array(
            'log.pages.startedDateTime' => 1, 
            'log.entries' => array('$slice' => array(0, 1)),
            'log.entries.request.url' => 1,
            'log.pages.pageTimings.onLoad' => 1,
            'agent' => 1);
        $sort['log.pages.startedDateTime'] = -1;
        return $this->findSort($find, $fields, $sort, $limit);
    }

    public function getSitesAndUrls()
    {
        $sites = $this->getSites();
        $results = array();
        foreach($sites as $name)
        {
            $results[$name] = $this->getUrls($name);
        }
        return $results;
    }

    public function getUrls($name)
    {
        $db = $this->getDb();
        $rows = $db->har->aggregate(array( 
            array( '$match' => array('site' => $name)),
            array( '$unwind' => '$log.entries' ),
            array( '$group' => array('_id' => '$log.entries.pageref', 'entries' => array('$first' => '$log.entries.request.url'))),
            array( '$project' => array('_id' => 0, 'entries' => 1) ),
            array( '$group' => array('_id' => '$entries') ),
        ));
        return $rows['result'];
    }
    
    public function getManagedSites()
    {
        $db = $this->getDb();  
        return $db->sites->distinct('site');
    }
    
    public function getSitesConfig($find = array(), $sort = array('interval' => 1))
    {
        $db = $this->getDb();  
        if($find && array_key_exists('_id', $find))
        {
            return $db->sites->findOne($find);
        }
        
        return $db->sites->find($find)->sort($sort);
    }

    public function getSiteField($find, $field)
    {
        $db = $this->getDb();  
        $row = $db->sites->findOne($find, array($field => 1));
        return $row && array_key_exists($field, $row) ? $row[$field] : null;
    }
    
    public function getTypeForSite($site)
    {
        return $this->getSiteField(array('site' => $site, 'agent' => 1), 'agent');
    }

    public function getFilesFromDB($find, $fields = array(), $sort = array(), $limit = 0)
    {
        $db = $this->getDb();  
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

    public function getAllFilesFromDB($find = array(), $fields = array(), $sort = array(), $limit = 0)
    {
        return $this->getFilesFromDB($find, $fields = array(), $sort, $limit);
    }

    public function getMostRecentRequests($find = array(), $sort = array(), $limit = 0)
    {
        return $this->getFilesFromDB($find, $sort, $limit);
    }

    public function getDb($db = 'perfmonitor')
    {
        $m = new \MongoClient();
        return $m->selectDB($db);  
    }

    public function getFilesFromFilter($site = null, $url = null, $limit = 0)
    {
        $files = null;

        if($site)
        {
            $find = array('site' => $site);

            if($url)
            {
                $find['log.entries.request.url'] = $url;
            }

            $files = $this->getMostRecentRequests($find, array('log.pages.startedDateTime' => -1), $limit);
        }
        
        return $files;
    }
    
    public function insertToDb($data)
    {
        $db = $this->getDb();
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
    
    public function updateToDb($id, $data)
    {
        $db = $this->getDb();
        $key = array(
            '_id' => $id
        );
        
        return $db->sites->update($key, array('$set' => $data)); 
    }

    public function getStatsForUrl($url)
    {
        $db = $this->getDb();  
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

    public function getStatsForHost($host)
    {
        return $this->getStatsForUrl(array('$regex'=>'^http://'.$host));

    }

    public function getHarItem($id)
    {
        $db = $this->getDb();
        $mongoid = new \MongoId($id);

        return $db->har->findOne(array('_id' => $mongoid));
    }
}

