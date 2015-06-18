<?php
namespace xrow\bootstrapBundle\Menu;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Search\SearchHit;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use eZ\Publish\Core\Helper\TranslationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Builder
{
    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var SearchService
     */
    private $searchService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ConfigResolverInterface
     */
    private $configResolver;

    /**
     * @var LocationService
     */
    private $locationService;

    /**
     * @var TranslationHelper
     */
    private $translationHelper;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    public function __construct(
        FactoryInterface $factory,
        SearchService $searchService,
        RouterInterface $router,
        ConfigResolverInterface $configResolver,
        LocationService $locationService,
        TranslationHelper $translationHelper,
        ContainerInterface $container
    )
    {
        $this->factory = $factory;
        $this->searchService = $searchService;
        $this->router = $router;
        $this->configResolver = $configResolver;
        $this->locationService = $locationService;
        $this->translationHelper = $translationHelper;
        $this->container = $container;
    }

    public function createTopMenu( Request $request )
    {
        $menu = $this->factory->createItem( 'root' );
        $this->addLocationsToMenu(
            $menu,
            $this->getMenuItems(
                $this->configResolver->getParameter( 'content.tree_root.location_id' )
            )
        );

        return $menu;
    }

    /**
     * Adds locations from $searchHit to $menu
     *
     * @param ItemInterface $menu
     * @param SearchHit[] $searchHits
     * @return void
     */
    private function addLocationsToMenu( ItemInterface $menu, array $searchHits )
    {
        foreach ( $searchHits as $searchHit )
        {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            $menuItem = isset( $menu[$location->parentLocationId] ) ? $menu[$location->parentLocationId] : $menu;
            $menuItem->addChild(
                $location->id,
                array(
                    'label' => $this->translationHelper->getTranslatedContentNameByContentInfo( $location->contentInfo ),
                    'uri' => $this->router->generate( $location )
                )
            );
            $menuItem->setChildrenAttribute( 'class', 'navbar-nav' );
        }
    }

    /**
     * Queries the repository for menu items, as locations filtered on the list in TopIdentifierList in menu.ini
     * @param int|string $rootLocationId Root location for menu items. Only two levels below this one are searched
     * @return SearchHit[]
     */
    private function getMenuItems( $rootLocationId )
    {
        $rootLocation = $this->locationService->loadLocation( $rootLocationId );
        $query = new LocationQuery();
        $show_in_nav_identifier = $this->container->getParameter('xrowbootstrap.show_navigation_identifier');
        $query->query = new Criterion\LogicalAnd(
            array(
                new Criterion\ContentTypeIdentifier( $this->getTopMenuContentTypeIdentifierList() ),
                new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
                new Criterion\Field( $show_in_nav_identifier, Criterion\Operator::EQ, true),
                new Criterion\Location\Depth(
                    Criterion\Operator::BETWEEN,
                    array( $rootLocation->depth + 1, $rootLocation->depth + 2 )
                ),
                new Criterion\Subtree( $rootLocation->pathString ),
                new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' ) )
            )
        );
        $query->sortClauses = array( new Query\SortClause\Location\Path() );

        return $this->searchService->findLocations( $query )->searchHits;
    }

    private function getTopMenuContentTypeIdentifierList()
    {
        return $this->configResolver->getParameter( 'MenuContentSettings.TopIdentifierList', 'menu' );
    }
}
