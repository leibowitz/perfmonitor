<?php

use HarUtils\HarPage;
namespace HarUtils;

class HarFile
{
    private $pages = array();
    private $file = null;
    private $content = null;

    private $extensions = array(
        'images' => 
            array('.png', '.jpg', '.gif'),
        'javascripts' => 
            array('.js'),
        'stylesheets' => 
            array('.css')
    );
    
    const TYPE_IMAGE = 'images';
    const TYPE_JAVASCRIPT = 'javascripts';
    const TYPE_CSS = 'stylesheets';

    public static function fromFile($file)
    {
        $har = new HarFile();
        $har->setContent(self::parseFile($file));
        return $har;
    }

    public static function fromJson($string)
    {
        $har = new HarFile();
        $har->setContent($string);
        return $har;
    }

    private static function parseFile($file)
    {
        $content = file_get_contents($file);
        
        return json_decode($content, true);
    }

    public function getContent()
    {
        return $this->content;
    }
    
    public function setContent($content)
    {
        if(!$content)
            throw new \Exception('HarFile exception: Empty content');
        $this->content = $content;
    }

    private function setEntries()
    {
		$ids = array();
		foreach($this->content['log']['pages'] as $page)
		{
			$ids[] = $page['id'];
		}
        foreach($this->content['log']['entries'] as $entry)
        {
            if( array_key_exists('pageref', $entry) && array_key_exists($entry['pageref'], $this->pages) )
            {
                $this->pages[ $entry['pageref'] ]->addEntry($entry);
            }
			else if(count($ids) > 0)
			{
                $this->pages[ $ids[0] ]->addEntry($entry);
			}
        }
    }

    public function setPages()
    {
        foreach($this->content['log']['pages'] as $page)
        {
            $this->pages[ $page['id'] ] = new HarPage($page);

        }
    }
    
    public function getFirstPage()
    {
        foreach($this->getPages() as $page)
        {
            return $page;
        }
        
        throw new Exception('HarFile: no page found');
    }

    public function getName()
    {
        return $this->getFirstPage()->getName();
    }
    
    public function getId()
    {
        return $this->getFirstPage()->getId();
    }
    
    public function getDate()
    {
        return $this->getFirstPage()->getStarted();
    }
    
    public function getUrl()
    {
        return $this->getFirstPage()->getUrl();
    }

    public function getPages()
    {
        if(!$this->pages)
        {
            $this->setPages();
            $this->setEntries();
        }
        
        return $this->pages;
    }

    public function getPage($id)
    {
        return $this->pages[ $id ];
    }
      
    public function getLoadTime($id)
    {
        return $this->getPage($id)->getLoadTime();
    }

    public function getLoadTimes()
    {
        $times = array();

        foreach($this->getPages() as $id => $page)
        {
            $times[] = $page->getLoadTimeAndDate();
        }

        return $times;
    }
        
    private function matchExtensions($url, $extensions)
    {
        foreach($extensions as $ext)
        {
            if( false !== strpos($url, $ext) )
                return true;
        }

        return false;
    }

    public function getUrls($id, $ext = array())
    {
        if(!is_array($ext)){
            $ext = (array) $ext;
        }

        $urls = array();

        foreach($this->getPage($id)->getEntries() as $entry)
        {
            if( !$ext || $this->matchExtensions($entry->getUrl()->getFileName(), $ext))
            {
                $urls[] = $entry->getUrl();
            }
        }

        return $urls;
    }

    public function getExtensions($type)
    {
        return $this->extensions[$type];
    }

    public function getImages($id)
    {
        return $this->getUrls($id, $this->getExtensions(self::TYPE_IMAGE));
    }

    public function getJavascripts($id)
    {
        return $this->getUrls($id, $this->getExtensions(self::TYPE_JS));
    }
    
    public function getStylesheets($id)
    {
        return $this->getUrls($id, $this->getExtensions(self::TYPE_CSS));
    }

    static public function getUrlsPerDomain($urls)
    {
        $domains = array();

        foreach($urls as $url)
        {
            $domains[ $url->getHost() ][] = $url->getPath();
        }
        
        return $domains;
    }

    public function getFileTypes()
    {
        return array_keys($this->extensions);
    }
}



