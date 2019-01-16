<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\BaseDriver;
use putyourlightson\blitz\helpers\DriverHelper;
use putyourlightson\blitz\helpers\PurgerHelper;
use putyourlightson\blitz\purgers\BasePurger;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Saves the plugin settings.
     *
     * @return Response|null
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $postedSettings = Craft::$app->getRequest()->getBodyParam('settings', []);
        $driverSettings = Craft::$app->getRequest()->getBodyParam('driverSettings', []);
        $purgerSettings = Craft::$app->getRequest()->getBodyParam('purgerSettings', []);

        $settings = Blitz::$plugin->getSettings();
        $settings->setAttributes($postedSettings, false);

        // Remove driver type from settings
        $settings->driverSettings = $driverSettings[$settings->driverType] ?? [];

        // Create the driver so that we can validate it
        /* @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            $settings->driverType,
            $settings->driverSettings
        );

        // Remove purger type from settings
        $settings->purgerSettings = $purgerSettings[$settings->purgerType] ?? [];

        // Create the purger so that we can validate it
        /* @var BasePurger $purger */
        $purger = PurgerHelper::createPurger(
            $settings->purgerType,
            $settings->purgerSettings
        );

        // Validate
        $settings->validate();
        $driver->validate();

        if ($purger !== null) {
            $purger->validate();
        }

        if ($settings->hasErrors() || $driver->hasErrors() || ($purger !== null && $purger->hasErrors())) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

            // Send the settings back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings,
                'driver' => $driver,
                'purger' => $purger,
            ]);

            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Plugin settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
