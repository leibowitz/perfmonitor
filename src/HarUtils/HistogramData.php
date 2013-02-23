<?php

namespace HarUtils;
use HarFile;
use HarPage;

class HistogramData
{

    private $datas = array();

    public function readHar($files)
    {

        foreach($files as $file)
        {
            $har = new HarFile($file);

            foreach($har->getPages() as $id => $page)
            {
                $values = $page->getLoadTimeAndDate();

                $day = HarPage::getDayTimestamp($values['date']);

                $this->setUrlData($page->getUrl()->getUrl(), $day, $values['value']);

            }
        }    
    }

    public function setUrlData($url, $day, $loadtime)
    {
        if(!array_key_exists($url, $this->datas))
        {
            $this->datas[ $url ] = array();
        }
        
        if(!array_key_exists($day, $this->datas[ $url ]))
        {
            $this->datas[ $url ][ $day ] = array();
        }
        
        $this->datas[ $url ][ $day ][] = $loadtime;
    }

    public function getData()
    {
        return $this->datas;
    }

    public function printCSV()
    {
        $fp = fopen('php://stdout', 'w');
        foreach($this->datas as $url => $datas)
        {
            foreach($datas as $day => $times)
            {
                foreach($times as $time)
                {
                    fputcsv($fp, array($url, $day, $time));
                }
            }
        }
    }

    public function readCSV($file)
    {
    
        $fp = fopen($file, 'r');
        while($fields = fgetcsv($fp))
        {
            list($url, $date, $avg) = $fields;
            $this->setUrlData($url, $date, $avg);

        }
        fclose($fp);
    }

    public static function getQuantile($values, $fraction)
    {
        $len = count($values);

        if($len == 1)
        {
            return $values[0];
        }
        elseif($len == 0)
        {
            return 0;
        }

        $pos = $len * $fraction;
        if($pos % 2 == 0)
        {
            $pos = floor($pos);
            return ($values[$pos] + $values[$pos+1]) / 2;
        }
        else
        {
            return $values[$pos];
        }
    }

    public function getHistAvgValue($times)
    {
        sort($times);
        $quart1 = self::getQuantile($times, .25);
        $quart3 = self::getQuantile($times, .75);
        return ($quart1 + $quart3) / 2;
    }
    
    public function getUrlDaysValues()
    {
        $results = array();

        foreach($this->datas as $url => $datas)
        {
            $values = array();

            foreach($datas as $day => $times)
            {
                $values[] = array('date' => $day, 'value' => $this->getHistAvgValue($times));
            }

            $results[ $url ] = $values;
        }

        return $results;
    }
}
