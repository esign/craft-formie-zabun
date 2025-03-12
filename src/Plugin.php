<?php

namespace esign\formiezabun;

use craft\base\Event;
use craft\base\Plugin as BasePlugin;
use esign\formiezabun\integrations\crm\Zabun;
use verbb\formie\events\RegisterIntegrationsEvent;
use verbb\formie\services\Integrations;

/**
 * Formie Zabun Integration plugin
 *
 * @method static Plugin getInstance()
 * @author dieter.vanhove@dynamate.be <support@esign.eu>
 * @copyright dieter.vanhove@dynamate.be
 * @license MIT
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Integrations::class,
            Integrations::EVENT_REGISTER_INTEGRATIONS,
            function(RegisterIntegrationsEvent $event) {
                $event->crm[] = Zabun::class;
            }
        );
    }
}
