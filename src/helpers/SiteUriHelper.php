<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class SiteUriHelper
{
    /**
     * Returns all site URIs.
     *
     * @return SiteUriModel[]
     */
    public static function getAllSiteUris(): array
    {
        // Use sets and the splat operator rather than array_merge for performance (https://goo.gl/9mntEV)
        $siteUriSets = [[]];

        // Loop through all sites to ensure we warm all site element URLs
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $siteUriSets[] = self::getSiteSiteUris($site->id);
        }

        $siteUris = array_merge(...$siteUriSets);

        return $siteUris;
    }

    /**
     * Returns site URIs for a given site.
     *
     * @param int $siteId
     *
     * @return SiteUriModel[]
     */
    public static function getSiteSiteUris(int $siteId): array
    {
        $siteUris = self::_getCachedSiteUris(['siteId' => $siteId]);

        // Get URLs from all element types
        $elementTypes = Craft::$app->getElements()->getAllElementTypes();

        /** @var Element $elementType */
        foreach ($elementTypes as $elementType) {
            if ($elementType::hasUris()) {
                $elements = $elementType::find()
                    ->siteId($siteId)
                    ->all();

                /** @var Element $element */
                foreach ($elements as $element) {
                    $uri = trim($element->uri, '/');
                    $uri = ($uri == '__home__' ? '' : $uri);

                    $siteUri = new SiteUriModel([
                        'siteId' => $siteId,
                        'uri' => $uri,
                    ]);

                    if (!in_array($siteUri, $siteUris, true) && $siteUri->getIsCacheableUri()) {
                        $siteUris[] = $siteUri;
                    }
                }
            }
        }

        return $siteUris;
    }

    /**
     * Returns refreshable site URIs given an array of cache IDs.
     *
     * @param int[] $cacheIds
     *
     * @return SiteUriModel[]
     */
    public static function getCachedSiteUris(array $cacheIds): array
    {
        return self::_getCachedSiteUris(['id' => $cacheIds]);
    }

    /**
     * Returns URLs of given site URIs.
     *
     * @param SiteUriModel[] $siteUris
     *
     * @return string[]
     */
    public static function getUrls(array $siteUris): array
    {
        $urls = [];

        foreach ($siteUris as $siteUri) {
            $urls[] = $siteUri->getUrl();
        }

        return $urls;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns cached site URIs given a condition.
     *
     * @param array $condition
     *
     * @return SiteUriModel[]
     */
    private static function _getCachedSiteUris(array $condition = []): array
    {
        $siteUriModels = [];

        $siteUris = CacheRecord::find()
            ->select(['siteId', 'uri'])
            ->where($condition)
            ->asArray(true)
            ->all();

        foreach ($siteUris as $siteUri) {
            $siteUriModels[] = new SiteUriModel($siteUri);
        }

        return $siteUriModels;
    }
}