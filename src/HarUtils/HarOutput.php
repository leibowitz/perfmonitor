<?php

use HarUtils\HarFile;
namespace HarUtils;

class HarOutput
{
    static public function printUrlsPerDomain($title, $urls)
    {
        $results = HarFile::getUrlsPerDomain($urls);

        $sorted_results = array();
        foreach($results as $domain => $urls)
        {
            $sorted_results[$domain] = count($urls);
        }

        arsort($sorted_results);

        echo '<h2>'.$title.'</h2>';
        echo '<table>';

        foreach($sorted_results as $domain => $total)
        {
            echo '<tr><td>'.$domain."</td><td>".$total.'</td></tr>';
        }

        echo '</table>';
    }

    static public function getHumanValues($value, $units, $format = '%.2f')
    {
        $final_unit = null;

        foreach($units as $unit => $limit)
        {
            $final_unit = $unit;

            if($limit != 0 && $value >= $limit)
            {
                $value = $value / $limit;
            }
            else
            {
                break;
            }
        }

        return sprintf($format."%s", $value, $final_unit);
    }
    
    static public function getHumanTime($time, $start_at = 'ms', $format = '%.2f')
    {
        $units = array(
            'ms' => 1000, 
            's' => 60, 
            'm' => 60, 
            'h' => 24,
            'd' => 0,
        );

        while( key($units) != $start_at )
        {
            array_shift($units);
        }

        return self::getHumanValues($time, $units, $format);
    }

    static public function getHumanSize($size, $start_at = 'B', $format = '%.1f ')
    {
        $units = array(
            'B' => 1024, 
            'KB' => 1024, 
            'MB' => 1024, 
            'GB' => 1024, 
            'TB' => 0, 
        );

        return self::getHumanValues($size, $units, $format);
    }
    
    public static function showTimeBars($timings, $total_time)
    {
        foreach($timings as $name => $time)
        {
            echo "<span class='".$name." time' style='width: ".(round($time/$total_time*100, 2))."%'>";
            echo "</span>";
        }
    }
}
