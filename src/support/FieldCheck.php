<?php
declare(strict_types=1);

namespace Adlexone\support;

use Adlexone\Html\Form;

/**
 * Builds the data-field-check attribute used on item forms.
 */
final class FieldCheck
{
    /** @var list<string> */
    private array $types = [];
    /** @var list<string> */
    private array $names = [];
    /** @var list<string> */
    private array $errors = [];
    /** @var list<string> */
    private array $requiredMessages = [];
    /** @var list<bool> */
    private array $required = [];

    /**
     * @param array<string, mixed> $field
     */
    public function add(array $field, string $inputName): void
    {
        $kind = FieldValidation::kind($field);
        $isRequired = strcasecmp((string) ($field['required'] ?? ''), 'Yes') === 0;
        if (!$isRequired && $kind === '') {
            return;
        }
        $label = (string) ($field['custom_field_name'] ?? '');
        $this->types[] = FieldValidation::jsType($kind);
        $this->names[] = $inputName;
        $this->errors[] = $kind === '' ? '' : FieldValidation::message($kind, $label);
        $this->requiredMessages[] = $isRequired ? FieldValidation::requiredMessage($label) : '';
        $this->required[] = $isRequired;
    }

    public function withTitle(): self
    {
        $this->types[] = '';
        $this->names[] = 'item_title';
        $this->errors[] = '';
        $this->requiredMessages[] = defined('TXT_476') ? (string) TXT_476 : 'Enter a name.';
        $this->required[] = true;
        return $this;
    }

    public function onclick(): string
    {
        $header = defined('TXT_468') ? (string) TXT_468 : 'Check the form';

        return Form::fieldCheck(
            $header,
            $this->types,
            $this->names,
            $this->errors,
            $this->requiredMessages,
            $this->required
        );
    }
}
