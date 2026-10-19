<?php

namespace esign\formiezabun\integrations\crm;

use Craft;
use craft\helpers\App;
use craft\helpers\Json;
use GuzzleHttp\Client;
use Throwable;

use verbb\formie\base\Crm;
use verbb\formie\base\FieldInterface;
use verbb\formie\base\Integration;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Agree;
use verbb\formie\fields\data\MultiOptionsFieldData;
use verbb\formie\fields\data\OptionData;
use verbb\formie\fields\Hidden;
use verbb\formie\fields\Password;
use verbb\formie\fields\Payment;
use verbb\formie\fields\Recipients;
use verbb\formie\fields\Signature;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationFormSettings;

/**
 * Sends submissions to Zabun 360 (Skarabee WebLink) as "contact me" requests.
 *
 * WebLink has no search profiles: a form that collects search preferences can append its other fields
 * to the comments, so the agency still sees them.
 */
class Zabun360 extends Crm
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('formie', 'Zabun 360');
    }

    // Properties
    // =========================================================================

    public const BASE_URL = 'https://weblink.skarabee.com/rest/';
    public const API_CONTACT_MES = 'v1/contactmes';
    public const API_CONTACT_INFO = 'v1/contactinfo';

    // Left out of the logs when WebLink refuses a contact
    private const PERSONAL_FIELDS = ['firstName', 'lastName', 'email', 'phone', 'cellPhone', 'comments', 'street', 'houseNumber', 'houseNumberExtension', 'zipCode', 'city', 'userName'];

    public ?string $apiKey = null;
    public ?string $originator = null;
    public ?string $officeId = null;
    public bool $mapToContactMe = false;
    public ?array $contactMeFieldMapping = null;
    public bool $appendFieldsToComments = false;

    // Public Methods
    // =========================================================================

    public function getIconUrl(): string
    {
        return Craft::$app->getAssetManager()->getPublishedUrl('@esign/formiezabun/assets/icon.jpg', true);
    }

    public function getDescription(): string
    {
        return Craft::t('formie', 'Send your form submissions to Zabun 360 as contact requests.');
    }

    public function getSettingsHtml(): string
    {
        $variables = $this->getSettingsHtmlVariables();

        return Craft::$app->getView()->renderTemplate('formie-zabun/integrations/crm/zabun-360/_plugin-settings', $variables);
    }

    public function getFormSettingsHtml($form): string
    {
        $variables = $this->getFormSettingsHtmlVariables($form);

        return Craft::$app->getView()->renderTemplate('formie-zabun/integrations/crm/zabun-360/_form-settings', $variables);
    }

    public function fetchFormSettings()
    {
        $settings = [];

        try {
            $settings['contactMe'] = [
                new IntegrationField([
                    'handle' => 'firstName',
                    'name' => Craft::t('formie', 'First Name'),
                ]),
                new IntegrationField([
                    'handle' => 'lastName',
                    'name' => Craft::t('formie', 'Last Name'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'email',
                    'name' => Craft::t('formie', 'Email'),
                ]),
                new IntegrationField([
                    'handle' => 'phone',
                    'name' => Craft::t('formie', 'Phone'),
                ]),
                new IntegrationField([
                    'handle' => 'cellPhone',
                    'name' => Craft::t('formie', 'Mobile'),
                ]),
                new IntegrationField([
                    'handle' => 'comments',
                    'name' => Craft::t('formie', 'Comments'),
                ]),
                new IntegrationField([
                    'handle' => 'publicationID',
                    'name' => Craft::t('formie', 'Publication ID'),
                ]),
                new IntegrationField([
                    'handle' => 'street',
                    'name' => Craft::t('formie', 'Street'),
                ]),
                new IntegrationField([
                    'handle' => 'houseNumber',
                    'name' => Craft::t('formie', 'House Number'),
                ]),
                new IntegrationField([
                    'handle' => 'houseNumberExtension',
                    'name' => Craft::t('formie', 'House Number Extension'),
                ]),
                new IntegrationField([
                    'handle' => 'zipCode',
                    'name' => Craft::t('formie', 'Zip Code'),
                ]),
                new IntegrationField([
                    'handle' => 'city',
                    'name' => Craft::t('formie', 'City'),
                ]),
                new IntegrationField([
                    'handle' => 'originator',
                    'name' => Craft::t('formie', 'Originator'),
                ]),
            ];
        } catch (Throwable $e) {
            Integration::apiError($this, $e);
        }

        return new IntegrationFormSettings($settings);
    }

    public function sendPayload(Submission $submission): bool
    {
        if (!$this->mapToContactMe) {
            return true;
        }

        try {
            // Pass the fields themselves rather than their group name: only then do the field mapping events
            // know which contact field (e.g. zipCode or city) a value is for. They're static, so build them
            // instead of reading Formie's cache, which stays empty until someone refreshes the integration.
            $fields = $this->fetchFormSettings()->getSettingsByKey('contactMe');
            $values = $this->getFieldMappingValues($submission, $this->contactMeFieldMapping, $fields);
            $contactMe = $this->_buildContactMe($submission, $values);

            $response = $this->deliverPayload($submission, self::API_CONTACT_MES, [
                'contactMes' => [$contactMe],
            ]);
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        // Cancelled by an event
        if ($response === false) {
            return true;
        }

        $invalid = is_array($response) ? ($response['invalidContactMes'] ?? []) : [];

        if (!empty($invalid)) {
            // Only what WebLink objected to: the contact itself is personal data, and stays in the submission
            Integration::error($this, Craft::t('formie', 'Zabun 360 refused the contact of submission {id}: {errors}', [
                'id' => $submission->id,
                'errors' => json_encode(array_map(fn($contact) => array_diff_key((array)$contact, array_flip(self::PERSONAL_FIELDS)), $invalid)),
            ]), true);

            return false;
        }

        return true;
    }

    public function fetchConnection(): bool
    {
        try {
            $this->request('GET', self::API_CONTACT_INFO);
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
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Sends the API key with each request rather than in the client's config, which Formie stores with a failed
     * queue job. A response that isn't JSON doesn't fail a request WebLink already accepted.
     */
    public function request(string $method, string $uri, array $options = []): mixed
    {
        $options['headers']['X-API-Key'] = App::parseEnv($this->apiKey);

        $response = $this->getClient()->request($method, ltrim($uri, '/'), $options);

        return Json::decodeIfJson($response->getBody()->getContents());
    }

    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['apiKey'], 'required'];

        // The fields are static: don't depend on Formie's cache, which stays empty until the integration is refreshed
        $contactMe = $this->fetchFormSettings()->getSettingsByKey('contactMe');

        $rules[] = [
            ['contactMeFieldMapping'], 'validateFieldMapping', 'params' => $contactMe,
            'when' => function($model) {
                return $model->enabled && $model->mapToContactMe;
            }, 'on' => [Integration::SCENARIO_FORM],
        ];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    private function _buildContactMe(Submission $submission, array $values): array
    {
        $contactMe = [];

        foreach ($values as $handle => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, fn($item) => is_scalar($item) && $item !== ''));
            }

            if ($value === null || $value === '') {
                continue;
            }

            $contactMe[$handle] = $value;
        }

        // WebLink types these as integers, and refuses the whole contact when they aren't
        foreach (['publicationID', 'houseNumber'] as $handle) {
            if (!isset($contactMe[$handle])) {
                continue;
            }

            if (is_numeric($contactMe[$handle])) {
                $contactMe[$handle] = (int)$contactMe[$handle];
            } else {
                unset($contactMe[$handle]);
            }
        }

        if ($this->appendFieldsToComments) {
            $summary = $this->_getUnmappedFieldsSummary($submission);

            if ($summary !== '') {
                $contactMe['comments'] = trim(($contactMe['comments'] ?? '') . "\n\n" . $summary);
            }
        }

        $contactMe['externalReference'] = (string)$submission->uid;
        $contactMe['originator'] ??= App::parseEnv($this->originator) ?: 'Website';

        $officeId = App::parseEnv($this->officeId);

        if (is_numeric($officeId)) {
            $contactMe['officeId'] = (int)$officeId;
        }

        return $contactMe;
    }

    /**
     * The form's name, then "Label: value" lines for the fields that aren't mapped to a contact field,
     * e.g. search preferences.
     */
    private function _getUnmappedFieldsSummary(Submission $submission): string
    {
        $mappedHandles = [];

        foreach (array_filter($this->contactMeFieldMapping ?? []) as $fieldKey) {
            if (preg_match('/^\{field:([^.}]+)/', (string)$fieldKey, $matches)) {
                $mappedHandles[] = $matches[1];
            }
        }

        // Integrations run from the queue, in the console's language: use the site's
        $language = $submission->getSite()?->language;
        $lines = [Craft::t('formie', 'Form', [], $language) . ': ' . $submission->getForm()?->title];

        foreach ($submission->getFields() as $field) {
            if (!$this->_isSummaryField($field, $submission) || in_array($field->handle, $mappedHandles, true)) {
                continue;
            }

            $rawValue = $submission->getFieldValue($field->handle);

            // An unticked optional checkbox says nothing
            if ($field instanceof Agree && !$rawValue) {
                continue;
            }

            $value = $this->_getOptionLabels($rawValue) ?? $field->getValueAsString($rawValue, $submission);

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $value = trim((string)$value);

            if ($value === '') {
                continue;
            }

            $lines[] = $field->label . ': ' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * Dropdowns, radios and checkboxes read better by their labels ("Te koop") than their values ("1").
     */
    private function _getOptionLabels(mixed $value): ?string
    {
        if ($value instanceof OptionData) {
            return $value->label;
        }

        if ($value instanceof MultiOptionsFieldData) {
            $labels = [];

            foreach ($value as $option) {
                if ($option instanceof OptionData && $option->label !== null) {
                    $labels[] = $option->label;
                }
            }

            return implode(', ', $labels);
        }

        return null;
    }

    private function _isSummaryField(FieldInterface $field, Submission $submission): bool
    {
        // Layout fields, fields the visitor didn't see, and values that are sensitive (passwords, payments, the
        // hidden addresses of a recipients field) or unreadable (a signature image)
        if ($field instanceof Hidden || $field->getIsCosmetic() || $field->getIsHidden() ||
            $field instanceof Password || $field instanceof Payment || $field instanceof Recipients ||
            $field instanceof Signature || $field->isConditionallyHidden($submission)) {
            return false;
        }

        // A required checkbox is consent (privacy policy), not information for the agent
        if ($field instanceof Agree && $field->required) {
            return false;
        }

        return true;
    }
}
