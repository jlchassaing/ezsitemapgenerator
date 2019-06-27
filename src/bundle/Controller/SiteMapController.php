<?php
/**
 * @author jlchassaing <jlchassaing@gmail.com>
 * @licence MIT
 */

namespace Gie\SiteMapGeneratorBundle\Controller;


use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\Core\MVC\Symfony\Templating\GlobalHelper;
use eZ\Publish\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter;
use EzSystems\PlatformHttpCacheBundle\Handler\TagHandler;
use Gie\SiteMapGenerator\Loader\SiteMapLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\EngineInterface;

class SiteMapController
{

    const GROUP_BY_ROOTS = 'roots';
    const GROUP_BY_LIMIT = 'limit';

    const DEFAULT_LIMIT = 5;

    /**
     * @var \EzSiteMapGenerator\Loader\SiteMapLoader
     */
    private $siteMapLoader;

    /**
     * @var \eZ\Publish\Core\MVC\Symfony\Templating\GlobalHelper
     */
    private $globalHelper;

    /**
     * @var \eZ\Publish\API\Repository\LocationService
     */
    private $locationService;

    /**
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter
     */
    private $slugConverter;

    /**
     * @var \Symfony\Bundle\TwigBundle\TwigEngine
     */
    private $templating;

    /**
     * @var \EzSystems\PlatformHttpCacheBundle\Handler\TagHandler
     */
    private $tagHandler;

    /**
     * @var array
     */
    private $siteMapConfig;

    /**
     * SiteMapController constructor.
     * @param \Gie\SiteMapGenerator\Loader\SiteMapLoader $siteMapLoader
     * @param \eZ\Publish\Core\MVC\Symfony\Templating\GlobalHelper $globalHelper
     * @param \eZ\Publish\API\Repository\LocationService $locationService
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter $slugConverter
     * @param \Symfony\Component\Templating\EngineInterface $templating
     * @param \EzSystems\PlatformHttpCacheBundle\Handler\TagHandler $tagHandler
     * @param $siteMapConfig
     */
    public function __construct(
        SiteMapLoader $siteMapLoader,
        GlobalHelper $globalHelper,
        LocationService $locationService,
        SlugConverter $slugConverter,
        EngineInterface $templating,
        TagHandler $tagHandler,
        $siteMapConfig
    ) {
        $this->siteMapLoader = $siteMapLoader;
        $this->globalHelper = $globalHelper;
        $this->locationService = $locationService;
        $this->slugConverter = $slugConverter;
        $this->templating = $templating;
        $this->tagHandler = $tagHandler;
        $this->siteMapConfig = $siteMapConfig;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function indexAction(Request $request)
    {
        $parentLocation = $this->globalHelper->getRootLocation();
        $limit = self::DEFAULT_LIMIT;
        $defaultGroupBy = $this->siteMapConfig['grouping_type'];
        $offset = 0;

        $params = $defaultGroupBy === self::GROUP_BY_LIMIT ?
            $this->getLimitParams($parentLocation,$limit, $offset) :
            $this->getRootsParams($parentLocation);

        return $this->render($parentLocation, $params);
    }

    public function partAction($part = 1)
    {
        $parentLocation = $this->globalHelper->getRootLocation();
        $limit = self::DEFAULT_LIMIT;
        $offset = ($part - 1) * $limit;
        return $this->baseLoadContent($parentLocation,self::GROUP_BY_LIMIT,$offset,$limit);
    }

    public function locationAction($name, $locationId)
    {
        $parentLocation = $this->locationService->loadLocation((int) $locationId);
        return $this->baseLoadContent($parentLocation, self::GROUP_BY_LIMIT);
    }

    public function baseLoadContent(Location $parentLocation, $type, $offset = 0, $limit = self::DEFAULT_LIMIT)
    {
        $params = $this->getDefaultParams();
        $params['template'] = 'sitemap.xml.twig';

        $query = $this->siteMapLoader->getGlobalQuery($parentLocation, $type);

        $contents = $this->siteMapLoader->getUrlList($query, $limit, $offset, $params['tagKeys']);
        $params['contents'] = $contents;

        return $this->render($parentLocation, $params);
    }

    public function render($parentLocation, $params)
    {
        $tagKeys = $params['tagKeys'] ?: null;
        $response = new Response();
        $response->setSharedMaxAge(3600 * 24);
        $response->setMaxAge(3600);
        $now   = new \DateTime;
        $clone = $now;        //this doesnot clone so:
        $clone->modify( '+ 5minute' );
        //$response->setExpires($clone);

        $response->setVary(['User-Agent', 'Accept-Encoding']);
        $this->tagHandler->addTags(['locationId-' . $parentLocation->id, 'contentId-'.$parentLocation->contentId]);
        if ($tagKeys !== null) {
            $this->tagHandler->addTags(array_unique($tagKeys));
        }
        $this->tagHandler->tagResponse($response);
        $response->headers->set('Content-Type', 'text/xml');

        $params['type'] = $this->siteMapConfig['grouping_type'];

        return $this->templating->renderResponse('@GieSiteMapGenerator/'.$params['template'], $params, $response);
    }

    /**
     * @param $parentLocation
     * @param $limit
     * @param $offset
     * @return mixed
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    private function getLimitParams(Location $parentLocation, $limit, $offset)
    {
        $query = $this->siteMapLoader->getGlobalQuery($parentLocation);
        $count = $this->siteMapLoader->getCount();
        $params = $this->getDefaultParams();

        if ($count > self::DEFAULT_LIMIT) {
            $params['template'] = 'index.xml.twig';
            $parts = $count / self::DEFAULT_LIMIT;

            $params['parts'] = $parts + 1;
        }

        $params['contents'] = $this->siteMapLoader->getUrlList($query, $limit, $offset, $params['tagKeys']);

        return $params;
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Location $parentLocation
     * @return mixed
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    private function getRootsParams(Location $parentLocation) {
        $params = $this->getDefaultParams();
        $params['template'] = 'index.xml.twig';
        $mainNodesQuery = $this->siteMapLoader->getParentsQuery($parentLocation, self::GROUP_BY_ROOTS);
        $contents = $this->siteMapLoader->getLocations($mainNodesQuery);

        foreach ($contents as $content) {

            /**
             * @var \eZ\Publish\API\Repository\Values\Content\Location $location
             */
            $location = $content->valueObject;
            $name = $this->slugConverter->convert($location->getContent()->contentInfo->name);

            $params['parts'][] = [
                'name' => $name,
                'locationId' => $location->id,
            ];
        }
        return $params;
    }

    private function getDefaultParams()
    {
        $params = [];
        $params['template'] = 'sitemap.xml.twig';
        $params['tagKeys']  =[];
        return $params;
     }
}