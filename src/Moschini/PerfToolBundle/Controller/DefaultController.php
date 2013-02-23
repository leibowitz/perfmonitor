<?php

namespace Moschini\PerfToolBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use HarUtils\HarOutput;
use HarUtils\HarFile;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('MoschiniPerfToolBundle:Default:index.html.twig', array('name' => $name));
    }
	
	/**
     * @Route("/graph")
     * @Template()
     */
    public function graphAction()
	{
		$urls = array();
		$datas = array();

		$files = $this->getHarFiles();

		foreach($files as $har)
		{
			foreach($har->getPages() as $page)
			{
				$url = $page->getUrl();
				$url_key = $url->getUid();
				if(!array_key_exists($url_key, $urls))
				{
					$urls[ $url_key ] = $url;
				}

				if(!array_key_exists($url_key, $datas)){
					$datas[ $url_key ] = array();
				}

				$datas[ $url_key ][ ] = $page->getLoadTime() / 1000;
			}
		}
		return array(
			'datas' => $datas,
			'urls' => $urls,
			);
    }
	
	/**
     * @Route("/time")
     * @Template()
     */
    public function timeAction()
	{
		return array(
			'files' => $this->getHarFiles(),
			);
	}

	private function getHarFiles($glob = '../harfiles/inline-scripts-block.har')
	{
		$files = glob($glob);
		$harfiles = array();
		foreach($files as $file)
		{
			$harfiles[$file] = new HarFile($file);
		}
		return $harfiles;
	}
	
    /**
     * @Route("/harviewer")
     * @Template()
     */
	public function harviewerAction()
    {
		$har = new HarFile('../harfiles/inline-scripts-block.har');
        return array('har' => $har);
    }
}
