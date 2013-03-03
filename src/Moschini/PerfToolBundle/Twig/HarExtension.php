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
			'showTimeBars' => new \Twig_Function_Method($this, 'showTimeBars')
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

    public function getHumanValuesFilter($value, $units, $format = '%.2f')
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
    
    public function getHumanTimeFilter($time, $start_at = 'ms', $format = '%.2f')
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

        return self::getHumanValuesFilter($time, $units, $format);
    }

    public function getHumanSizeFilter($size, $start_at = 'B', $format = '%.1f ')
    {
        $units = array(
            'B' => 1024, 
            'KB' => 1024, 
            'MB' => 1024, 
            'GB' => 1024, 
            'TB' => 0, 
        );

        return self::getHumanValuesFilter($size, $units, $format);
    }
    
    public function showTimeBars($timings, $total_time)
    {
        $names = array('dns', 'connect', 'blocked', 'send', 'wait', 'receive');
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
            $percentage = round($time/$total_time*100, 2);
            $humantime = self::getHumanTimeFilter($time);
            echo "<span data-toggle='tooltip' data-original-title='${name} ${humantime}' class='${name} time' style='width: ${percentage}%'>";
            echo "<em>${timings[$name]}</em>";
            echo "</span>";
        }
    }

    public function getName()
    {
        return 'perf_extension';
    }
}
