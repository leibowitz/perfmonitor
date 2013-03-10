<?php
namespace Moschini\PerfToolBundle\Twig;
use HarUtils\HarFile;

class HarExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            'getHumanSize' => new \Twig_Filter_Method($this, 'getHumanSizeFilter'),
            'printUrlsPerDomain' => new \Twig_Filter_Method($this, 'printUrlsPerDomainFilter'),
            'getHumanValues' => new \Twig_Filter_Method($this, 'getHumanValuesFilter'),
            'getHumanTime' => new \Twig_Filter_Method($this, 'getHumanTimeFilter'),
        );
	}
	
	public function getFunctions()
    {
        return array(
			'showTimeBars' => new \Twig_Function_Method($this, 'showTimeBars'),
			'showTimes' => new \Twig_Function_Method($this, 'showTimes'),
        );
	}
	
	public function printUrlsPerDomainFilter($title, $urls)
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

    public function getHumanValuesFilter($value, $units, $digits = 2)
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

        return array(trim(number_format($value, $digits), '0.'), $final_unit);
    }

    private function shiftUnits($units, $start_at)
    {
        while( key($units) != $start_at )
        {
            if(!array_shift($units))
                break;
        }
        return $units;
    }
    
    public function getHumanTimeFilter($time, $start_at = 'ms', $digits = 1)
    {
        $units = array(
            'ms' => 1000, 
            's' => 60, 
            'm' => 60, 
            'h' => 24,
            'd' => 0,
        );

        return self::getHumanValuesFilter($time, self::shiftUnits($units, $start_at), $digits);
    }

    public function getHumanSizeFilter($size, $start_at = 'B', $digits = 2)
    {
        $units = array(
            'B' => 1024, 
            'KB' => 1024, 
            'MB' => 1024, 
            'GB' => 1024, 
            'TB' => 0, 
        );

        return self::getHumanValuesFilter($size, self::shiftUnits($units, $start_at), $digits);
    }

    private function filterTimings($timings, $names = array('dns', 'connect', 'blocked', 'send', 'wait', 'receive'))
    {
        $filtered = array();
        foreach($names as $name)
        {
            if(!array_key_exists($name, $timings))
            {
                continue;
            }
            $time = $timings[$name];
            if(!$time)
            {
                continue;
            }
            $filtered[$name] = $time;
        }
        return $filtered;
    }

    public function showTimes($timings)
    {
        $timings = self::filterTimings($timings);
        foreach($timings as $name => $time)
        {
            echo $name.' '.join(self::getHumanTimeFilter($time))."\n";
        } 
    }

    public function showTimeBars($timings, $total_time)
    {
        $timings = self::filterTimings($timings);
        foreach($timings as $name => $time)
        {
            $percentage = round($time/$total_time*100, 2);
            $humantime = join(self::getHumanTimeFilter($time));
            echo "<span data-toggle='tooltip' data-original-title='${name} ${humantime}' class='${name} time' style='width: ${percentage}%'>";
            echo "<em>$time</em>";
            echo "</span>";
        }
    }

    public function getName()
    {
        return 'perf_extension';
    }
}
