<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Builds the fieldCheck() onclick used on item forms.
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
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $call = 'return fieldCheck('
            . json_encode($header, $flags) . ','
            . json_encode($this->types, $flags) . ','
            . json_encode($this->names, $flags) . ','
            . json_encode($this->errors, $flags) . ','
            . json_encode($this->requiredMessages, $flags) . ','
            . json_encode($this->required, $flags)
            . ')';
        return 'onclick="' . htmlspecialchars($call, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }
}
