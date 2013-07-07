<?php
namespace Moschini\PerfToolBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Range;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use HarUtils\HarFile;
use HarUtils\HarTime;
use HarUtils\Url;

use DbUtils\SitesDb;
use Domain\Domain;
use SclWhois\DomainLookup;
use SclSocket\Socket;

class DefaultController extends Controller
{



    /**
     * @Route("/info")
     * @Template()
     */
    public function infoAction(Request $request)
    {
        $url = $request->get('url');
        if($url)
        {
            $host = parse_url($url, PHP_URL_HOST);
        
            $rows = SitesDb::getStatsForUrl($url);

        }
        else
        {
            $host = $request->get('host');
            
            $rows = SitesDb::getStatsForHost($host);
        }

        $domain = Domain::getRegisteredDomain($host);

        $timings = SitesDb::getAvgValues(SitesDb::sumUp($rows));
        

        $times = SitesDb::getUrlTimes($rows);

        $urls = SitesDb::getUrlsFromTimesList($times);
        
        return array(
            'url' => $url, 
            'urls' => $urls, 
            'domain' => $domain, 
            'host' => $host, 
            'timings' => $timings, 
            'times' => $times);
    }

    /**
     * @Route("/lookup")
     * @Template()
     */
    public function lookupAction(Request $request)
    {
        $resp = array();

        $host = $request->get('domain');
        
        if(!$host)
            return $resp;
        
        $resp['domain'] = $host;

        $whois = new DomainLookup(new Socket);

        $resp['data'] = $whois->lookup($host);
        return $resp;
    }

    /**
     * @Route("/index")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $requests = SitesDb::getRecentRequests(SitesDb::getRecentRequestsList($request->get('site'), $request->get('url')));
        
        return array(
            'requests' => $requests, 
        );
    }
    
    /**
     * @Route("/delete", defaults={"_format"="json"})
     */
    public function deleteAction(Request $request)
    {
        if($request->isXmlHttpRequest())
        {
            $site = $request->get('site');
            $url = $request->get('url');

            SitesDb::deleteAll($site, $url);
        }
        return new JsonResponse(array('result' => 'success'));
    }

    /**
     * @Route("/send")
     * @Template()
     */
    public function sendAction(Request $request)
    {
        $site = $request->get('site');
        
        $type = SitesDb::getTypeForSite($site);

        $url = $request->get('url');
        $defaultData = array(
            'type' => 'har', 
            'url' => $url, 
            'site' => $site,
            'agent' => $type ? $type : 'desktop',
            'nb' => 1);

        $form = $this->createFormBuilder($defaultData)
            //->add('type', 'choice', array('choices' => array('har' => 'har', 'loadtime' => 'loadtime')))
            ->add('site', 'text', array(
                'attr' => array(
                    'placeholder' => 'Site name',
                )
            ))
            ->add('url', 'text', array(
                'attr' => array(
                    'placeholder' => 'http://www.google.com',
                    'class' => 'input-xxlarge',
                )
            ))
            ->add('agent', 'choice', array('label' => 'User-Agent', 'choices' => array('desktop' => 'Desktop', 'mobile' => 'Mobile'), 'expanded' => true))
            ->add('nb', 'integer', array(
                'label' => 'Number of requests',
                'attr' => array(
                    'class' => 'input-mini',
                ),
                'constraints' => array(
                    new Range(array('min' => 1, 'max' => 20)),
                ),
            ))
            ->getForm();

        if($request->isMethod('POST'))
        {
            $form->bind($request);
            if($form->isValid())
            {
                $data = $form->getData();
                $msg = array(
                    'url' => $data['url'],
                    'site' => $data['site'],
                    'nb' => $data['nb'],
                    'account' => 'me',
                    'type' => 'har', //$data['type'],
                    'agent' => $data['agent'],
                );

                // Send to background job
                $celery = new \Celery('localhost', 'guest', 'guest', '/');
                $celery->PostTask('tasks.processtest', array($msg));
                // If no site has been defined, use the one used for this request 
                if(!$site){
                    $site = $data['site'];
                }
                return $this->redirect($this->generateUrl('moschini_perftool_default_done', array('site' => $site)));
            }
        }
        return array('form' => $form->createView());
        
    }
    
    /**
     * @Route("/done")
     * @Template()
     */
    public function doneAction()
    {
        return array();
    }
    
    /**
     * @Route("/graph")
     * @Template()
     */
    public function graphAction(Request $request)
    {
        /*$to = new \DateTime();
        $to->modify('+1 day');
        $to->setTime(0, 0);
        $from = clone $to;
        $from->modify('-1 week');
        */
        $datas = SitesDb::getLoadTimesPerUrl($request->get('site'), $request->get('url'));//, $from, $to);
        
        //$to->modify('-1 day');

        return array(
            'datas' => $datas, 
            );
    }
    
    /**
     * @Route("/time")
     * @Template()
     */
    public function timeAction(Request $request)
    {
        $to = new \DateTime();
        $to->setTimezone(new \DateTimeZone('GMT'));
        $to->setTime(0, 0);

        $from = clone $to;
        $from->modify('-1 week');
        
        $to->modify('+1 day');

        $reqfrom = $request->query->get('from');
        if($reqfrom)
        {
            $from = new \DateTime("@".$reqfrom, new \DateTimeZone('GMT'));
            $from->setTime(0, 0);
        }
        $reqto = $request->query->get('to');
        if($reqto)
        {
            $to = new \DateTime("@".$reqto, new \DateTimeZone('GMT'));
            $to->modify('+1 day');
            $to->setTime(0, 0);
        }
        

        $site = $request->get('site');
        $url = $request->get('url');
        
        $values = SitesDb::getLoadTimeGroupBySites(
            SitesDb::getLoadTimesAndDatePerUrl($site, $url, $from, $to), 
            $from, 
            $to);

        return array(
            'values' => $values, 
            'from' => $from,
            'to' => $to
        );
    }
    
    /**
     * @Route("/harviewer/{id}")
     * @Template()
     */
    public function harviewerAction(Request $request, $id)
    {
        $db = SitesDb::getDb();
        $mongoid = new \MongoId($id);
        $item = $db->har->findOne(array('_id' => $mongoid));
        $har = HarFile::fromJson($item);
        
        list($previous, $next) = SitesDb::getPreviousNext($item);

        return array(
            'har' => $har,
            'previous' => SitesDb::getObjectId($previous),
            'next' => SitesDb::getObjectId($next),
        );
    }
}
