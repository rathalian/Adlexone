<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Permission;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;

/**
 * Guided creation of a business application: record type, fields, and nav.
 */
final class ApplicationBuilder
{
    private static bool $installingShowcase = false;

    public const BLUEPRINT_BLANK = 'blank';
    public const BLUEPRINT_MATTERS = 'matters';
    public const BLUEPRINT_CONVEYANCING = 'conveyancing';
    public const BLUEPRINT_CLAIMS = 'claims';
    public const BLUEPRINT_REPAIRS = 'repairs';
    public const BLUEPRINT_COMPLAINTS = 'complaints';
    public const BLUEPRINT_COMMITTEE = 'committee';
    public const BLUEPRINT_AUDIT = 'audit';
    public const BLUEPRINT_INCIDENTS = 'incidents';
    public const BLUEPRINT_CANDIDATES = 'candidates';
    public const BLUEPRINT_CONTRACTS = 'contracts';

    /**
     * @return array<string, array{label: string, hint: string, record: string, icon: string, rule: string, fields: list<array<string, mixed>>, screens: list<string>}>
     */
    public static function blueprints(): array
    {
        $plain = [ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_ADVANCED, ScreenCatalog::SEARCH_LIST];
        $extra = [
            ScreenCatalog::COUNTS,
            ScreenCatalog::OWNERS,
            ScreenCatalog::STEPS,
            ScreenCatalog::PARTIES,
            ScreenCatalog::ACTIVITY,
            ScreenCatalog::FILES,
            ScreenCatalog::AGE,
            ScreenCatalog::REGISTER,
        ];
        $work = array_merge($plain, $extra);
        $wall = array_merge([ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_ADVANCED, ScreenCatalog::ANNOUNCEMENTS, ScreenCatalog::SEARCH_LIST], $extra);
        $diary = [ScreenCatalog::DIARY];
        $intake = [ScreenCatalog::INTAKE];
        $status = static fn (array $values): array => self::menu('Status', $values);
        $close = static function (string $name, string $status, string $date, string $line): array {
            return self::when($name, Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, $status, [
                self::stamp($date),
                self::logLine($line),
                self::setField('Next step', ''),
            ]);
        };

        return [
            self::BLUEPRINT_MATTERS => self::shape(
                'Matters',
                'Waiting on us, or waiting on them.',
                'Matter',
                'ic-briefcase',
                'Kind',
                'Stamps the closed date when a matter is done.',
                [$status(['Waiting on us', 'Waiting on them', 'Done']), self::text('Client'), self::menu('Kind', ['Contract', 'Dispute', 'Advice', 'Property']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Closed on')],
                [
                    self::when('Note that a matter was opened', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Opened ITEM_TITLE.')]),
                    self::when('Tell the owner a matter is waiting on us', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Waiting on us', [self::email('Waiting on us: ITEM_TITLE', 'ITEM_TITLE is waiting on us.')]),
                    $close('Close a finished matter', 'Done', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                $work,
                self::search('Waiting on us', 'Matters that are with us.', 'Waiting on us')
            ),
            self::BLUEPRINT_CONVEYANCING => self::shape(
                'Conveyancing',
                'A sale or a purchase, from instruction to completion.',
                'Transaction',
                'ic-home',
                'Side',
                'Stamps the completed date when a transaction completes.',
                [$status(['Instructed', 'Searches', 'Enquiries', 'Exchange', 'Complete']), self::text('Client'), self::text('Place'), self::menu('Side', ['Sale', 'Purchase']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Completed on')],
                [
                    self::when('Note the instruction', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Instructed ITEM_TITLE.')]),
                    self::when('Tell the owner enquiries have started', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Enquiries', [self::email('Enquiries: ITEM_TITLE', 'ITEM_TITLE is in enquiries.')]),
                    $close('Complete the transaction', 'Complete', 'Completed on', 'Completed ITEM_TITLE.'),
                ],
                $work,
                self::search('Enquiries', 'Transactions in enquiries.', 'Enquiries')
            ),
            self::BLUEPRINT_CLAIMS => self::shape(
                'Claims',
                'A claim from the first notification through to settlement.',
                'Claim',
                'ic-shield',
                'Insurer',
                'Stamps the settled date and writes the log when a claim is settled.',
                [$status(['Notified', 'With the insurer', 'Waiting on them', 'Settled']), self::text('Client'), self::text('Insurer'), self::menu('Kind', ['Property', 'Motor', 'Liability', 'Travel']), self::text('Reference'), self::number('Reserve'), self::text('Next step'), self::note(), self::date('Settled on')],
                [
                    self::when('Note the notification', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Notified ITEM_TITLE.')]),
                    self::when('Tell the owner the claim is with the insurer', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With the insurer', [
                        self::email('With the insurer: ITEM_TITLE', 'ITEM_TITLE is with the insurer.'),
                        self::logLine('Sent ITEM_TITLE to the insurer.'),
                    ]),
                    $close('Settle the claim', 'Settled', 'Settled on', 'Settled ITEM_TITLE.'),
                    self::when('Note a file was added', Rules::EVENT_ATTACHMENT, '', Rules::OP_ALWAYS, '', [self::logLine('A file was added to ITEM_TITLE.')]),
                ],
                $work,
                self::search('With the insurer', 'Claims sent to the insurer.', 'With the insurer')
            ),
            self::BLUEPRINT_REPAIRS => self::shape(
                'Repairs',
                'A repair from the report through to completion.',
                'Repair',
                'ic-wrench',
                'Trade',
                'Emails the owner when a repair is on site.',
                [$status(['Reported', 'Booked', 'On site', 'Done']), self::text('Place'), self::menu('Trade', ['Plumbing', 'Electrical', 'Joinery', 'Roof', 'Other']), self::text('Reported by'), self::text('Next step'), self::note(), self::check('Urgent'), self::date('Done on')],
                [
                    self::when('Note the report', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Reported ITEM_TITLE.')]),
                    self::when('Tell the owner someone is on site', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'On site', [self::email('On site: ITEM_TITLE', 'ITEM_TITLE is on site.')]),
                    $close('Finish the repair', 'Done', 'Done on', 'Finished ITEM_TITLE.'),
                ],
                array_merge($work, $intake),
                self::search('On site', 'Repairs where someone is there now.', 'On site')
            ),
            self::BLUEPRINT_COMPLAINTS => self::shape(
                'Complaints',
                'A complaint from opening through to closure.',
                'Complaint',
                'ic-complaint',
                'Channel',
                'Posts a notice when a complaint is opened, and stamps the closed date.',
                [$status(['Opened', 'With us', 'Their response', 'Closed']), self::text('Client'), self::menu('Channel', ['Letter', 'Phone', 'Email', 'Web']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Response due'), self::date('Closed on')],
                [
                    self::when('Open the complaint', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::logLine('Opened ITEM_TITLE.'),
                        self::email('Opened: ITEM_TITLE', 'ITEM_TITLE has been opened.'),
                        self::notice('Opened: ITEM_TITLE', 'ITEM_TITLE has been opened.'),
                    ]),
                    self::when('Tell the owner it is with us', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With us', [self::email('With us: ITEM_TITLE', 'ITEM_TITLE is with us.')]),
                    $close('Close the complaint', 'Closed', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                array_merge($wall, $diary, $intake),
                self::search('With us', 'Complaints that are with us.', 'With us')
            ),
            self::BLUEPRINT_COMMITTEE => self::shape(
                'Committee',
                'A paper from receipt through to the decision.',
                'Paper',
                'ic-landmark',
                'Amount',
                'Stamps the decided date and posts a notice when a paper is decided.',
                [$status(['In', 'Reading', 'Asking', 'Decided']), self::text('Applicant'), self::menu('Kind', ['Grant', 'Credit', 'Award']), self::number('Amount'), self::text('Next step'), self::note(), self::date('Decided on')],
                [
                    self::when('Note the paper arrived', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Received ITEM_TITLE.')]),
                    self::when('Tell the owner more is being asked', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Asking', [
                        self::email('Asking: ITEM_TITLE', 'ITEM_TITLE needs a further question.'),
                        self::logLine('Asking for more on ITEM_TITLE.'),
                    ]),
                    self::when('Record the decision', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Decided', [
                        self::stamp('Decided on'),
                        self::logLine('Decided ITEM_TITLE.'),
                        self::notice('Decided: ITEM_TITLE', 'ITEM_TITLE has been decided.'),
                        self::setField('Next step', ''),
                    ]),
                ],
                $wall,
                self::search('Asking', 'Papers waiting on a further question.', 'Asking')
            ),
            self::BLUEPRINT_AUDIT => self::shape(
                'Audit',
                'A finding from when it is raised until it is closed.',
                'Finding',
                'ic-audit',
                'Rating',
                'Emails the owner when a finding is accepted.',
                [$status(['Raised', 'Accepted', 'In progress', 'Closed']), self::text('Place'), self::menu('Rating', ['High', 'Medium', 'Low']), self::text('Next step'), self::note(), self::date('Due'), self::date('Closed on')],
                [
                    self::when('Note the finding', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Raised ITEM_TITLE.')]),
                    self::when('Tell the owner the finding was accepted', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Accepted', [self::email('Accepted: ITEM_TITLE', 'ITEM_TITLE has been accepted.')]),
                    $close('Close the finding', 'Closed', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                array_merge($work, $diary),
                self::search('In progress', 'Findings being worked on.', 'In progress')
            ),
            self::BLUEPRINT_INCIDENTS => self::shape(
                'Incidents',
                'An incident from the report through to closure.',
                'Incident',
                'ic-alert',
                'Kind',
                'Posts a notice when an incident is reported.',
                [$status(['Reported', 'Investigating', 'Actions', 'Closed']), self::text('Place'), self::menu('Kind', ['Injury', 'Near miss', 'Damage', 'Environment']), self::text('Reported by'), self::text('Next step'), self::note(), self::date('Closed on')],
                [
                    self::when('Report the incident', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::email('Reported: ITEM_TITLE', 'ITEM_TITLE has been reported.'),
                        self::logLine('Reported ITEM_TITLE.'),
                        self::notice('Reported: ITEM_TITLE', 'ITEM_TITLE has been reported.'),
                    ]),
                    self::when('Note the investigation', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Investigating', [self::logLine('Investigation opened on ITEM_TITLE.')]),
                    self::when('Note a file was added', Rules::EVENT_ATTACHMENT, '', Rules::OP_ALWAYS, '', [self::logLine('A file was added to ITEM_TITLE.')]),
                    $close('Close the incident', 'Closed', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                array_merge($wall, $intake),
                self::search('Investigating', 'Incidents under investigation.', 'Investigating')
            ),
            self::BLUEPRINT_CANDIDATES => self::shape(
                'Candidates',
                'A candidate from the longlist through to placement.',
                'Candidate',
                'ic-user',
                'Role',
                'Emails the owner when a candidate reaches the shortlist.',
                [$status(['Longlist', 'Shortlist', 'Met', 'Offer', 'Placed']), self::text('Client'), self::text('Role'), self::text('Next step'), self::note(), self::date('Placed on')],
                [
                    self::when('Note the candidate', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Added ITEM_TITLE to the longlist.')]),
                    self::when('Tell the owner they are on the shortlist', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Shortlist', [self::email('Shortlist: ITEM_TITLE', 'ITEM_TITLE is on the shortlist.')]),
                    $close('Place the candidate', 'Placed', 'Placed on', 'Placed ITEM_TITLE.'),
                ],
                $work,
                self::search('Shortlist', 'Candidates on the shortlist.', 'Shortlist')
            ),
            self::BLUEPRINT_CONTRACTS => self::shape(
                'Contracts',
                'A contract from receipt through to signature.',
                'Contract',
                'ic-contract',
                'Kind',
                'Hands a contract back to the person who opened it, and stamps the signed date.',
                [$status(['In', 'Review', 'With the business', 'Signed']), self::text('Client'), self::menu('Kind', ['Customer', 'Supplier', 'Employment', 'NDA']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Signed on')],
                [
                    self::when('Note the contract arrived', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [self::logLine('Received ITEM_TITLE for review.')]),
                    self::when('Hand it back to the person who opened it', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With the business', [
                        self::setOwner(Rules::OWNER_CREATOR),
                        self::email('With the business: ITEM_TITLE', 'ITEM_TITLE is back with the person who opened it.', Rules::TO_CREATOR),
                    ]),
                    $close('Sign the contract', 'Signed', 'Signed on', 'Signed ITEM_TITLE.'),
                ],
                $work,
                self::search('In review', 'Contracts being read.', 'Review')
            ),
            self::BLUEPRINT_BLANK => self::shape(
                'Blank',
                'An empty application and one record type. Add fields when you are ready.',
                '',
                'ic-grid',
                '',
                '',
                [],
                [],
                $plain,
                null
            ),
        ];
    }

    /**
     * @param list<string>|null $screens Built-in screen ids; null = blueprint default
     * @param list<int> $groupIds groups to receive app permissions (empty = admins + creator)
     * @param list<string> $permLevels use|settings
     * @return array{application_id: int, slug: string, item_type_id: int}
     */
    public static function create(
        string $name,
        string $slug,
        string $hint,
        string $icon,
        string $blueprint,
        string $recordTypeName,
        ?array $screens = null,
        array $groupIds = [],
        array $permLevels = []
    ): array {
        $name = trim($name);
        $slug = self::slugify($slug, $name);
        $hint = trim($hint);
        $recordTypeName = trim($recordTypeName);
        $blueprints = self::blueprints();

        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Enter an application name.');
        }
        if (!isset($blueprints[$blueprint])) {
            throw new \InvalidArgumentException('Choose a blueprint.');
        }
        if ($recordTypeName === '') {
            $recordTypeName = trim((string) ($blueprints[$blueprint]['record'] ?? ''));
        }
        if ($recordTypeName === '') {
            $recordTypeName = $name . ' record';
        }
        if (!in_array($icon, Capabilities::icons(), true)) {
            $icon = 'ic-grid';
        }
        if (ApplicationStore::slugInUse($slug, 0)) {
            throw new \InvalidArgumentException('That slug is already used.');
        }
        if (self::itemTypeNameInUse($recordTypeName)) {
            throw new \InvalidArgumentException('That record type name is already used.');
        }

        $allowedScreens = array_keys(ScreenCatalog::builderScreens());
        $chosen = $screens ?? $blueprints[$blueprint]['screens'];
        $chosen = array_values(array_filter(
            $chosen,
            static fn (string $id): bool => in_array($id, $allowedScreens, true)
        ));
        if ($chosen === []) {
            $chosen = [ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_ADVANCED, ScreenCatalog::SEARCH_LIST];
        }

        $itemTypeId = self::createItemType($recordTypeName);
        $fieldDefs = $blueprints[$blueprint]['fields'];
        $fieldIds = self::createBlueprintFields($fieldDefs, $name);
        self::attachFields($itemTypeId, $fieldIds);
        $fieldIdsByName = [];
        $idIndex = 0;
        foreach ($fieldDefs as $field) {
            $fieldName = trim((string) ($field['name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }
            if (!isset($fieldIds[$idIndex])) {
                break;
            }
            $fieldIdsByName[$fieldName] = $fieldIds[$idIndex];
            $idIndex++;
        }

        $max = 0;
        foreach (ApplicationStore::all() as $app) {
            $max = max($max, (int) $app['sort_order']);
        }

        $usePerm = Permission::appUse($slug);
        $applicationId = ApplicationStore::insertApplication([
            'slug' => $slug,
            'name' => $name,
            'hint' => $hint !== '' ? $hint : $blueprints[$blueprint]['hint'],
            'icon' => $icon,
            'permission' => $usePerm,
            'enabled' => 1,
            'sort_order' => $max + 10,
            'entry_mode' => 'shell',
            'legacy_controller' => '',
            'legacy_key' => '',
            'settings_json' => json_encode([
                'default_item_type_id' => (string) $itemTypeId,
                'search_tab_added' => '1',
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $grantedGroups = Permission::grantAppToGroups($slug, $groupIds, $permLevels);
        GroupMembership::setItemTypeGroups($itemTypeId, $grantedGroups);
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId > 0) {
            \Adlexone\Auth\Access::hydrateSession($userId);
        }

        $typeConfig = ['item_type_id' => (string) $itemTypeId];
        $labels = ScreenCatalog::builderScreens();
        $icons = [
            ScreenCatalog::WORK => 'ic-work',
            ScreenCatalog::CREATE => 'ic-plus',
            ScreenCatalog::SEARCH_ADVANCED => 'ic-search',
            ScreenCatalog::SEARCH_LIST => 'ic-saved',
            ScreenCatalog::ANNOUNCEMENTS => 'ic-announce',
            ScreenCatalog::SETTINGS => 'ic-sliders',
            ScreenCatalog::COUNTS => 'ic-counts',
            ScreenCatalog::OWNERS => 'ic-owners',
            ScreenCatalog::STEPS => 'ic-steps',
            ScreenCatalog::DIARY => 'ic-diary',
            ScreenCatalog::PARTIES => 'ic-parties',
            ScreenCatalog::ACTIVITY => 'ic-activity',
            ScreenCatalog::FILES => 'ic-files',
            ScreenCatalog::AGE => 'ic-ageing',
            ScreenCatalog::REGISTER => 'ic-register',
            ScreenCatalog::INTAKE => 'ic-intake',
        ];
        $order = 10;
        foreach ($chosen as $capability) {
            $config = in_array($capability, [
                ScreenCatalog::WORK,
                ScreenCatalog::CREATE,
                ScreenCatalog::SEARCH_ADVANCED,
                ScreenCatalog::COUNTS,
                ScreenCatalog::OWNERS,
                ScreenCatalog::STEPS,
                ScreenCatalog::DIARY,
                ScreenCatalog::PARTIES,
                ScreenCatalog::ACTIVITY,
                ScreenCatalog::FILES,
                ScreenCatalog::AGE,
                ScreenCatalog::REGISTER,
                ScreenCatalog::INTAKE,
            ], true)
                ? $typeConfig
                : [];
            ApplicationStore::insertNav(
                $applicationId,
                $labels[$capability] ?? Capabilities::label($capability),
                $capability,
                '',
                $icons[$capability] ?? '',
                $config,
                $order
            );
            $order += 10;
        }

        self::seedRules($applicationId, $itemTypeId, $blueprints[$blueprint]['rules'] ?? [], $fieldIdsByName);
        self::seedSearch($slug, $itemTypeId, $blueprints[$blueprint]['search'] ?? null);

        return [
            'application_id' => $applicationId,
            'slug' => $slug,
            'item_type_id' => $itemTypeId,
        ];
    }

    public static function slugify(string $slug, string $name): string
    {
        $source = trim($slug) !== '' ? $slug : $name;
        $source = strtolower($source);
        $source = preg_replace('/[^a-z0-9]+/', '-', $source) ?? '';
        return trim($source, '-');
    }

    private static function itemTypeNameInUse(string $name): bool
    {
        return Database::first('item_types', ['item_type_id'], 'item_type_name = ?', [$name]) !== null;
    }

    private static function createItemType(string $name): int
    {
        return Database::insert('item_types', [
            'item_type_name' => $name,
            'user_security' => '',
            'enabled' => 'Yes',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<int>
     */
    private static function createBlueprintFields(array $fields, string $appName): array
    {
        $ids = [];
        foreach ($fields as $field) {
            $desired = (string) ($field['name'] ?? '');
            if ($desired === '') {
                continue;
            }
            $type = FieldTypes::normalise((string) ($field['type'] ?? FieldTypes::TEXT_BOX));
            $name = self::uniqueFieldName($desired, $appName);
            $id = Database::insert('custom_fields', [
                'custom_field_name' => $name,
                'field_type' => $type,
                'default_value' => '',
                'sub_menu' => 0,
                'enabled' => 'Yes',
                'field_reference' => 0,
                'data' => '',
                'validation_type' => FieldTypes::impliedValidation($type),
                'required' => 'No',
            ]);
            Database::update('custom_fields', ['field_reference' => $id], 'custom_field_id = ?', [$id]);

            $values = $field['values'] ?? [];
            if ($type === FieldTypes::MENU && is_array($values)) {
                foreach ($values as $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        continue;
                    }
                    Database::insert('custom_field_menu_values', [
                        'custom_field_id' => $id,
                        'menu_value' => $value,
                        'parent_menu_value_id' => 0,
                    ]);
                }
            }
            $ids[] = $id;
        }
        return $ids;
    }

    private static function uniqueFieldName(string $desired, string $appName): string
    {
        if (Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$desired]) === null) {
            return $desired;
        }
        $candidate = $desired . ' (' . $appName . ')';
        if (Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$candidate]) === null) {
            return $candidate;
        }
        $n = 2;
        while (Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$candidate . ' ' . $n]) !== null) {
            $n++;
        }
        return $candidate . ' ' . $n;
    }

    /**
     * @param list<int> $fieldIds
     */
    private static function attachFields(int $itemTypeId, array $fieldIds): void
    {
        $order = 10;
        foreach ($fieldIds as $fieldId) {
            Database::insert('item_type_custom_fields', [
                'item_type_id' => $itemTypeId,
                'custom_field_id' => $fieldId,
                'custom_field_order' => $order,
            ]);
            $order += 10;
        }
    }

    /**
     * Drop every application currently installed. Used once, by the schema
     * migration, before the case-file applications are installed.
     */
    public static function removeInstalledApplications(): void
    {
        if (!Database::tableExists('applications')) {
            return;
        }
        $slugs = [];
        foreach (Database::select('applications', ['slug']) as $row) {
            $slugs[] = (string) $row['slug'];
        }
        foreach ($slugs as $slug) {
            self::dropSlug($slug);
        }
        self::deleteUnlinkedFields();
    }

    /**
     * The ten case-file applications, with work already on the board.
     * An application is left alone once its mark field is present.
     */
    public static function installShowcase(): void
    {
        if (self::$installingShowcase) {
            return;
        }
        self::$installingShowcase = true;
        try {
            self::installShowcaseOnce();
        } finally {
            self::$installingShowcase = false;
        }
    }

    private static function installShowcaseOnce(): void
    {
        self::dropSlug('studio');
        self::dropSlug('house');
        self::deleteUnlinkedFields();
        $blueprints = self::blueprints();
        foreach (self::showcase() as $slug => $spec) {
            $mark = (string) ($blueprints[$spec['blueprint']]['mark'] ?? '');
            if (
                self::slugExists($slug)
                && $mark !== ''
                && !self::recordTypeWasChosen($slug)
                && !self::applicationHasMark($slug, $mark)
            ) {
                self::dropSlug($slug);
            }
        }
        foreach (self::showcase() as $slug => $spec) {
            if (self::slugExists($slug)) {
                continue;
            }
            $meta = $blueprints[$spec['blueprint']];
            $record = (string) $meta['record'];
            if (self::itemTypeNameInUse($record)) {
                $record .= ' (' . $meta['label'] . ')';
            }
            $created = self::create(
                (string) $meta['label'],
                $slug,
                (string) $meta['hint'],
                (string) $meta['icon'],
                $spec['blueprint'],
                $record
            );
            Database::update('applications', ['sort_order' => $spec['sort']], 'application_id = ?', [(int) $created['application_id']]);
            self::seedShowcaseCards((int) $created['item_type_id'], $meta['fields'], $spec['cards']);
            $notice = $spec['notice'] ?? null;
            if (is_array($notice) && Database::tableExists('announcements')) {
                Database::insert('announcements', [
                    'time' => time(),
                    'subject' => (string) $notice['subject'],
                    'message' => (string) $notice['message'],
                    'type' => '',
                    'application' => $slug,
                ]);
            }
        }
    }

    /**
     * @return array<string, array{blueprint: string, sort: int, cards: list<array{title: string, fields: array<string, string>}>, notice?: array{subject: string, message: string}}>
     */
    private static function showcase(): array
    {
        return [
            'matters' => [
                'blueprint' => self::BLUEPRINT_MATTERS,
                'sort' => 10,
                'cards' => [
                    ['title' => 'Reid lease', 'fields' => ['Status' => 'Waiting on us', 'Client' => 'Reid', 'Kind' => 'Contract', 'Reference' => 'R-1044', 'Next step' => 'Follow up the draft', 'Note' => 'The break clause is to come out.']],
                    ['title' => 'Nguyen dispute', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Nguyen', 'Kind' => 'Dispute', 'Reference' => 'N-220', 'Next step' => 'Contact their solicitor']],
                    ['title' => 'Campbell advice', 'fields' => ['Status' => 'Waiting on us', 'Client' => 'Campbell', 'Kind' => 'Advice', 'Reference' => 'C-18', 'Next step' => 'Call on Thursday']],
                    ['title' => 'Walsh licence', 'fields' => ['Status' => 'Done', 'Client' => 'Walsh', 'Kind' => 'Property', 'Reference' => 'W-7', 'Next step' => 'File the copy', 'Closed on' => '02-10-2026']],
                ],
            ],
            'conveyancing' => [
                'blueprint' => self::BLUEPRINT_CONVEYANCING,
                'sort' => 20,
                'cards' => [
                    ['title' => 'Barton Street sale', 'fields' => ['Status' => 'Enquiries', 'Client' => 'Reid', 'Place' => '14 Barton Street', 'Side' => 'Sale', 'Reference' => 'C-441', 'Next step' => 'Reply on the boundary']],
                    ['title' => 'Nguyen purchase', 'fields' => ['Status' => 'Searches', 'Client' => 'Nguyen', 'Place' => 'Kingston', 'Side' => 'Purchase', 'Reference' => 'C-442', 'Next step' => 'Follow up the local search']],
                    ['title' => 'Campbell unit', 'fields' => ['Status' => 'Instructed', 'Client' => 'Campbell', 'Place' => '8 Civic', 'Side' => 'Purchase', 'Reference' => 'C-450', 'Next step' => 'Take instructions']],
                    ['title' => 'Walsh leasehold', 'fields' => ['Status' => 'Exchange', 'Client' => 'Walsh', 'Place' => '2 Braddon', 'Side' => 'Sale', 'Reference' => 'C-438', 'Next step' => 'Book exchange']],
                ],
            ],
            'claims' => [
                'blueprint' => self::BLUEPRINT_CLAIMS,
                'sort' => 30,
                'cards' => [
                    ['title' => 'Reid roof', 'fields' => ['Status' => 'With the insurer', 'Client' => 'Reid', 'Insurer' => 'QBE', 'Kind' => 'Property', 'Reference' => 'CL-19', 'Reserve' => '18000', 'Next step' => 'Send the photos']],
                    ['title' => 'Nguyen motor', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Nguyen', 'Insurer' => 'Allianz', 'Kind' => 'Motor', 'Reference' => 'CL-22', 'Reserve' => '6400', 'Next step' => 'Waiting on the engineer\'s report']],
                    ['title' => 'Civic stock', 'fields' => ['Status' => 'Notified', 'Client' => 'Campbell', 'Insurer' => 'QBE', 'Kind' => 'Liability', 'Reference' => 'CL-30', 'Reserve' => '25000', 'Next step' => 'Open the file']],
                    ['title' => 'Walsh flood', 'fields' => ['Status' => 'Settled', 'Client' => 'Walsh', 'Insurer' => 'QBE', 'Kind' => 'Property', 'Reference' => 'CL-11', 'Reserve' => '9100', 'Next step' => 'File the settlement', 'Settled on' => '28-09-2026']],
                ],
            ],
            'repairs' => [
                'blueprint' => self::BLUEPRINT_REPAIRS,
                'sort' => 40,
                'cards' => [
                    ['title' => 'Boiler, flat 4', 'fields' => ['Status' => 'On site', 'Place' => 'Flat 4', 'Trade' => 'Plumbing', 'Reported by' => 'Porter', 'Next step' => 'Keys from the porter', 'Urgent' => 'Yes']],
                    ['title' => 'West stair light', 'fields' => ['Status' => 'Booked', 'Place' => 'West stair', 'Trade' => 'Electrical', 'Reported by' => 'Office', 'Next step' => 'Thursday morning']],
                    ['title' => 'Gate latch', 'fields' => ['Status' => 'Reported', 'Place' => 'Yard', 'Trade' => 'Joinery', 'Reported by' => 'Reid', 'Next step' => 'Visit the site']],
                    ['title' => 'Roof, block C', 'fields' => ['Status' => 'Done', 'Place' => 'Block C', 'Trade' => 'Roof', 'Reported by' => 'Estate', 'Next step' => 'File the invoice', 'Done on' => '11-10-2026']],
                ],
            ],
            'complaints' => [
                'blueprint' => self::BLUEPRINT_COMPLAINTS,
                'sort' => 50,
                'cards' => [
                    ['title' => 'Missed appointment', 'fields' => ['Status' => 'With us', 'Client' => 'Reid', 'Channel' => 'Phone', 'Reference' => 'CO-12', 'Next step' => 'Call them back', 'Response due' => '01-12-2026']],
                    ['title' => 'Charge on the account', 'fields' => ['Status' => 'Their response', 'Client' => 'Nguyen', 'Channel' => 'Letter', 'Reference' => 'CO-9', 'Next step' => 'Wait for the letter', 'Response due' => '18-11-2026']],
                    ['title' => 'Website form', 'fields' => ['Status' => 'Opened', 'Client' => 'Campbell', 'Channel' => 'Web', 'Reference' => 'CO-14', 'Next step' => 'Acknowledge today', 'Response due' => '14-12-2026']],
                    ['title' => 'Late refund', 'fields' => ['Status' => 'Closed', 'Client' => 'Walsh', 'Channel' => 'Email', 'Reference' => 'CO-4', 'Next step' => 'File the reply', 'Closed on' => '20-09-2026']],
                ],
                'notice' => [
                    'subject' => 'Eight weeks',
                    'message' => 'A response is due within eight weeks of the complaint being opened.',
                ],
            ],
            'committee' => [
                'blueprint' => self::BLUEPRINT_COMMITTEE,
                'sort' => 60,
                'cards' => [
                    ['title' => 'Barton fund', 'fields' => ['Status' => 'Asking', 'Applicant' => 'Barton trust', 'Kind' => 'Grant', 'Amount' => '40000', 'Next step' => 'Ask for the budget']],
                    ['title' => 'Kingston bursary', 'fields' => ['Status' => 'Reading', 'Applicant' => 'Kingston school', 'Kind' => 'Award', 'Amount' => '8000', 'Next step' => 'Read the statement']],
                    ['title' => 'Campbell facility', 'fields' => ['Status' => 'In', 'Applicant' => 'Campbell', 'Kind' => 'Credit', 'Amount' => '120000', 'Next step' => 'Check the security']],
                    ['title' => 'Walsh award', 'fields' => ['Status' => 'Decided', 'Applicant' => 'Walsh', 'Kind' => 'Award', 'Amount' => '5000', 'Next step' => 'Write the letter', 'Decided on' => '03-10-2026']],
                ],
                'notice' => [
                    'subject' => 'Thursday',
                    'message' => 'The committee sits on Thursday. Papers are with the secretary.',
                ],
            ],
            'audit' => [
                'blueprint' => self::BLUEPRINT_AUDIT,
                'sort' => 70,
                'cards' => [
                    ['title' => 'Cash handling', 'fields' => ['Status' => 'Accepted', 'Place' => 'Front office', 'Rating' => 'High', 'Next step' => 'Count the till weekly', 'Due' => '30-10-2026']],
                    ['title' => 'Access cards', 'fields' => ['Status' => 'In progress', 'Place' => 'Plant room', 'Rating' => 'Medium', 'Next step' => 'Reissue the lost cards', 'Due' => '14-11-2026']],
                    ['title' => 'Supplier list', 'fields' => ['Status' => 'Raised', 'Place' => 'Accounts', 'Rating' => 'Low', 'Next step' => 'Pull the last twelve', 'Due' => '28-11-2026']],
                    ['title' => 'Fire doors', 'fields' => ['Status' => 'Closed', 'Place' => 'West stair', 'Rating' => 'High', 'Next step' => 'File the check', 'Closed on' => '19-09-2026']],
                ],
            ],
            'incidents' => [
                'blueprint' => self::BLUEPRINT_INCIDENTS,
                'sort' => 80,
                'cards' => [
                    ['title' => 'Slip, west stair', 'fields' => ['Status' => 'Investigating', 'Place' => 'West stair', 'Kind' => 'Injury', 'Reported by' => 'Porter', 'Next step' => 'Speak to the witness']],
                    ['title' => 'Near miss, yard', 'fields' => ['Status' => 'Reported', 'Place' => 'Yard', 'Kind' => 'Near miss', 'Reported by' => 'Reid', 'Next step' => 'Write the note']],
                    ['title' => 'Flood, store', 'fields' => ['Status' => 'Actions', 'Place' => 'Store', 'Kind' => 'Damage', 'Reported by' => 'Estate', 'Next step' => 'Dry the stock']],
                    ['title' => 'Cut, kitchen', 'fields' => ['Status' => 'Closed', 'Place' => 'Kitchen', 'Kind' => 'Injury', 'Reported by' => 'Chef', 'Next step' => 'File the note', 'Closed on' => '07-10-2026']],
                ],
                'notice' => [
                    'subject' => 'Before the end of the shift',
                    'message' => 'Report an injury before the end of the shift.',
                ],
            ],
            'candidates' => [
                'blueprint' => self::BLUEPRINT_CANDIDATES,
                'sort' => 90,
                'cards' => [
                    ['title' => 'A. Reid', 'fields' => ['Status' => 'Shortlist', 'Client' => 'Campbell', 'Role' => 'Finance lead', 'Next step' => 'Arrange the meeting']],
                    ['title' => 'J. Nguyen', 'fields' => ['Status' => 'Longlist', 'Client' => 'Campbell', 'Role' => 'Finance lead', 'Next step' => 'Read the brief']],
                    ['title' => 'M. Walsh', 'fields' => ['Status' => 'Met', 'Client' => 'Barton', 'Role' => 'Operations', 'Next step' => 'Their note is due']],
                    ['title' => 'S. Campbell', 'fields' => ['Status' => 'Offer', 'Client' => 'Nguyen', 'Role' => 'Counsel', 'Next step' => 'Wait on the offer']],
                ],
            ],
            'contracts' => [
                'blueprint' => self::BLUEPRINT_CONTRACTS,
                'sort' => 100,
                'cards' => [
                    ['title' => 'Reid services', 'fields' => ['Status' => 'Review', 'Client' => 'Reid', 'Kind' => 'Customer', 'Reference' => 'K-100', 'Next step' => 'Mark the liability cap']],
                    ['title' => 'Nguyen supply', 'fields' => ['Status' => 'With the business', 'Client' => 'Nguyen', 'Kind' => 'Supplier', 'Reference' => 'K-88', 'Next step' => 'They have the draft']],
                    ['title' => 'Campbell NDA', 'fields' => ['Status' => 'In', 'Client' => 'Campbell', 'Kind' => 'NDA', 'Reference' => 'K-110', 'Next step' => 'Read it']],
                    ['title' => 'Walsh employment', 'fields' => ['Status' => 'Signed', 'Client' => 'Walsh', 'Kind' => 'Employment', 'Reference' => 'K-74', 'Next step' => 'File the copy', 'Signed on' => '15-09-2026']],
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $fieldDefs
     * @param list<array{title: string, fields: array<string, string>}> $cards
     */
    private static function seedShowcaseCards(int $itemTypeId, array $fieldDefs, array $cards): void
    {
        $links = Database::select(
            'item_type_custom_fields',
            ['custom_field_id'],
            'item_type_id = ?',
            [$itemTypeId],
            'custom_field_order ASC'
        );
        $fieldIds = [];
        $types = [];
        foreach ($fieldDefs as $index => $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '' || !isset($links[$index])) {
                continue;
            }
            $fieldIds[$name] = (int) $links[$index]['custom_field_id'];
            $types[$name] = FieldTypes::normalise((string) ($field['type'] ?? ''));
        }
        $owner = self::showcaseOwnerId();
        $groups = GroupMembership::itemTypeGroupIds($itemTypeId);
        foreach ($cards as $card) {
            $itemId = Database::insert('items', [
                'create_date' => time(),
                'item_type_id' => $itemTypeId,
                'creator_security' => $owner,
                'user_security' => $owner,
                'item_title' => $card['title'],
            ]);
            $values = [];
            foreach ($card['fields'] as $name => $value) {
                $fieldId = $fieldIds[$name] ?? 0;
                if ($fieldId <= 0) {
                    continue;
                }
                if (($types[$name] ?? '') === FieldTypes::CHECK_BOX && strcasecmp($value, 'Yes') === 0) {
                    $value = (string) $fieldId;
                }
                $values['custom_field_' . $fieldId] = $value;
            }
            ItemFields::saveFromArray($itemId, $values);
            GroupMembership::setItemGroups($itemId, $groups);
        }
    }

    private static function showcaseOwnerId(): int
    {
        $row = Database::first('users', ['user_id'], 'user_name = ?', [\Adlexone\Platform\DefaultAccount::USERNAME]);
        if ($row === null) {
            $row = Database::first('users', ['user_id'], 'user_name = ?', ['Administrator']);
        }
        if ($row !== null) {
            return (int) $row['user_id'];
        }
        $row = Database::first('users', ['user_id'], '', [], 'user_id ASC');

        return (int) ($row['user_id'] ?? 0);
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param list<array<string, mixed>> $rules
     * @param list<string> $screens
     * @param array{name: string, description: string, field: string, value: string}|null $search
     * @return array<string, mixed>
     */
    private static function shape(
        string $label,
        string $hint,
        string $record,
        string $icon,
        string $mark,
        string $rule,
        array $fields,
        array $rules,
        array $screens,
        ?array $search
    ): array {
        return [
            'label' => $label,
            'hint' => $hint,
            'record' => $record,
            'icon' => $icon,
            'mark' => $mark,
            'rule' => $rule,
            'fields' => $fields,
            'rules' => $rules,
            'screens' => $screens,
            'search' => $search,
        ];
    }

    /**
     * @param list<string> $values
     * @return array<string, mixed>
     */
    private static function menu(string $name, array $values): array
    {
        return ['name' => $name, 'type' => FieldTypes::MENU, 'values' => $values];
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(string $name): array
    {
        return ['name' => $name, 'type' => FieldTypes::TEXT_BOX];
    }

    /**
     * @return array<string, mixed>
     */
    private static function date(string $name): array
    {
        return ['name' => $name, 'type' => FieldTypes::DATE];
    }

    /**
     * @return array<string, mixed>
     */
    private static function number(string $name): array
    {
        return ['name' => $name, 'type' => FieldTypes::NUMBER];
    }

    /**
     * @return array<string, mixed>
     */
    private static function note(): array
    {
        return ['name' => 'Note', 'type' => FieldTypes::TEXT_AREA];
    }

    /**
     * @return array<string, mixed>
     */
    private static function check(string $name): array
    {
        return ['name' => $name, 'type' => FieldTypes::CHECK_BOX];
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return array<string, mixed>
     */
    private static function when(string $name, string $event, string $field, string $operator, string $match, array $steps): array
    {
        return [
            'name' => $name,
            'event' => $event,
            'field' => $field,
            'operator' => $operator,
            'match' => $match,
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function email(string $subject, string $body, string $recipient = Rules::TO_OWNER): array
    {
        return ['outcome' => Rules::OUTCOME_EMAIL, 'subject' => $subject, 'body' => $body, 'recipient' => $recipient];
    }

    /**
     * @return array<string, mixed>
     */
    private static function logLine(string $body): array
    {
        return ['outcome' => Rules::OUTCOME_LOG, 'body' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stamp(string $field): array
    {
        return ['outcome' => Rules::OUTCOME_STAMP, 'field' => $field];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notice(string $subject, string $body): array
    {
        return ['outcome' => Rules::OUTCOME_ANNOUNCE, 'subject' => $subject, 'body' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    private static function setField(string $field, string $value): array
    {
        return ['outcome' => Rules::OUTCOME_SET_FIELD, 'field' => $field, 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private static function setOwner(string $who): array
    {
        return ['outcome' => Rules::OUTCOME_SET_OWNER, 'owner' => $who];
    }

    /**
     * @return array{name: string, description: string, field: string, value: string}
     */
    private static function search(string $name, string $description, string $value): array
    {
        return ['name' => $name, 'description' => $description, 'field' => 'Status', 'value' => $value];
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, int> $fieldIdsByName
     */
    private static function seedRules(int $applicationId, int $itemTypeId, array $rules, array $fieldIdsByName): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $fieldName = (string) ($rule['field'] ?? '');
            $fieldId = $fieldName === '' ? 0 : (int) ($fieldIdsByName[$fieldName] ?? 0);
            $operator = (string) ($rule['operator'] ?? Rules::OP_ALWAYS);
            if ($operator !== Rules::OP_ALWAYS && $fieldId <= 0) {
                continue;
            }
            $steps = [];
            foreach ($rule['steps'] ?? [] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $outcome = (string) ($step['outcome'] ?? '');
                $target = (string) ($step['field'] ?? '');
                $actionField = '';
                if ($target !== '') {
                    $targetId = (int) ($fieldIdsByName[$target] ?? 0);
                    if ($targetId <= 0) {
                        continue;
                    }
                    $actionField = 'custom_field_' . $targetId;
                } elseif ($outcome === Rules::OUTCOME_STAMP || $outcome === Rules::OUTCOME_SET_FIELD) {
                    continue;
                }
                $steps[] = [
                    'outcome' => $outcome,
                    'recipient' => (string) ($step['recipient'] ?? Rules::TO_OWNER),
                    'subject' => (string) ($step['subject'] ?? ''),
                    'body' => (string) ($step['body'] ?? ''),
                    'action_field' => $actionField,
                    'action_value' => (string) ($step['value'] ?? $step['owner'] ?? ''),
                ];
            }
            if ($steps === []) {
                continue;
            }
            Rules::add($applicationId, [
                'item_type_id' => $itemTypeId,
                'name' => (string) ($rule['name'] ?? ''),
                'event' => (string) ($rule['event'] ?? Rules::EVENT_UPDATE),
                'field_key' => $fieldId > 0 ? 'custom_field_' . $fieldId : '',
                'operator' => $operator,
                'match_value' => (string) ($rule['match'] ?? ''),
                'steps' => $steps,
            ]);
        }
    }

    /**
     * @param array{name?: string, description?: string, field?: string, value?: string}|null $search
     */
    private static function seedSearch(string $slug, int $typeId, ?array $search): void
    {
        if ($search === null || $slug === '' || $typeId <= 0 || !Database::tableExists('saved_searches')) {
            return;
        }
        $name = trim((string) ($search['name'] ?? ''));
        $value = trim((string) ($search['value'] ?? ''));
        if ($name === '' || $value === '') {
            return;
        }
        if (Database::first('saved_searches', ['search_id'], 'application = ? AND search_name = ?', [$slug, $name]) !== null) {
            return;
        }
        $fieldId = self::fieldIdOnType($typeId, (string) ($search['field'] ?? 'Status'));
        if ($fieldId <= 0) {
            return;
        }
        $criteria = [
            'version' => 1,
            'filters' => [
                ['field' => 'item_type_id', 'op' => '=', 'value' => (string) $typeId, 'andor' => 'AND'],
                ['field' => 'custom_field_' . $fieldId, 'op' => '=', 'value' => $value, 'andor' => 'AND'],
            ],
            'display' => [],
        ];
        Database::insert('saved_searches', [
            'user' => 'all',
            'search_name' => $name,
            'search_description' => (string) ($search['description'] ?? ''),
            'saved_search_sql' => '',
            'application' => $slug,
            'criteria_json' => json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private static function fieldIdOnType(int $typeId, string $name): int
    {
        $want = self::plainName($name);
        foreach (Database::select('item_type_custom_fields', ['custom_field_id'], 'item_type_id = ?', [$typeId], 'custom_field_order ASC') as $link) {
            $field = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [(int) $link['custom_field_id']]);
            if ($field !== null && self::plainName((string) $field['custom_field_name']) === $want) {
                return (int) $link['custom_field_id'];
            }
        }

        return 0;
    }

    private static function slugExists(string $slug): bool
    {
        return $slug !== '' && Database::first('applications', ['application_id'], 'slug = ?', [$slug]) !== null;
    }

    /**
     * The details page has chosen this application's record type.
     * The showcase installer leaves that choice in place.
     */
    private static function recordTypeWasChosen(string $slug): bool
    {
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return false;
        }
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);

        return is_array($settings) && (string) ($settings['record_type_chosen'] ?? '') === '1';
    }

    private static function applicationHasMark(string $slug, string $mark): bool
    {
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return false;
        }
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        $typeId = is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;

        return $typeId > 0 && self::fieldIdOnType($typeId, $mark) > 0;
    }

    private static function plainName(string $name): string
    {
        $name = strtolower(trim($name));
        $previous = null;
        while ($name !== $previous) {
            $previous = $name;
            $name = trim((string) preg_replace('/\s+\d+$/', '', $name));
            $name = trim((string) preg_replace('/\s*\([^)]*\)$/', '', $name));
        }

        return $name;
    }

    private static function dropSlug(string $slug): void
    {
        if ($slug === '' || !Database::tableExists('applications')) {
            return;
        }
        $app = Database::first('applications', ['application_id', 'settings_json'], 'slug = ?', [$slug]);
        if ($app === null) {
            return;
        }
        $applicationId = (int) $app['application_id'];
        $typeIds = [];
        $settings = json_decode((string) ($app['settings_json'] ?? '{}'), true);
        $defaultType = is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;
        if ($defaultType > 0) {
            $typeIds[] = $defaultType;
        }
        if (Database::tableExists('application_nav')) {
            foreach (Database::select('application_nav', ['config_json'], 'application_id = ?', [$applicationId]) as $nav) {
                $config = json_decode((string) ($nav['config_json'] ?? '{}'), true);
                $typeId = is_array($config) ? (int) ($config['item_type_id'] ?? 0) : 0;
                if ($typeId > 0) {
                    $typeIds[] = $typeId;
                }
            }
        }
        $typeIds = array_values(array_unique($typeIds));
        $fieldIds = [];
        foreach ($typeIds as $typeId) {
            if (self::typeIsShared($typeId, $applicationId)) {
                continue;
            }
            foreach (Database::select('item_type_custom_fields', ['custom_field_id'], 'item_type_id = ?', [$typeId]) as $link) {
                $fieldIds[] = (int) $link['custom_field_id'];
            }
            self::deleteRecordsOfType($typeId);
        }
        if (class_exists(Permission::class)) {
            Permission::revokeApp($slug);
        }
        if (Database::tableExists('saved_searches')) {
            Database::delete('saved_searches', 'application = ?', [$slug]);
        }
        if (Database::tableExists('announcements')) {
            Database::delete('announcements', 'application = ?', [$slug]);
        }
        if (Database::tableExists('users')) {
            Database::run(
                'UPDATE users SET home_controller = ? WHERE home_controller = ?',
                ['', 'application:' . $slug]
            );
        }
        Rules::deleteForApplication($applicationId);
        Database::delete('application_nav', 'application_id = ?', [$applicationId]);
        Database::delete('applications', 'application_id = ?', [$applicationId]);
        foreach ($typeIds as $typeId) {
            if (self::typeIsShared($typeId, $applicationId) || Database::exists('items', 'item_type_id = ?', [$typeId])) {
                continue;
            }
            Database::delete('item_type_custom_fields', 'item_type_id = ?', [$typeId]);
            Database::delete('item_type_groups', 'item_type_id = ?', [$typeId]);
            if (Database::tableExists('action_definitions')) {
                Database::delete('action_definitions', 'item_type_id = ?', [$typeId]);
            }
            Database::delete('item_types', 'item_type_id = ?', [$typeId]);
        }
        foreach (array_unique($fieldIds) as $fieldId) {
            if ($fieldId <= 0 || Database::exists('item_type_custom_fields', 'custom_field_id = ?', [$fieldId])) {
                continue;
            }
            Database::delete('item_field_values', 'custom_field_id = ?', [$fieldId]);
            if (Database::tableExists('custom_field_menu_values')) {
                Database::delete('custom_field_menu_values', 'custom_field_id = ?', [$fieldId]);
            }
            Database::delete('custom_fields', 'custom_field_id = ?', [$fieldId]);
        }
    }

    private static function typeIsShared(int $typeId, int $exceptApplicationId): bool
    {
        if ($typeId <= 0 || !Database::tableExists('applications')) {
            return false;
        }
        foreach (Database::select('applications', ['application_id', 'settings_json']) as $app) {
            if ((int) $app['application_id'] === $exceptApplicationId) {
                continue;
            }
            $settings = json_decode((string) ($app['settings_json'] ?? '{}'), true);
            if (is_array($settings) && (int) ($settings['default_item_type_id'] ?? 0) === $typeId) {
                return true;
            }
        }
        if (!Database::tableExists('application_nav')) {
            return false;
        }
        foreach (Database::select('application_nav', ['application_id', 'config_json'], 'application_id <> ?', [$exceptApplicationId]) as $nav) {
            $config = json_decode((string) ($nav['config_json'] ?? '{}'), true);
            if (is_array($config) && (int) ($config['item_type_id'] ?? 0) === $typeId) {
                return true;
            }
        }

        return false;
    }

    private static function deleteUnlinkedFields(): void
    {
        if (!Database::tableExists('custom_fields') || !Database::tableExists('item_type_custom_fields')) {
            return;
        }
        foreach (Database::select('custom_fields', ['custom_field_id']) as $field) {
            $fieldId = (int) $field['custom_field_id'];
            if ($fieldId <= 0 || Database::exists('item_type_custom_fields', 'custom_field_id = ?', [$fieldId])) {
                continue;
            }
            Database::delete('item_field_values', 'custom_field_id = ?', [$fieldId]);
            if (Database::tableExists('custom_field_menu_values')) {
                Database::delete('custom_field_menu_values', 'custom_field_id = ?', [$fieldId]);
            }
            Database::delete('custom_fields', 'custom_field_id = ?', [$fieldId]);
        }
    }

    private static function deleteRecordsOfType(int $typeId): void
    {
        if ($typeId <= 0) {
            return;
        }
        $ids = [];
        foreach (Database::select('items', ['item_id'], 'item_type_id = ?', [$typeId]) as $row) {
            $ids[] = (int) $row['item_id'];
        }
        foreach ($ids as $id) {
            Database::delete('item_field_values', 'item_id = ?', [$id]);
            Database::delete('item_groups', 'item_id = ?', [$id]);
            Database::delete('core_log', 'item_id = ?', [$id]);
            if (Database::tableExists('item_attachments')) {
                Database::delete('item_attachments', 'item_id = ?', [$id]);
            }
            if (Database::tableExists('system_log')) {
                Database::delete('system_log', 'item_id = ?', [$id]);
            }
            if (Database::tableExists('application_rule_runs')) {
                Database::delete('application_rule_runs', 'item_id = ?', [$id]);
            }
            Database::delete('items', 'item_id = ?', [$id]);
        }
    }
}
