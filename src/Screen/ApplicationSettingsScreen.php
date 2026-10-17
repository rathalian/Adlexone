<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Rules;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\support\Database;
use Adlexone\Http\Page;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class ApplicationSettingsScreen
{
    /**
     * Settings for the application that is open: default record type and rules.
     */
    public static function open(): void
    {
        $slug = ApplicationAccess::slug();
        if ($slug === '') {
            View::respond('Open settings from inside an application.');
            return;
        }
        if (!ApplicationAccess::canSettings()) {
            \Adlexone\Auth\Access::deny();
        }

        switch ((string) ($_GET['option'] ?? 'settings')) {
            case 'update_settings':
                self::save($slug);
                break;
            case 'new_rule':
                self::showRuleForm($slug, null);
                break;
            case 'edit_rule':
                self::showRuleForm($slug, (int) ($_GET['rule_id'] ?? 0));
                break;
            case 'add_rule':
                self::saveRule($slug, 0);
                break;
            case 'update_rule':
                self::saveRule($slug, (int) ($_POST['rule_id'] ?? 0));
                break;
            case 'delete_rule':
                self::deleteRule($slug);
                break;
            case 'toggle_rule':
                self::toggleRule($slug);
                break;
            case 'duplicate_rule':
                self::duplicateRule($slug);
                break;
            case 'move_rule':
                self::moveRule($slug);
                break;
            case 'try_rule':
                self::tryRule($slug, false);
                break;
            case 'run_rule':
                self::tryRule($slug, true);
                break;
            case 'settings':
            default:
                self::show($slug);
                break;
        }
    }

    public static function show(string $slug): void
    {
        $valueArray = [''];
        $displayArray = ['— none —'];
        foreach (Database::select('item_types', ['item_type_id', 'item_type_name'], '', [], 'item_type_name ASC') as $row) {
            $valueArray[] = (string) $row['item_type_id'];
            $displayArray[] = (string) $row['item_type_name'];
        }
        $current = ApplicationSettings::defaultItemTypeId($slug);

        $html = Form::form(
            'Application settings',
            MAN_BASE_URL . '&option=update_settings',
            [
                'New work' => Form::select('default_item_type_id', $valueArray, $displayArray, $current),
            ],
            [
                Form::button('submit', 'submit_button', 'Save'),
                Form::button('reset', 'reset', 'Reset'),
            ]
        );
        $html .= self::rulesHtml($slug);
        Page::current()->setBody($html);
    }

    public static function rulesFormAction(string $slug, string $option): string
    {
        $base = defined('MAN_BASE_URL') ? MAN_BASE_URL : ('?application=' . rawurlencode($slug));
        return $base . '&option=' . $option . '&app=' . rawurlencode($slug);
    }

    public static function rulesHomeUrl(string $slug): string
    {
        if (defined('RULES_HOME_URL')) {
            return RULES_HOME_URL;
        }
        $base = defined('MAN_BASE_URL') ? MAN_BASE_URL : ('?application=' . rawurlencode($slug));
        return $base . '&option=settings';
    }

    public static function rulesHtml(string $slug): string
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            return '';
        }
        $applicationId = (int) $app['application_id'];
        $typeIds = ApplicationSettings::allowedItemTypeIds($slug);
        $fields = Rules::fieldChoices($typeIds);
        $typeNames = self::ruleTypeNames($typeIds);

        $grouped = [];
        foreach (Rules::forApplication($applicationId) as $rule) {
            $typeId = (int) $rule['item_type_id'];
            $grouped[$typeId][] = $rule;
        }

        $listGroups = [];
        foreach ($grouped as $typeId => $rows) {
            $listRows = [];
            foreach ($rows as $rule) {
                $name = (string) $rule['name'];
                $id = (int) $rule['id'];
                $description = Rules::summary($rule, $fields);
                if ((int) $rule['enabled'] !== 1) {
                    $description = 'Off. ' . $description;
                }
                $listRows[] = [
                    'name' => $name,
                    'href' => self::rulesFormAction($slug, 'edit_rule') . '&rule_id=' . $id,
                    'meta' => (int) $rule['enabled'] === 1 ? 'On' : 'Off',
                    'cells' => [
                        'description' => $description,
                    ],
                    'search' => $description,
                    'actions' => [
                        [
                            'href' => self::rulesFormAction($slug, 'try_rule') . '&rule_id=' . $id,
                            'label' => 'Try',
                        ],
                        [
                            'href' => self::rulesFormAction($slug, 'toggle_rule') . '&rule_id=' . $id,
                            'label' => (int) $rule['enabled'] === 1 ? 'Turn off' : 'Turn on',
                        ],
                        [
                            'href' => self::rulesFormAction($slug, 'duplicate_rule') . '&rule_id=' . $id,
                            'label' => 'Duplicate',
                        ],
                        [
                            'href' => self::rulesFormAction($slug, 'move_rule') . '&rule_id=' . $id . '&dir=up',
                            'label' => 'Move up',
                        ],
                        [
                            'href' => self::rulesFormAction($slug, 'move_rule') . '&rule_id=' . $id . '&dir=down',
                            'label' => 'Move down',
                        ],
                        [
                            'href' => self::rulesFormAction($slug, 'delete_rule') . '&rule_id=' . $id,
                            'label' => TXT_315,
                            'tone' => 'danger',
                            'confirm' => $name . "\n" . TXT_400,
                        ],
                    ],
                ];
            }
            $label = $typeNames[$typeId] ?? '';
            if ($label === '') {
                $type = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [(int) $typeId]);
                $label = (string) ($type['item_type_name'] ?? ('Type ' . $typeId));
            }
            $listGroups[] = ['label' => $label, 'rows' => $listRows];
        }

        $list = Blocks::recordList([
            'column' => 'Rule',
            'columns' => [
                ['key' => 'description', 'label' => 'What it does', 'wrap' => true],
            ],
            'searchLabel' => TXT_3,
            'primary' => $typeIds === [] ? null : [
                'href' => self::rulesFormAction($slug, 'new_rule'),
                'label' => 'Add a rule',
            ],
            'empty' => 'No rules yet.',
            'noMatch' => TXT_688,
            'groups' => $listGroups,
        ]);

        return Blocks::vertical([[
            'title' => 'Rules',
            'html' => $list . '<p class="record-list__meta">Rules run from top to bottom. Put “set the owner” above “email the owner”.</p>',
        ]]);
    }

    /**
     * @param list<int> $typeIds
     * @return array<int, string>
     */
    public static function ruleTypeNames(array $typeIds): array
    {
        $names = [];
        foreach ($typeIds as $typeId) {
            $type = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [(int) $typeId]);
            $names[(int) $typeId] = (string) ($type['item_type_name'] ?? ('Type ' . $typeId));
        }
        return $names;
    }

    public static function showRuleForm(string $slug, ?int $ruleId): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        $applicationId = (int) $app['application_id'];
        $rule = null;
        if ($ruleId !== null && $ruleId > 0) {
            $rule = Rules::find($ruleId, $applicationId);
            if ($rule === null) {
                View::respond('That rule is not on this application.');
                return;
            }
        }

        $typeIds = ApplicationSettings::allowedItemTypeIds($slug);
        $typeNames = self::ruleTypeNames($typeIds);
        if ($rule !== null && !isset($typeNames[(int) $rule['item_type_id']])) {
            $typeId = (int) $rule['item_type_id'];
            $type = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);
            $typeNames[$typeId] = (string) ($type['item_type_name'] ?? ('Type ' . $typeId));
            $typeIds[] = $typeId;
        }
        if ($typeNames === []) {
            View::respond('Add a record type before creating a rule.');
            return;
        }

        $editing = $rule !== null;
        if (!is_array($rule)) {
            $rule = [];
        }
        $selectedType = (string) ($rule['item_type_id'] ?? (string) ($typeIds[0] ?? ''));
        $catalog = [];
        foreach ($typeIds as $typeId) {
            $catalog[(string) $typeId] = Rules::fieldCatalog((int) $typeId);
        }
        $fields = $catalog[$selectedType] ?? Rules::fieldCatalog((int) $selectedType);
        $enabled = $editing && (int) $rule['enabled'] !== 1 ? 'No' : 'Yes';
        $emailActor = $editing && (int) ($rule['skip_actor'] ?? 1) === 0 ? 'Yes' : 'No';
        $steps = is_array($rule['steps'] ?? null) ? $rule['steps'] : [];

        $formFields = [
            'Name' => self::ruleText('name', (string) ($rule['name'] ?? ''), 'Email the owner when priority is high'),
            'Enabled' => self::ruleSelect('enabled', ['Yes' => 'Yes', 'No' => 'No'], $enabled),
            'Record type' => self::ruleSelect('item_type_id', $typeNames, $selectedType, 'rule-type'),
            'When' => self::ruleSelect('event', Rules::events(), (string) ($rule['event'] ?? Rules::EVENT_UPDATE), 'rule-event')
                . '<span class="rule-log-role"> ' . self::ruleSelect('min_log_role', Rules::logRoles(), (string) (int) ($rule['min_log_role'] ?? 0)) . '</span>',
            'If' => self::ruleSelect('operator', Rules::operators(), (string) ($rule['operator'] ?? Rules::OP_BECOMES), 'rule-operator')
                . ' <span class="rule-field">' . self::ruleFieldSelect('field_key', $fields, (string) ($rule['field_key'] ?? ''), true) . '</span>'
                . ' <span class="rule-from">from ' . self::ruleValueControl('match_from', $fields, (string) ($rule['field_key'] ?? ''), (string) ($rule['match_from'] ?? '')) . '</span>'
                . ' <span class="rule-value">' . self::ruleValueControl('match_value', $fields, (string) ($rule['field_key'] ?? ''), (string) ($rule['match_value'] ?? '')) . '</span>',
            'Also email the person who saved' => self::ruleSelect('email_actor', ['No' => 'No', 'Yes' => 'Yes'], $emailActor),
            'Then' => self::ruleStepsHtml($steps, $fields),
        ];
        if ($editing) {
            $formFields[''] = Form::hidden('rule_id', (string) $rule['id']);
        }

        $html = Form::form(
            $editing ? 'Edit rule' : 'Add a rule',
            self::rulesFormAction($slug, $editing ? 'update_rule' : 'add_rule'),
            $formFields,
            [
                Form::button('submit', 'submit_button', $editing ? 'Save' : 'Add rule'),
                Form::button('button', '', 'Back', 'onclick="location.href=\'' . Html::e(self::rulesHomeUrl($slug)) . '\'"', 'secondary'),
            ]
        );
        $html .= self::ruleEditorScript($catalog);
        if ($editing) {
            $html .= self::ruleRunsHtml($slug, $rule);
        }
        Page::current()->setBody($html);
    }

    /**
     * @param array<int|string, string> $options
     */
    public static function ruleSelect(string $name, array $options, string $selected, string $class = ''): string
    {
        $html = '<select name="' . Html::e($name) . '" class="select ' . Html::e($class) . '">';
        foreach ($options as $value => $label) {
            $current = (string) $value === $selected ? ' selected' : '';
            $html .= '<option value="' . Html::e((string) $value) . '"' . $current . '>'
                . Html::e((string) $label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function ruleText(string $name, string $value, string $placeholder = ''): string
    {
        return '<input type="text" class="input rule-token-target" name="' . Html::e($name)
            . '" value="' . Html::e($value)
            . '" placeholder="' . Html::e($placeholder) . '">';
    }

    public static function ruleArea(string $name, string $value): string
    {
        return '<textarea class="input rule-token-target" name="' . Html::e($name)
            . '" rows="4">' . Html::e($value) . '</textarea>';
    }

    /**
     * @param list<array{key: string, label: string, kind: string, options: list<array{value: string, label: string}>}> $fields
     */
    public static function ruleFieldSelect(string $name, array $fields, string $selected, bool $includeBlank): string
    {
        $options = [];
        if ($includeBlank) {
            $options[''] = 'Any record';
        }
        foreach ($fields as $field) {
            if (!$includeBlank && in_array($field['key'], ['owner', 'creator', 'groups'], true)) {
                continue;
            }
            $options[$field['key']] = $field['label'];
        }
        return self::ruleSelect($name, $options, $selected, 'rule-field-select');
    }

    /**
     * @param list<array{key: string, label: string, kind: string, options: list<array{value: string, label: string}>}> $fields
     */
    public static function ruleValueControl(string $name, array $fields, string $fieldKey, string $value): string
    {
        $options = [];
        foreach ($fields as $field) {
            if ($field['key'] === $fieldKey) {
                foreach ($field['options'] as $option) {
                    $options[$option['value']] = $option['label'];
                }
                break;
            }
        }
        $text = self::ruleText($name, $value, 'High');
        if ($options === []) {
            return $text;
        }
        if (!isset($options[$value]) && $value !== '') {
            $options[$value] = $value;
        }
        return '<span class="rule-value-choice">' . self::ruleSelect($name . '_choice', $options, $value, 'rule-value-select') . '</span>'
            . '<span class="rule-value-text" hidden>' . $text . '</span>';
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param list<array{key: string, label: string, kind: string, options: list<array{value: string, label: string}>}> $fields
     */
    public static function ruleStepsHtml(array $steps, array $fields): string
    {
        $html = '<div class="rule-steps">';
        for ($i = 0; $i < 4; $i++) {
            $step = $steps[$i] ?? [];
            $outcome = (string) ($step['outcome'] ?? ($i === 0 ? Rules::OUTCOME_EMAIL : ''));
            $outcomes = $i === 0 ? Rules::outcomes() : ['' => 'No further action'] + Rules::outcomes();
            $people = [];
            $groups = [];
            foreach ($fields as $field) {
                if ($field['key'] === 'owner') {
                    foreach ($field['options'] as $option) {
                        $people[$option['value']] = $option['label'];
                    }
                }
                if ($field['key'] === 'groups') {
                    foreach ($field['options'] as $option) {
                        $groups[$option['value']] = $option['label'];
                    }
                }
            }
            $prefix = 'steps[' . $i . ']';
            $html .= '<div class="rule-step" data-step="' . $i . '">';
            $html .= '<div class="rule-step-head">Action ' . ($i + 1) . ' ' . self::ruleSelect($prefix . '[outcome]', $outcomes, $outcome, 'rule-outcome') . '</div>';
            $html .= '<div class="rule-step-panel" data-for="email">'
                . '<div>To ' . self::ruleSelect($prefix . '[recipient]', Rules::recipients(), (string) ($step['recipient'] ?? Rules::TO_OWNER), 'rule-recipient') . '</div>'
                . '<div class="rule-recipient-user">Person ' . self::ruleSelect($prefix . '[user_id]', $people, (string) (int) ($step['user_id'] ?? 0)) . '</div>'
                . '<div class="rule-recipient-group">Group ' . self::ruleSelect($prefix . '[group_id]', $groups, (string) (int) ($step['group_id'] ?? 0)) . '</div>'
                . '<div class="rule-recipient-address">Address ' . self::ruleText($prefix . '[address]', (string) ($step['address'] ?? ''), 'name@example.com') . '</div>'
                . '<div>Subject ' . self::ruleText($prefix . '[subject]', (string) ($step['subject'] ?? ''), 'ITEM_TITLE') . '</div>'
                . '<div>Message ' . self::ruleArea($prefix . '[body]', (string) ($step['body'] ?? '')) . '</div>'
                . '</div>';
            $html .= '<div class="rule-step-panel" data-for="log">'
                . '<div>Message ' . self::ruleArea($prefix . '[body]', (string) ($step['body'] ?? '')) . '</div>'
                . '<div>Visible to ' . self::ruleSelect($prefix . '[role_id]', [5 => 'Everyone', 4 => 'Users', 3 => 'Managers', 2 => 'Administrators'], (string) (int) ($step['role_id'] ?? 5)) . '</div>'
                . '</div>';
            $html .= '<div class="rule-step-panel" data-for="set_field">'
                . '<div>Field ' . self::ruleFieldSelect($prefix . '[action_field]', $fields, (string) ($step['action_field'] ?? ''), false) . '</div>'
                . '<div>Value ' . self::ruleText($prefix . '[action_value]', (string) ($step['action_value'] ?? ''), 'Closed') . '</div>'
                . '</div>';
            $owners = Rules::ownerChoices();
            $html .= '<div class="rule-step-panel" data-for="set_owner">'
                . '<div>Owner ' . self::ruleSelect($prefix . '[owner_id]', $owners, (string) ($step['action_value'] ?? Rules::OWNER_CREATOR)) . '</div>'
                . '</div>';
            $html .= '</div>';
        }
        $html .= '<div class="rule-tokens"><span class="record-list__meta">Insert </span><span class="rule-token-list"></span></div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $catalog
     */
    public static function ruleEditorScript(array $catalog): string
    {
        $json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($json)) {
            $json = '{}';
        }
        return '<script>
    (function () {
        var catalog = ' . $json . ';
        var form = document.querySelector("form");
        if (!form) return;
        var type = form.querySelector(".rule-type");
        var operator = form.querySelector(".rule-operator");
        var eventSel = form.querySelector(".rule-event");
        var fieldWrap = form.querySelector(".rule-field");
        var fromWrap = form.querySelector(".rule-from");
        var valueWrap = form.querySelector(".rule-value");
        var logRole = form.querySelector(".rule-log-role");
        function optionsFor(key) {
            var fields = catalog[type ? type.value : ""] || [];
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].key === key) return fields[i].options || [];
            }
            return [];
        }
        function fillSelect(select, rows, blank) {
            if (!select) return;
            var current = select.value;
            select.innerHTML = "";
            if (blank) {
                var any = document.createElement("option");
                any.value = "";
                any.textContent = "Any record";
                select.appendChild(any);
            }
            rows.forEach(function (row) {
                var opt = document.createElement("option");
                opt.value = row.key || row.value;
                opt.textContent = row.label;
                select.appendChild(opt);
            });
            if (current) select.value = current;
        }
        function syncMatch() {
            var op = operator ? operator.value : "always";
            var hideValue = op === "always" || op === "empty" || op === "not_empty" || op === "changed";
            if (fieldWrap) fieldWrap.hidden = op === "always";
            if (fromWrap) fromWrap.hidden = op !== "from_to";
            if (valueWrap) valueWrap.hidden = hideValue;
            if (logRole) logRole.hidden = !eventSel || eventSel.value !== "log";
            ["match_from", "match_value"].forEach(function (name) {
                var wrap = name === "match_from" ? fromWrap : valueWrap;
                if (!wrap) return;
                var select = wrap.querySelector("select");
                var text = wrap.querySelector("input");
                var choice = wrap.querySelector(".rule-value-choice");
                var textWrap = wrap.querySelector(".rule-value-text");
                var key = form.querySelector("[name=field_key]");
                var opts = optionsFor(key ? key.value : "");
                if (select && opts.length) {
                    fillSelect(select, opts, false);
                    if (choice) choice.hidden = false;
                    if (textWrap) textWrap.hidden = true;
                } else {
                    if (choice) choice.hidden = true;
                    if (textWrap) textWrap.hidden = false;
                }
                if (select && text && choice && !choice.hidden) text.value = select.value;
            });
        }
        function syncSteps() {
            form.querySelectorAll(".rule-step").forEach(function (step) {
                var outcome = step.querySelector(".rule-outcome");
                var value = outcome ? outcome.value : "";
                step.querySelectorAll(".rule-step-panel").forEach(function (panel) {
                    var hide = panel.getAttribute("data-for") !== value;
                    panel.hidden = hide;
                    panel.querySelectorAll("input, textarea, select").forEach(function (el) {
                        el.disabled = hide;
                    });
                });
                var recipient = step.querySelector(".rule-recipient");
                var kind = recipient ? recipient.value : "";
                var user = step.querySelector(".rule-recipient-user");
                var group = step.querySelector(".rule-recipient-group");
                var address = step.querySelector(".rule-recipient-address");
                if (user) user.hidden = kind !== "user";
                if (group) group.hidden = kind !== "group";
                if (address) address.hidden = kind !== "address";
            });
        }
        function syncTokens() {
            var host = form.querySelector(".rule-token-list");
            if (!host) return;
            host.innerHTML = "";
            var tokens = [
                ["ITEM_ID", "Record id"],
                ["ITEM_TITLE", "Title"],
                ["ITEM_CREATOR", "Creator"],
                ["ITEM_OWNER", "Owner"]
            ];
            (catalog[type ? type.value : ""] || []).forEach(function (field) {
                var match = /^custom_field_(\d+)$/.exec(field.key || "");
                if (match) tokens.push(["CUSTOM_FIELD_" + match[1], field.label]);
            });
            tokens.forEach(function (token) {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "btn btn--sm";
                button.textContent = token[1];
                button.addEventListener("click", function () {
                    var target = form.querySelector(".rule-token-target:focus") || form.querySelector("[name=\'steps[0][body]\']");
                    if (!target) return;
                    var start = target.selectionStart || target.value.length;
                    var end = target.selectionEnd || start;
                    target.value = target.value.slice(0, start) + token[0] + target.value.slice(end);
                    target.focus();
                });
                host.appendChild(button);
                host.appendChild(document.createTextNode(" "));
            });
        }
        function syncFields() {
            var rows = catalog[type ? type.value : ""] || [];
            fillSelect(form.querySelector("[name=field_key]"), rows, true);
            form.querySelectorAll("[name$=\'[action_field]\']").forEach(function (select) {
                var keep = [];
                rows.forEach(function (row) {
                    if (row.key === "owner" || row.key === "creator" || row.key === "groups") return;
                    keep.push(row);
                });
                fillSelect(select, keep, false);
            });
            syncMatch();
            syncTokens();
        }
        form.addEventListener("change", function (event) {
            var name = event.target && event.target.name;
            if (name === "item_type_id") syncFields();
            if (name === "operator" || name === "field_key" || name === "event" || name === "match_value_choice" || name === "match_from_choice") syncMatch();
            if (event.target && (event.target.classList.contains("rule-outcome") || event.target.classList.contains("rule-recipient"))) syncSteps();
        });
        form.addEventListener("submit", function () {
            ["match_from", "match_value"].forEach(function (name) {
                var choice = form.querySelector("[name=\'" + name + "_choice\']");
                var text = form.querySelector("[name=\'" + name + "\']");
                var wrap = name === "match_from" ? fromWrap : valueWrap;
                var choiceWrap = wrap ? wrap.querySelector(".rule-value-choice") : null;
                if (choice && text && choiceWrap && !choiceWrap.hidden) text.value = choice.value;
            });
        });
        syncFields();
        syncSteps();
    })();
    </script>';
    }

    /**
     * @param array<string, mixed> $rule
     */
    public static function ruleRunsHtml(string $slug, array $rule): string
    {
        $runs = Rules::recentRuns((int) $rule['id'], 12);
        $html = '<p><a class="btn btn--sm" href="' . Html::e(self::rulesFormAction($slug, 'try_rule') . '&rule_id=' . (int) $rule['id']) . '">Try this rule</a></p>';
        if ($runs === []) {
            $html .= '<p class="record-list__empty">This rule has not run yet.</p>';
        } else {
            foreach ($runs as $run) {
                $when = date('j M Y, g:i a', (int) ($run['ran_at'] ?? time()));
                $html .= '<div class="log-item"><div class="log-heading"><strong>'
                    . Html::e($when)
                    . ' — record ' . (int) ($run['item_id'] ?? 0)
                    . '</strong></div><div class="log-text">'
                    . nl2br(Html::e((string) ($run['detail'] ?? '')))
                    . '</div></div>';
            }
        }
        return Blocks::vertical([['title' => 'Recent runs', 'html' => $html]]);
    }

    public static function tryRule(string $slug, bool $execute): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        $applicationId = (int) $app['application_id'];
        $ruleId = (int) ($_GET['rule_id'] ?? $_POST['rule_id'] ?? 0);
        $rule = Rules::find($ruleId, $applicationId);
        if ($rule === null) {
            View::respond('That rule is not on this application.');
            return;
        }
        $itemId = (int) ($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
        $result = null;
        if ($itemId > 0 && $execute && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $result = Rules::executeOne($ruleId, $applicationId, $itemId);
        } elseif ($itemId > 0) {
            $result = Rules::preview($ruleId, $applicationId, $itemId);
        }
        $items = Database::select(
            'items',
            ['item_id', 'item_title'],
            'item_type_id = ?',
            [(int) $rule['item_type_id']],
            'item_id DESC',
            25
        );
        $ids = [''];
        $labels = ['Choose a record'];
        foreach ($items as $item) {
            $ids[] = (string) $item['item_id'];
            $labels[] = (string) $item['item_id'] . ' — ' . (string) $item['item_title'];
        }
        $fields = Rules::fieldChoices([(int) $rule['item_type_id']]);
        $html = '<p>' . Html::e(Rules::summary($rule, $fields)) . '</p>';
        $html .= Form::form(
            'Try this rule',
            self::rulesFormAction($slug, 'try_rule') . '&rule_id=' . $ruleId,
            [
                'Record' => Form::select('item_id', $ids, $labels, (string) $itemId),
                '' => Form::hidden('rule_id', (string) $ruleId),
            ],
            [
                Form::button('submit', 'submit_button', 'Preview'),
                Form::button('button', '', 'Back', 'onclick="location.href=\'' . Html::e(self::rulesFormAction($slug, 'edit_rule') . '&rule_id=' . $ruleId) . '\'"', 'secondary'),
            ]
        );
        if (is_array($result)) {
            $html .= '<p><strong>' . Html::e((string) ($result['reason'] ?? '')) . '</strong></p>';
            foreach ($result['steps'] ?? [] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $html .= '<p>' . Html::e((string) ($step['detail'] ?? '')) . '</p>';
            }
            if (($result['matches'] ?? false) === true && ($result['ran'] ?? false) !== true) {
                $html .= '<form method="post" action="' . Html::e(self::rulesFormAction($slug, 'run_rule') . '&rule_id=' . $ruleId) . '">'
                    . Form::hidden('rule_id', (string) $ruleId)
                    . Form::hidden('item_id', (string) $itemId)
                    . Form::button('submit', 'submit_button', 'Run it now')
                    . '</form>';
            }
        }
        Page::current()->setBody($html);
    }

    public static function ruleTypeAllowed(string $slug, int $typeId, int $ruleId): bool
    {
        if (ApplicationSettings::allowsItemType($slug, $typeId)) {
            return true;
        }
        if ($typeId <= 0 || Database::first('item_types', ['item_type_id'], 'item_type_id = ?', [$typeId]) === null) {
            return false;
        }
        if ($ruleId > 0) {
            $app = ApplicationStore::findBySlug($slug);
            $existing = $app === null ? null : Rules::find($ruleId, (int) $app['application_id']);
            if ($existing !== null && (int) $existing['item_type_id'] === $typeId) {
                return true;
            }
        }
        return defined('RULES_HOME_URL');
    }

    public static function saveRule(string $slug, int $ruleId): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        $typeId = (int) ($_POST['item_type_id'] ?? 0);
        if (!self::ruleTypeAllowed($slug, $typeId, $ruleId)) {
            \Adlexone\Auth\Access::deny();
        }
        $applicationId = (int) $app['application_id'];
        if ($ruleId > 0) {
            Rules::update($ruleId, $applicationId, $_POST);
        } else {
            Rules::add($applicationId, $_POST);
        }
        header('Location: ' . self::rulesHomeUrl($slug));
        exit;
    }

    public static function deleteRule(string $slug): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        Rules::delete((int) ($_GET['rule_id'] ?? 0), (int) $app['application_id']);
        header('Location: ' . self::rulesHomeUrl($slug));
        exit;
    }

    public static function toggleRule(string $slug): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        $rule = Rules::find((int) ($_GET['rule_id'] ?? 0), (int) $app['application_id']);
        if ($rule !== null) {
            Rules::setEnabled((int) $rule['id'], (int) $app['application_id'], (int) $rule['enabled'] !== 1);
        }
        header('Location: ' . self::rulesHomeUrl($slug));
        exit;
    }

    public static function duplicateRule(string $slug): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        $id = Rules::duplicate((int) ($_GET['rule_id'] ?? 0), (int) $app['application_id']);
        $target = $id > 0
            ? self::rulesFormAction($slug, 'edit_rule') . '&rule_id=' . $id
            : self::rulesHomeUrl($slug);
        header('Location: ' . $target);
        exit;
    }

    public static function moveRule(string $slug): void
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            View::respond('Open settings from inside an application.');
            return;
        }
        Rules::move((int) ($_GET['rule_id'] ?? 0), (int) $app['application_id'], (string) ($_GET['dir'] ?? 'down'));
        header('Location: ' . self::rulesHomeUrl($slug));
        exit;
    }

    public static function save(string $slug): void
    {
        $settings = ApplicationSettings::get($slug);
        $settings['default_item_type_id'] = trim((string) ($_POST['default_item_type_id'] ?? ''));
        ApplicationSettings::put($slug, $settings);
        header('Location: ' . MAN_BASE_URL . '&option=settings');
        exit;
    }
}
