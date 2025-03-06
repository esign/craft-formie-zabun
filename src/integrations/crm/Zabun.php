<?php

namespace esign\craftformiezabunintegration\integrations\crm;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use Throwable;

use verbb\formie\base\Crm;
use verbb\formie\base\Integration;
use verbb\formie\elements\Submission;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationFormSettings;

class Zabun extends Crm
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('formie', 'Zabun');
    }

    // Properties
    // =========================================================================

    public const BASE_URL = 'https://public.api-cms.zabun.be/';
    public const API_CONTACT = 'api/v1/contact';
    public const API_CONTACT_MESSAGE = 'api/v1/contactmessage';
    public const API_CONTACT_REQUEST = 'api/v1/contactrequest';
    public const API_HEARTBEAT = 'auth/v1/heartbeat';

    public ?int $xClientId = null;
    public ?string $apiKey = null;
    public ?string $clientId = null;
    public ?string $serverId = null;
    public bool $mapToContactMessage = false;
    public ?array $contactMessageFieldMapping = null;
    public bool $mapToContactRequest = false;
    public ?array $contactRequestFieldMapping = null;

    // Public Methods
    // =========================================================================

    public function getIconUrl(): string
    {
        return Craft::$app->getAssetManager()->getPublishedUrl("@esign/craftformiezabunintegration/icon.jpg", true);
    }

    public function getDescription(): string
    {
        return Craft::t('formie', 'Manage your Zabun real estate contacts by providing important information on their conversion on your site.');
    }

    public function getSettingsHtml(): string
    {
        $variables = $this->getSettingsHtmlVariables();

        return Craft::$app->getView()->renderTemplate('formie-zabun/integrations/crm/zabun/_plugin-settings', $variables);
    }

    public function getFormSettingsHtml($form): string
    {
        $variables = $this->getFormSettingsHtmlVariables($form);

        return Craft::$app->getView()->renderTemplate('formie-zabun/integrations/crm/zabun/_form-settings', $variables);
    }

    public function fetchFormSettings()
    {
        $settings = [];

        try {
            $booleanOptions = [
                [
                    'label' => Craft::t('formie', 'Yes'),
                    'value' => true,
                ],
                [
                    'label' => Craft::t('formie', 'No'),
                    'value' => false,
                ],
            ];

            if ($this->mapToContactMessage) {
                $settings['contactMessage'] = array_merge(
                    $this->_getContactFields($booleanOptions),
                    [
                        // Message fields
                        new IntegrationField([
                            'handle' => 'message:text',
                            'name' => Craft::t('formie', 'Message Text'),
                            'required' => true,
                            'type' => IntegrationField::TYPE_STRING,
                        ]),
                        new IntegrationField([
                            'handle' => 'message:property_id',
                            'name' => Craft::t('formie', 'Message Property ID'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'message:info',
                            'name' => Craft::t('formie', 'Message Info'),
                            'type' => IntegrationField::TYPE_ARRAY,
                        ]),
                    ]
                );
            }

            if ($this->mapToContactRequest) {
                $settings['contactRequest'] = array_merge(
                    $this->_getContactFields($booleanOptions),
                    [
                        // Request fields
                        new IntegrationField([
                            'handle' => 'request:bedrooms.min',
                            'name' => Craft::t('formie', 'Request Bedrooms Min'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:bedrooms.max',
                            'name' => Craft::t('formie', 'Request Bedrooms Max'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:bathrooms.min',
                            'name' => Craft::t('formie', 'Request Bathrooms Min'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:bathrooms.max',
                            'name' => Craft::t('formie', 'Request Bathrooms Max'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:gardens',
                            'name' => Craft::t('formie', 'Request Gardens'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:garages',
                            'name' => Craft::t('formie', 'Request Garages'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:construction_year.min',
                            'name' => Craft::t('formie', 'Request Construction Year Min'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:construction_year.max',
                            'name' => Craft::t('formie', 'Request Construction Year Max'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:price.min',
                            'name' => Craft::t('formie', 'Request Price Min'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:price.max',
                            'name' => Craft::t('formie', 'Request Price Max'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:surface.min',
                            'name' => Craft::t('formie', 'Request Surface Min'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:surface.max',
                            'name' => Craft::t('formie', 'Request Surface Max'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:date_stop',
                            'name' => Craft::t('formie', 'Request Date Stop'),
                            'type' => IntegrationField::TYPE_DATETIME,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:investment',
                            'name' => Craft::t('formie', 'Request Investment'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:equity',
                            'name' => Craft::t('formie', 'Request Equity'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:main_residence',
                            'name' => Craft::t('formie', 'Request Main Residence'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:student_residence',
                            'name' => Craft::t('formie', 'Request Student Residence'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:low_energy_house',
                            'name' => Craft::t('formie', 'Request Low Energy House'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:passive_house',
                            'name' => Craft::t('formie', 'Request Passive House'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:development',
                            'name' => Craft::t('formie', 'Request Development'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:pets_allowed',
                            'name' => Craft::t('formie', 'Request Pets Allowed'),
                            'type' => IntegrationField::TYPE_BOOLEAN,
                            'options' => [
                                'label' => Craft::t('formie', 'Options'),
                                'options' => $booleanOptions,
                            ],
                        ]),
                        new IntegrationField([
                            'handle' => 'request:media_id',
                            'name' => Craft::t('formie', 'Request Media ID'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:sales_rep',
                            'name' => Craft::t('formie', 'Request Sales Rep'),
                            'type' => IntegrationField::TYPE_NUMBER,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:description',
                            'name' => Craft::t('formie', 'Request Description'),
                            'type' => IntegrationField::TYPE_STRING,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:cities',
                            'name' => Craft::t('formie', 'Request Cities'),
                            'type' => IntegrationField::TYPE_ARRAY,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:transactions',
                            'name' => Craft::t('formie', 'Request Transactions'),
                            'type' => IntegrationField::TYPE_ARRAY,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:headtypes',
                            'name' => Craft::t('formie', 'Request Headtypes'),
                            'type' => IntegrationField::TYPE_ARRAY,
                        ]),
                        new IntegrationField([
                            'handle' => 'request:types',
                            'name' => Craft::t('formie', 'Request Types'),
                            'type' => IntegrationField::TYPE_ARRAY,
                        ]),
                    ]
                );
            }
        } catch (Throwable $e) {
            Integration::apiError($this, $e);
        }

        return new IntegrationFormSettings($settings);
    }

    public function sendPayload(Submission $submission): bool
    {
        try {
            if ($this->mapToContactMessage) {
                $contactMessagePayload = $this->getFieldMappingValues($submission, $this->contactMessageFieldMapping, 'contactMessage');
                $formattedPayload = $this->_formatPayload($contactMessagePayload);

                $response = $this->deliverPayload($submission, self::API_CONTACT_MESSAGE, $formattedPayload);

                if ($response === false) {
                    return true;
                }
            }

            if ($this->mapToContactRequest) {
                $contactRequestPayload = $this->getFieldMappingValues($submission, $this->contactRequestFieldMapping, 'contactRequest');
                $formattedPayload = $this->_formatPayload($contactRequestPayload);

                $response = $this->deliverPayload($submission, self::API_CONTACT_REQUEST, $formattedPayload);

                if ($response === false) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    public function fetchConnection(): bool
    {
        try {
            $response = $this->request('GET', self::API_HEARTBEAT);
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    public function getClient(): Client
    {
        if ($this->_client) {
            return $this->_client;
        }

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'X-Client-ID' => App::parseEnv($this->xClientId),
                'api_key' => App::parseEnv($this->apiKey),
                'client_id' => App::parseEnv($this->clientId),
                'server_id' => App::parseEnv($this->serverId),
            ],
        ]);
    }

    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['xClientId'], 'required'];
        $rules[] = [['apiKey'], 'required'];
        $rules[] = [['clientId'], 'required'];
        $rules[] = [['serverId'], 'required'];

        $contactMessage = $this->getFormSettingValue('contactMessage');
        $contactRequest = $this->getFormSettingValue('contactRequest');

        $rules[] = [
            ['contactMessageFieldMapping'], 'validateFieldMapping', 'params' => $contactMessage,
            'when' => function($model) {
                return $model->enabled && $model->mapToContactMessage;
            }, 'on' => [Integration::SCENARIO_FORM],
        ];

        $rules[] = [
            ['contactRequestFieldMapping'], 'validateFieldMapping', 'params' => $contactRequest,
            'when' => function($model) {
                return $model->enabled && $model->mapToContactRequest;
            }, 'on' => [Integration::SCENARIO_FORM],
        ];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    private function _formatPayload(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            if (is_null($value)) {
                continue;
            }
            
            $result = array_merge_recursive($result, $this->_transformToNestedArray($key, $value));
        }
        
        return $result;
    }

    private function _transformToNestedArray(string $input, mixed $value): array
    {
        $keys = explode(':', $input);
        $nestedArray = [];
        $current = &$nestedArray;
        
        foreach ($keys as $key) {
            $current = &$current[$key];
        }
    
        $current = $value;

        return $nestedArray;
    }

    private function _getContactFields(array $booleanOptions): array
    {
        return [
            new IntegrationField([
                'handle' => 'contact:title',
                'name' => Craft::t('formie', 'Contact Title ID'),
                'type' => IntegrationField::TYPE_NUMBER,
            ]),
            new IntegrationField([
                'handle' => 'contact:first_name',
                'name' => Craft::t('formie', 'Contact First Name'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:last_name',
                'name' => Craft::t('formie', 'Contact Last Name'),
                'required' => true,
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:email',
                'name' => Craft::t('formie', 'Contact Email'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:phone',
                'name' => Craft::t('formie', 'Contact Phone'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:phone_cc',
                'name' => Craft::t('formie', 'Contact Phone Country Code'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:mobile',
                'name' => Craft::t('formie', 'Contact Mobile'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:mobile_cc',
                'name' => Craft::t('formie', 'Contact Mobile Country Code'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:language',
                'name' => Craft::t('formie', 'Contact Language'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:country_id',
                'name' => Craft::t('formie', 'Contact Country ID'),
                'type' => IntegrationField::TYPE_NUMBER,
            ]),
            new IntegrationField([
                'handle' => 'contact:city_id',
                'name' => Craft::t('formie', 'Contact City ID'),
                'type' => IntegrationField::TYPE_NUMBER,
            ]),
            new IntegrationField([
                'handle' => 'contact:street',
                'name' => Craft::t('formie', 'Contact Street'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:number',
                'name' => Craft::t('formie', 'Contact Number'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:box',
                'name' => Craft::t('formie', 'Contact Box'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:description',
                'name' => Craft::t('formie', 'Contact Description'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:mailing_opt_in',
                'name' => Craft::t('formie', 'Contact Mailing Opt-in'),
                'type' => IntegrationField::TYPE_BOOLEAN,
                'options' => [
                    'label' => Craft::t('formie', 'Options'),
                    'options' => $booleanOptions,
                ],
            ]),
            new IntegrationField([
                'handle' => 'contact:marketing_opt_in',
                'name' => Craft::t('formie', 'Contact Marketing Opt-in'),
                'type' => IntegrationField::TYPE_BOOLEAN,
                'options' => [
                    'label' => Craft::t('formie', 'Options'),
                    'options' => $booleanOptions,
                ],
            ]),
            new IntegrationField([
                'handle' => 'contact:question',
                'name' => Craft::t('formie', 'Contact Question'),
                'type' => IntegrationField::TYPE_STRING,
            ]),
            new IntegrationField([
                'handle' => 'contact:categories',
                'name' => Craft::t('formie', 'Contact Categories'),
                'type' => IntegrationField::TYPE_ARRAY,
            ]),
            new IntegrationField([
                'handle' => 'contact:media_id',
                'name' => Craft::t('formie', 'Contact Media ID'),
                'type' => IntegrationField::TYPE_NUMBER,
            ]),
        ];
    }
}
