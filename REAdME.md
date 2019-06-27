# SiteMap Generator

This bundle will build the xml sitemap of your website.

## install


to enable it install it from git, it will be pushed to packagist when it will reach a usable stage.

add routing config to the routing.yml

```
_siteMapGenerator:
    resource: "@GieSiteMapGeneratorBundle/Resources/config/routing.yml"
```

## config

The sitemap settings can be set per siteaccess

for example :
```yaml
gie_site_map_generator:
  system:
    site:
      config:
        content_types: ['article', 'landing_page', 'diaporama', 'video']
        container_types: ['landing_page']
        grouping_type: 'roots'

```

You can set the grouping type to 'limit' or 'roots'. Grouping by roots will
generate a sitemap index with a sitemap per root content. Grouping by limit 
will generate a sitemap based on the default limit of 10000.

## usage

The default route is /sitemap.xml

## Todo
 Set the limit in configuration
 