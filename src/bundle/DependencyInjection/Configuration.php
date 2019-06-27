<?php
/**
 * @author jlchassaing <jlchassaing@gmail.com>
 * @licence MIT
 */

namespace Gie\SiteMapGeneratorBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\Configuration as SiteAccessConfiguration;

class Configuration extends SiteAccessConfiguration
{
    public function getConfigTreeBuilder() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gie_site_map_generator');

        $systemNode = $this->generateScopeBaseNode($rootNode);
        $systemNode
            ->arrayNode('config')
                ->children()
                    ->arrayNode('content_types')
                    ->scalarPrototype()->end()
                    ->info('List of content types for the site map.')
                    ->defaultValue(['article'])
                    ->end()
                    ->arrayNode('container_types')
                    ->scalarPrototype()->end()
                    ->info('List of content types for the site map.')
                    ->defaultValue(['folder'])
                    ->end()
                    ->scalarNode('grouping_type')->defaultValue('limit')->end()
                ->end()
            ->end();

        return $treeBuilder;
    }


}