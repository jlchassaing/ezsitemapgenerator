<?php
/**
 * @author jlchassaing <jlchassaing@gmail.com>
 * @licence MIT
 */

namespace Gie\SiteMapGenerator\Loader;

use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\API\Repository\Values\Content\Search\SearchResult;
use GC\Bundle\SocleBundle\Model\TreeModel;
use GC\Bundle\SocleBundle\Utils\LocationUtils;
use eZ\Publish\API\Repository\SearchService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class SiteMapLoader
{
    /**
     * @var \eZ\Publish\API\Repository\SearchService
     */
    private $searchService;

    /**
     * @var \Symfony\Component\Routing\RouterInterface
     */
    private $router;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var integer
     */
    private $count;

    /**
     * @var array
     */
    private $siteMapConfig;


    /**
     * SiteMap constructor.
     * @param \eZ\Publish\API\Repository\SearchService $searchService
     * @param \Symfony\Component\Routing\RouterInterface $router
     */
    public function __construct(
        SearchService $searchService,
        RouterInterface $router,
        $siteMapConfig

    ) {
        $this->searchService = $searchService;
        $this->router = $router;
        $this->siteMapConfig =$siteMapConfig;

    }


    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Query $query
     * @param int $limit
     * @param int $offset
     * @param array $tagKeys
     * @return array
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function getUrlList(Query $query, int $limit = 10000, int $offset = 0, array &$tagKeys = [])
    {
        $searchResults = $this->getLocations($query, $offset, $limit);
        $urls = [];
        foreach ( $searchResults as $searchHit )
        {
            /**
             * @var \eZ\Publish\API\Repository\Values\Content\Location $location
             */
            $location = $searchHit->valueObject;
            $urls[$location->id] = [
                'id' => $location->id,
                'name' => $location->contentInfo->name,
                'url' => $this->router->generate($location, [], UrlGeneratorInterface::ABSOLUTE_URL ),
                ];
            $tagKeys[] = 'relation-'.$location->id;
        }

        return $urls;
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\LocationQuery $query
     * @param int $offset
     * @param int $limit
     * @return \eZ\Publish\API\Repository\Values\Content\Search\SearchHit[]
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function getLocations(LocationQuery $query, $offset = 0, $limit = 10000)
    {
        $query->offset = $offset;
        $query->limit = $limit;

        $searchResult = $this->searchService->findLocations($query);

        return $searchResult->searchHits;
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Location $parentLocation
     * @return \eZ\Publish\API\Repository\Values\Content\Query
     */
    public function getGlobalQuery(Location $parentLocation, $type, $depth = 10): Query
    {
        $query = $this->getBaseQuery($parentLocation, $depth, $type);

        return $this->query = $query;
    }

    public function getParentsQuery(Location $parentLocation): Query
    {
        $query = $this->getBaseQuery($parentLocation, 2, 'roots');

        return $this->query = $query;
    }

    private function getBaseQuery(Location $parentLocation, $depth = 10, $type)
    {
        $query = new LocationQuery();
        $query->query = new Criterion\LogicalAnd(
            [
                new Criterion\Subtree($parentLocation->pathString),
                new Criterion\ContentTypeIdentifier($this->getTypes($type)),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Location\Depth(Criterion\Operator::LT, $parentLocation->depth + $depth),
            ]
        );
        $query->sortClauses = [
            new SortClause\DatePublished(Query::SORT_DESC),
        ];

        return $query;
    }

    private function getTypes($type)
    {
        if ($type == 'roots')
        {
            return $this->siteMapConfig['container_types'];
        }
        return $this->siteMapConfig['content_types'];

    }

    /**
     * @return int
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function getCount(): int
    {
        $cloneQuery = clone $this->query;

        $cloneQuery->limit = 0;

        return $this->count = $this->searchService->findLocations($cloneQuery)->totalCount;
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Search\SearchHit[] $result
     * @return array
     */
    public function format(array $result): array
    {
        $data = [];
        foreach ($result->searchHits as $searchHit) {

            /**
             * @var \eZ\Publish\API\Repository\Values\Content\Location $location
             */
            $location = $searchHit->valueObject;

            $data[] = [
                'id' => $location->id,
                'name' => $location->contentInfo->name,
                'url' => $this->router->generate($location, [], UrlGeneratorInterface::ABSOLUTE_URL )
            ];
        }
        return $data;
    }
}