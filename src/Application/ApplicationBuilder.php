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
    public const BLUEPRINT_PUBLISHING = 'publishing';

    /**
     * @return array<string, array{label: string, hint: string, record: string, icon: string, rule: string, fields: list<array<string, mixed>>, screens: list<string>}>
     */
    public static function blueprints(): array
    {
        $status = static fn (array $values): array => self::menu('Status', $values);
        $close = static function (string $name, string $status, string $date, string $line): array {
            return self::when($name, Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, $status, [
                self::stamp($date),
                self::logLine($line),
                self::setField('Next step', ''),
            ]);
        };

        return [
            self::BLUEPRINT_PUBLISHING => self::shape(
                'Publishing',
                'A manuscript from the day it arrives until it is approved or declined.',
                'Manuscript',
                'ic-book',
                'Writer',
                'Posts a notice and stamps the approved date when a manuscript is approved.',
                [
                    $status(['Received', 'Reading', 'With the writer', 'Editing', 'Approved', 'Declined']),
                    self::text('Writer'),
                    self::menu('Kind', ['Novel', 'Stories', 'Memoir', 'Poetry', 'Essays']),
                    self::number('Word count'),
                    self::text('Next step'),
                    self::note(),
                    self::date('Reply due'),
                    self::date('Received on'),
                    self::date('Approved on'),
                    self::date('Declined on'),
                ],
                [
                    self::when('Note the arrival', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Received on'),
                        self::logLine('Received ITEM_TITLE.'),
                    ]),
                    self::when('Start the reading', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Reading', [
                        self::logLine('Reading ITEM_TITLE.'),
                        self::setField('Next step', 'Finish the reading.'),
                    ]),
                    self::when('Ask for a rewrite', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With the writer', [
                        self::email('With the writer: ITEM_TITLE', 'ITEM_TITLE is with the writer for a rewrite.'),
                        self::logLine('Asked the writer to revise ITEM_TITLE.'),
                        self::setField('Next step', 'Waiting on the rewrite.'),
                    ]),
                    self::when('Note a file while it is with the writer', Rules::EVENT_ATTACHMENT, 'Status', Rules::OP_EQ, 'With the writer', [
                        self::email('A file on ITEM_TITLE', 'A file was added to ITEM_TITLE while it is with the writer.'),
                        self::logLine('A file was added to ITEM_TITLE.'),
                    ]),
                    self::when('Start the edit', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Editing', [
                        self::logLine('Editing ITEM_TITLE.'),
                        self::setField('Next step', 'Finish the edit.'),
                    ]),
                    self::when('Approve the manuscript', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Approved', [
                        self::stamp('Approved on'),
                        self::logLine('Approved ITEM_TITLE for publication.'),
                        self::notice('Approved: ITEM_TITLE', 'ITEM_TITLE has been approved for publication.'),
                        self::email('Approved: ITEM_TITLE', 'ITEM_TITLE has been approved for publication.'),
                        self::setField('Next step', ''),
                    ]),
                    self::when('Decline the manuscript', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Declined', [
                        self::stamp('Declined on'),
                        self::logLine('Declined ITEM_TITLE.'),
                        self::setField('Next step', ''),
                    ]),
                ],
                self::workScreens(true, true, true, false),
                self::search('With the writer', 'Manuscripts waiting on a rewrite.', 'With the writer'),
                ['Approved', 'Declined'],
                self::publishingGuide()
            ),
            self::BLUEPRINT_MATTERS => self::shape(
                'Client matters',
                'A matter from the instruction until it is done.',
                'Matter',
                'ic-briefcase',
                'Opened on',
                'Stamps the closed date when a matter is done.',
                [$status(['Waiting on us', 'Waiting on them', 'Done']), self::text('Client'), self::menu('Kind', ['Contract', 'Dispute', 'Advice', 'Property']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Opened on'), self::date('Closed on')],
                [
                    self::when('Note that a matter was opened', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Opened on'),
                        self::logLine('Opened ITEM_TITLE.'),
                    ]),
                    self::when('Tell the owner a matter is waiting on us', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Waiting on us', [
                        self::email('Waiting on us: ITEM_TITLE', 'ITEM_TITLE is waiting on us.'),
                        self::logLine('ITEM_TITLE is waiting on us.'),
                        self::setField('Next step', 'Take the next action.'),
                    ]),
                    self::when('Note that a matter is waiting on them', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Waiting on them', [
                        self::email('Waiting on them: ITEM_TITLE', 'ITEM_TITLE is waiting on them.'),
                        self::logLine('ITEM_TITLE is waiting on them.'),
                        self::setField('Next step', 'Waiting on their reply.'),
                    ]),
                    $close('Close a finished matter', 'Done', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                self::workScreens(true, true, false, false),
                self::search('Waiting on us', 'Matters that are waiting on us.', 'Waiting on us'),
                ['Done'],
                self::mattersGuide()
            ),
            self::BLUEPRINT_CONVEYANCING => self::shape(
                'Conveyancing',
                'A sale or a purchase, from instruction to completion.',
                'Conveyance',
                'ic-home',
                'Instructed on',
                'Stamps the completed date when a conveyance is complete.',
                [$status(['Instructed', 'Searches', 'Enquiries', 'Exchange', 'Complete']), self::text('Client'), self::text('Place'), self::menu('Side', ['Sale', 'Purchase']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Instructed on'), self::date('Completed on')],
                [
                    self::when('Note the instruction', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Instructed on'),
                        self::logLine('Instructed ITEM_TITLE.'),
                    ]),
                    self::when('Start the searches', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Searches', [
                        self::logLine('Searches ordered on ITEM_TITLE.'),
                        self::setField('Next step', 'Follow up the searches.'),
                    ]),
                    self::when('Tell the owner enquiries have started', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Enquiries', [
                        self::email('Enquiries: ITEM_TITLE', 'ITEM_TITLE is in enquiries.'),
                        self::logLine('Enquiries opened on ITEM_TITLE.'),
                        self::setField('Next step', 'Reply on the enquiries.'),
                    ]),
                    self::when('Note that exchange is ready', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Exchange', [
                        self::logLine('ITEM_TITLE is ready to exchange.'),
                        self::setField('Next step', 'Book exchange.'),
                    ]),
                    $close('Complete the conveyance', 'Complete', 'Completed on', 'Completed ITEM_TITLE.'),
                ],
                self::workScreens(true, true, false, false),
                self::search('Enquiries', 'Conveyances in enquiries.', 'Enquiries'),
                ['Complete'],
                self::conveyancingGuide()
            ),
            self::BLUEPRINT_CLAIMS => self::shape(
                'Insurance claims',
                'An insurance claim from the first notification until it is settled or withdrawn.',
                'Claim',
                'ic-shield',
                'Notified on',
                'Posts a notice and stamps the settled date when a claim is settled.',
                [$status(['Notified', 'With the insurer', 'Waiting on them', 'Settled', 'Withdrawn']), self::text('Client'), self::text('Insurer'), self::menu('Kind', ['Property', 'Motor', 'Liability', 'Travel']), self::text('Reference'), self::number('Reserve'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Notified on'), self::date('Settled on'), self::date('Withdrawn on')],
                [
                    self::when('Note the notification', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Notified on'),
                        self::logLine('Notified ITEM_TITLE.'),
                    ]),
                    self::when('Tell the owner the claim is with the insurer', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With the insurer', [
                        self::email('With the insurer: ITEM_TITLE', 'ITEM_TITLE is with the insurer.'),
                        self::logLine('Sent ITEM_TITLE to the insurer.'),
                        self::setField('Next step', 'With the insurer.'),
                    ]),
                    self::when('Note a file while it is with the insurer', Rules::EVENT_ATTACHMENT, 'Status', Rules::OP_EQ, 'With the insurer', [
                        self::email('A file on ITEM_TITLE', 'A file was added to ITEM_TITLE while it is with the insurer.'),
                        self::logLine('A file was added to ITEM_TITLE.'),
                    ]),
                    self::when('Note that a claim is waiting on them', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Waiting on them', [
                        self::email('Waiting on them: ITEM_TITLE', 'ITEM_TITLE is waiting on them.'),
                        self::logLine('ITEM_TITLE is waiting on them.'),
                        self::setField('Next step', 'Waiting on them.'),
                    ]),
                    self::when('Settle the claim', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Settled', [
                        self::stamp('Settled on'),
                        self::logLine('Settled ITEM_TITLE.'),
                        self::notice('Settled: ITEM_TITLE', 'ITEM_TITLE has been settled.'),
                        self::email('Settled: ITEM_TITLE', 'ITEM_TITLE has been settled.'),
                        self::setField('Next step', ''),
                    ]),
                    $close('Withdraw the claim', 'Withdrawn', 'Withdrawn on', 'Withdrew ITEM_TITLE.'),
                ],
                self::workScreens(true, true, true, false),
                self::search('With the insurer', 'Claims with the insurer.', 'With the insurer'),
                ['Settled', 'Withdrawn'],
                self::claimsGuide()
            ),
            self::BLUEPRINT_REPAIRS => self::shape(
                'Repairs',
                'A repair from the report until it is done.',
                'Repair',
                'ic-wrench',
                'Booked for',
                'Emails the owner when a repair is on site.',
                [$status(['Reported', 'Booked', 'On site', 'Done']), self::text('Place'), self::menu('Trade', ['Plumbing', 'Electrical', 'Joinery', 'Roof', 'Other']), self::text('Reported by'), self::text('Next step'), self::note(), self::check('Urgent'), self::date('Booked for'), self::date('Reported on'), self::date('Done on')],
                [
                    self::when('Note the report', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Reported on'),
                        self::logLine('Reported ITEM_TITLE.'),
                    ]),
                    self::when('Note the booking', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Booked', [
                        self::logLine('Booked ITEM_TITLE.'),
                        self::setField('Next step', 'Attend on the booked day.'),
                    ]),
                    self::when('Tell the owner someone is on site', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'On site', [
                        self::email('On site: ITEM_TITLE', 'ITEM_TITLE is on site.'),
                        self::logLine('ITEM_TITLE is on site.'),
                        self::setField('Next step', 'On site.'),
                    ]),
                    $close('Finish the repair', 'Done', 'Done on', 'Finished ITEM_TITLE.'),
                ],
                self::workScreens(true, true, false, true),
                self::search('On site', 'Repairs where someone is there now.', 'On site'),
                ['Done'],
                self::repairsGuide()
            ),
            self::BLUEPRINT_COMPLAINTS => self::shape(
                'Complaints',
                'A complaint from the day it is opened until it is upheld or not upheld.',
                'Complaint',
                'ic-complaint',
                'Upheld on',
                'Posts a notice and stamps the date when a complaint is upheld.',
                [$status(['Opened', 'With us', 'With them', 'Upheld', 'Not upheld']), self::text('Client'), self::menu('Channel', ['Letter', 'Phone', 'Email', 'Web']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Response due'), self::date('Opened on'), self::date('Upheld on'), self::date('Not upheld on')],
                [
                    self::when('Open the complaint', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Opened on'),
                        self::logLine('Opened ITEM_TITLE.'),
                    ]),
                    self::when('Tell the owner it is with us', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With us', [
                        self::email('With us: ITEM_TITLE', 'ITEM_TITLE is with us.'),
                        self::logLine('ITEM_TITLE is with us.'),
                        self::setField('Next step', 'Deal with the complaint.'),
                    ]),
                    self::when('Note that a complaint is with them', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With them', [
                        self::email('With them: ITEM_TITLE', 'ITEM_TITLE is with them.'),
                        self::logLine('ITEM_TITLE is with them.'),
                        self::setField('Next step', 'Waiting on their reply.'),
                    ]),
                    self::when('Uphold the complaint', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Upheld', [
                        self::stamp('Upheld on'),
                        self::logLine('Upheld ITEM_TITLE.'),
                        self::notice('Upheld: ITEM_TITLE', 'ITEM_TITLE has been upheld.'),
                        self::email('Upheld: ITEM_TITLE', 'ITEM_TITLE has been upheld.'),
                        self::setField('Next step', ''),
                    ]),
                    $close('Do not uphold the complaint', 'Not upheld', 'Not upheld on', 'Did not uphold ITEM_TITLE.'),
                ],
                self::workScreens(true, true, true, true),
                self::search('With us', 'Complaints that are with us.', 'With us'),
                ['Upheld', 'Not upheld'],
                self::complaintsGuide()
            ),
            self::BLUEPRINT_COMMITTEE => self::shape(
                'Committee papers',
                'A paper from the day it arrives until it is awarded or refused.',
                'Paper',
                'ic-landmark',
                'Sitting due',
                'Posts a notice and stamps the awarded date when a paper is awarded.',
                [$status(['Received', 'Reading', 'With the applicant', 'Awarded', 'Refused']), self::text('Applicant'), self::menu('Kind', ['Grant', 'Credit', 'Award']), self::number('Amount'), self::text('Next step'), self::note(), self::date('Sitting due'), self::date('Received on'), self::date('Awarded on'), self::date('Refused on')],
                [
                    self::when('Note the paper arrived', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Received on'),
                        self::logLine('Received ITEM_TITLE.'),
                    ]),
                    self::when('Start the reading', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Reading', [
                        self::logLine('Reading ITEM_TITLE.'),
                        self::setField('Next step', 'Finish the reading.'),
                    ]),
                    self::when('Ask the applicant', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With the applicant', [
                        self::email('With the applicant: ITEM_TITLE', 'ITEM_TITLE is with the applicant.'),
                        self::logLine('Asked the applicant for more on ITEM_TITLE.'),
                        self::setField('Next step', 'Waiting on the applicant.'),
                    ]),
                    self::when('Award the paper', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Awarded', [
                        self::stamp('Awarded on'),
                        self::logLine('Awarded ITEM_TITLE.'),
                        self::notice('Awarded: ITEM_TITLE', 'ITEM_TITLE has been awarded.'),
                        self::email('Awarded: ITEM_TITLE', 'ITEM_TITLE has been awarded.'),
                        self::setField('Next step', ''),
                    ]),
                    $close('Refuse the paper', 'Refused', 'Refused on', 'Refused ITEM_TITLE.'),
                ],
                self::workScreens(true, true, true, false),
                self::search('With the applicant', 'Papers waiting on the applicant.', 'With the applicant'),
                ['Awarded', 'Refused'],
                self::committeeGuide()
            ),
            self::BLUEPRINT_AUDIT => self::shape(
                'Audit findings',
                'A finding from the day it is raised until it is closed.',
                'Finding',
                'ic-audit',
                'Raised on',
                'Emails the owner when a finding is accepted.',
                [$status(['Raised', 'Accepted', 'In progress', 'Closed']), self::text('Place'), self::menu('Rating', ['High', 'Medium', 'Low']), self::text('Next step'), self::note(), self::date('Due'), self::date('Raised on'), self::date('Closed on')],
                [
                    self::when('Note the finding', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Raised on'),
                        self::logLine('Raised ITEM_TITLE.'),
                    ]),
                    self::when('Tell the owner the finding was accepted', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Accepted', [
                        self::email('Accepted: ITEM_TITLE', 'ITEM_TITLE has been accepted.'),
                        self::logLine('Accepted ITEM_TITLE.'),
                        self::setField('Next step', 'Start the work.'),
                    ]),
                    self::when('Note that the work is underway', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'In progress', [
                        self::logLine('Work is underway on ITEM_TITLE.'),
                        self::setField('Next step', 'Finish the work.'),
                    ]),
                    $close('Close the finding', 'Closed', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                self::workScreens(true, true, false, false),
                self::search('In progress', 'Findings being worked on.', 'In progress'),
                ['Closed'],
                self::auditGuide()
            ),
            self::BLUEPRINT_INCIDENTS => self::shape(
                'Incidents',
                'An incident from the report until it is closed.',
                'Incident',
                'ic-alert',
                'Action due',
                'Posts a notice when an incident is reported.',
                [$status(['Reported', 'Investigating', 'Actions underway', 'Closed']), self::text('Place'), self::menu('Kind', ['Injury', 'Near miss', 'Damage', 'Environment']), self::text('Reported by'), self::text('Next step'), self::note(), self::date('Action due'), self::date('Reported on'), self::date('Closed on')],
                [
                    self::when('Report the incident', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Reported on'),
                        self::email('Reported: ITEM_TITLE', 'ITEM_TITLE has been reported.'),
                        self::logLine('Reported ITEM_TITLE.'),
                        self::notice('Reported: ITEM_TITLE', 'ITEM_TITLE has been reported.'),
                    ]),
                    self::when('Note the investigation', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Investigating', [
                        self::logLine('Investigation opened on ITEM_TITLE.'),
                        self::setField('Next step', 'Speak to the people who were there.'),
                    ]),
                    self::when('Note a file while it is being investigated', Rules::EVENT_ATTACHMENT, 'Status', Rules::OP_EQ, 'Investigating', [
                        self::logLine('A file was added to ITEM_TITLE.'),
                    ]),
                    self::when('Note that the actions are underway', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Actions underway', [
                        self::logLine('Actions underway on ITEM_TITLE.'),
                        self::setField('Next step', 'Finish the actions.'),
                    ]),
                    $close('Close the incident', 'Closed', 'Closed on', 'Closed ITEM_TITLE.'),
                ],
                self::workScreens(true, true, true, true),
                self::search('Investigating', 'Incidents under investigation.', 'Investigating'),
                ['Closed'],
                self::incidentsGuide()
            ),
            self::BLUEPRINT_CANDIDATES => self::shape(
                'Recruitment',
                'A candidate from the first approach until they are placed or withdrawn.',
                'Candidate',
                'ic-user',
                'Listed on',
                'Posts a notice and stamps the placed date when a candidate is placed.',
                [$status(['Listed', 'Shortlist', 'Interviewed', 'Offer', 'Placed', 'Withdrawn']), self::text('Client'), self::text('Role'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Listed on'), self::date('Placed on'), self::date('Withdrawn on')],
                [
                    self::when('Note the candidate', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Listed on'),
                        self::logLine('Added ITEM_TITLE.'),
                    ]),
                    self::when('Tell the owner they are on the shortlist', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Shortlist', [
                        self::email('Shortlist: ITEM_TITLE', 'ITEM_TITLE is on the shortlist.'),
                        self::logLine('ITEM_TITLE is on the shortlist.'),
                        self::setField('Next step', 'Arrange the meeting.'),
                    ]),
                    self::when('Note the interview', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Interviewed', [
                        self::logLine('Interviewed ITEM_TITLE.'),
                        self::setField('Next step', 'Write up the meeting.'),
                    ]),
                    self::when('Note the offer', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Offer', [
                        self::email('Offer: ITEM_TITLE', 'An offer is out for ITEM_TITLE.'),
                        self::logLine('An offer is out for ITEM_TITLE.'),
                        self::setField('Next step', 'Waiting on the offer.'),
                    ]),
                    self::when('Place the candidate', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Placed', [
                        self::stamp('Placed on'),
                        self::logLine('Placed ITEM_TITLE.'),
                        self::notice('Placed: ITEM_TITLE', 'ITEM_TITLE has been placed.'),
                        self::email('Placed: ITEM_TITLE', 'ITEM_TITLE has been placed.'),
                        self::setField('Next step', ''),
                    ]),
                    $close('Withdraw the candidate', 'Withdrawn', 'Withdrawn on', 'Withdrew ITEM_TITLE.'),
                ],
                self::workScreens(true, true, true, false),
                self::search('Shortlist', 'Candidates on the shortlist.', 'Shortlist'),
                ['Placed', 'Withdrawn'],
                self::recruitmentGuide()
            ),
            self::BLUEPRINT_CONTRACTS => self::shape(
                'Contracts',
                'A contract from the day it arrives until it is signed or declined.',
                'Contract',
                'ic-contract',
                'Received on',
                'Hands a contract back to the person who opened it, and posts a notice when it is signed.',
                [$status(['Received', 'In review', 'With the business', 'Signed', 'Declined']), self::text('Client'), self::menu('Kind', ['Customer', 'Supplier', 'Employment', 'NDA']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Received on'), self::date('Signed on'), self::date('Declined on')],
                [
                    self::when('Note the contract arrived', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Received on'),
                        self::logLine('Received ITEM_TITLE.'),
                    ]),
                    self::when('Start the review', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'In review', [
                        self::logLine('Reviewing ITEM_TITLE.'),
                        self::setField('Next step', 'Read it.'),
                    ]),
                    self::when('Hand it back to the person who opened it', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'With the business', [
                        self::setOwner(Rules::OWNER_CREATOR),
                        self::email('With the business: ITEM_TITLE', 'ITEM_TITLE is back with the person who opened it.', Rules::TO_CREATOR),
                        self::logLine('Handed ITEM_TITLE back to the person who opened it.'),
                        self::setField('Next step', 'Back with the person who opened it.'),
                    ]),
                    self::when('Sign the contract', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Signed', [
                        self::stamp('Signed on'),
                        self::logLine('Signed ITEM_TITLE.'),
                        self::notice('Signed: ITEM_TITLE', 'ITEM_TITLE has been signed.'),
                        self::email('Signed: ITEM_TITLE', 'ITEM_TITLE has been signed.'),
                        self::setField('Next step', ''),
                    ]),
                    $close('Decline the contract', 'Declined', 'Declined on', 'Declined ITEM_TITLE.'),
                ],
                self::workScreens(true, true, true, false),
                self::search('In review', 'Contracts being read.', 'In review'),
                ['Signed', 'Declined'],
                self::contractsGuide()
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
                self::workScreens(false, false, false, false),
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
        array $permLevels = [],
        ?string $guide = null
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
        $statusId = 0;
        $stepId = 0;
        $dueId = 0;
        $dueFallback = 0;
        $openStatuses = [];
        $closed = array_values(array_filter(array_map('strval', $blueprints[$blueprint]['closed'] ?? [])));
        foreach ($fieldDefs as $field) {
            $fieldName = trim((string) ($field['name'] ?? ''));
            $fieldId = (int) ($fieldIdsByName[$fieldName] ?? 0);
            $plain = self::plainName($fieldName);
            if ($plain === 'status') {
                $statusId = $fieldId;
                $openStatuses = array_values(array_filter(array_map('strval', $field['values'] ?? [])));
                if ($closed !== []) {
                    $openStatuses = array_values(array_filter(
                        $openStatuses,
                        static fn (string $value): bool => !in_array($value, $closed, true)
                    ));
                } elseif (count($openStatuses) > 1) {
                    array_pop($openStatuses);
                }
            } elseif ($plain === 'next step') {
                $stepId = $fieldId;
            } elseif ($dueId === 0 && str_contains($plain, 'due')) {
                $dueId = $fieldId;
            } elseif (
                $dueFallback === 0
                && (string) ($field['type'] ?? '') === FieldTypes::DATE
                && !str_ends_with($plain, ' on')
            ) {
                $dueFallback = $fieldId;
            }
        }
        if ($dueId === 0) {
            $dueId = $dueFallback;
        }
        $applicationSettings = WorkflowFields::store([
            'default_item_type_id' => (string) $itemTypeId,
            'search_tab_added' => '1',
        ], $itemTypeId, $statusId, $stepId, $dueId, $openStatuses);
        $applicationId = ApplicationStore::insertApplication([
            'slug' => $slug,
            'name' => $name,
            'hint' => $hint !== '' ? $hint : $blueprints[$blueprint]['hint'],
            'guide' => $guide === null ? trim((string) ($blueprints[$blueprint]['guide'] ?? '')) : trim($guide),
            'icon' => $icon,
            'permission' => $usePerm,
            'enabled' => 1,
            'sort_order' => $max + 10,
            'entry_mode' => 'shell',
            'legacy_controller' => '',
            'legacy_key' => '',
            'settings_json' => json_encode($applicationSettings, JSON_UNESCAPED_SLASHES),
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
        unset($appName);

        return $desired;
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
     * The case-file applications, with work already on the board.
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
        self::deleteUnlinkedFields();
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
     * Bring showcase applications already on a site up to the current blueprints:
     * the screens the work uses, the guide, and the field labels.
     * Runs once, from the schema migration. A later edit on that site is left in place.
     */
    public static function alignInstalled(): void
    {
        if (!Database::tableExists('applications')) {
            return;
        }
        $blueprints = self::blueprints();
        foreach (self::showcase() as $slug => $spec) {
            $blueprint = (string) $spec['blueprint'];
            if (!isset($blueprints[$blueprint])) {
                continue;
            }
            self::alignOne($slug, $blueprints[$blueprint]);
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function alignOne(string $slug, array $meta): void
    {
        $app = Database::first('applications', ['application_id', 'settings_json'], 'slug = ?', [$slug]);
        if ($app === null) {
            return;
        }
        $applicationId = (int) $app['application_id'];
        $settings = json_decode((string) ($app['settings_json'] ?? ''), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $typeId = (int) ($settings['default_item_type_id'] ?? 0);
        Database::update('applications', [
            'guide' => trim((string) ($meta['guide'] ?? '')),
        ], 'application_id = ?', [$applicationId]);
        if ($typeId <= 0) {
            return;
        }
        self::canonicalFieldNames($typeId, $meta['fields'] ?? []);
        self::alignNavigation($applicationId, $typeId, $meta['screens'] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private static function canonicalFieldNames(int $typeId, array $fields): void
    {
        foreach ($fields as $field) {
            $canonical = trim((string) ($field['name'] ?? ''));
            if ($canonical === '') {
                continue;
            }
            $id = self::fieldIdOnType($typeId, $canonical);
            if ($id <= 0) {
                continue;
            }
            $row = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [$id]);
            if ($row !== null && (string) $row['custom_field_name'] !== $canonical) {
                Database::update('custom_fields', ['custom_field_name' => $canonical], 'custom_field_id = ?', [$id]);
            }
        }
    }

    /**
     * @param list<string> $screens
     */
    private static function alignNavigation(int $applicationId, int $typeId, array $screens): void
    {
        if (!Database::tableExists('application_nav') || $screens === []) {
            return;
        }
        $labels = ScreenCatalog::builderScreens();
        $known = array_keys($labels);
        $navs = Database::select('application_nav', '*', 'application_id = ?', [$applicationId], 'sort_order ASC, nav_id ASC');
        $keep = [];
        foreach ($navs as $nav) {
            $capability = ScreenCatalog::resolve((string) $nav['capability']);
            if (!in_array($capability, $known, true)) {
                continue;
            }
            if (!in_array($capability, $screens, true) || isset($keep[$capability])) {
                Database::delete('application_nav', 'nav_id = ?', [(int) $nav['nav_id']]);
                continue;
            }
            $keep[$capability] = $nav;
        }
        $order = 10;
        $typeConfig = json_encode(['item_type_id' => (string) $typeId], JSON_UNESCAPED_SLASHES) ?: '{}';
        foreach ($screens as $capability) {
            if (!is_string($capability) || !isset($labels[$capability])) {
                continue;
            }
            if (isset($keep[$capability])) {
                Database::update('application_nav', [
                    'label' => $labels[$capability],
                    'sort_order' => $order,
                    'icon' => self::screenIcon($capability),
                ], 'nav_id = ?', [(int) $keep[$capability]['nav_id']]);
            } else {
                Database::insert('application_nav', [
                    'application_id' => $applicationId,
                    'label' => $labels[$capability],
                    'capability' => $capability,
                    'permission' => '',
                    'icon' => self::screenIcon($capability),
                    'config_json' => self::screenNeedsType($capability) ? $typeConfig : '{}',
                    'sort_order' => $order,
                ]);
            }
            $order += 10;
        }
    }

    private static function screenNeedsType(string $capability): bool
    {
        return in_array($capability, [
            ScreenCatalog::WORK,
            ScreenCatalog::CREATE,
            ScreenCatalog::SEARCH_ADVANCED,
            ScreenCatalog::DIARY,
            ScreenCatalog::PARTIES,
            ScreenCatalog::INTAKE,
        ], true);
    }

    private static function screenIcon(string $capability): string
    {
        return [
            ScreenCatalog::WORK => 'ic-work',
            ScreenCatalog::CREATE => 'ic-plus',
            ScreenCatalog::SEARCH_ADVANCED => 'ic-search',
            ScreenCatalog::SEARCH_LIST => 'ic-saved',
            ScreenCatalog::ANNOUNCEMENTS => 'ic-announce',
            ScreenCatalog::DIARY => 'ic-diary',
            ScreenCatalog::PARTIES => 'ic-parties',
            ScreenCatalog::INTAKE => 'ic-intake',
        ][$capability] ?? '';
    }

    /**
     * @return array<string, array{blueprint: string, sort: int, cards: list<array{title: string, fields: array<string, string>}>, notice?: array{subject: string, message: string}}>
     */
    private static function showcase(): array
    {
        return [
            'publishing' => [
                'blueprint' => self::BLUEPRINT_PUBLISHING,
                'sort' => 5,
                'cards' => [
                    ['title' => 'Home to Roost', 'fields' => ['Status' => 'Approved', 'Writer' => 'Carole Hocking', 'Kind' => 'Novel', 'Word count' => '86000', 'Note' => 'Accepted for publication. The line is ready.', 'Received on' => '02-06-2026', 'Approved on' => '18-10-2026']],
                    ['title' => 'A Place In Time', 'fields' => ['Status' => 'Editing', 'Writer' => 'Carole Hocking', 'Kind' => 'Novel', 'Word count' => '91000', 'Next step' => 'Finish the edit.', 'Note' => 'The rewrite is in. The last chapters hold.', 'Received on' => '14-07-2026']],
                    ['title' => 'Old Farts On A Bus', 'fields' => ['Status' => 'With the writer', 'Writer' => 'Carole Hocking', 'Kind' => 'Novel', 'Word count' => '72000', 'Next step' => 'Waiting on the rewrite.', 'Note' => 'The journey is the book. A new last chapter was asked for.', 'Reply due' => '19-11-2026', 'Received on' => '03-09-2026']],
                    ['title' => 'Damaged Good', 'fields' => ['Status' => 'Reading', 'Writer' => 'Carole Hocking', 'Kind' => 'Novel', 'Word count' => '68000', 'Next step' => 'Finish the reading.', 'Note' => 'A clear opening. The reading is underway.', 'Received on' => '06-10-2026']],
                ],
                'notice' => [
                    'subject' => 'Home to Roost',
                    'message' => 'Home to Roost has been approved for publication.',
                ],
            ],
            'matters' => [
                'blueprint' => self::BLUEPRINT_MATTERS,
                'sort' => 10,
                'cards' => [
                    ['title' => 'Reid lease', 'fields' => ['Status' => 'Waiting on us', 'Client' => 'Reid', 'Kind' => 'Contract', 'Reference' => 'R-1044', 'Next step' => 'Take the next action.', 'Note' => 'The break clause is to come out.', 'Opened on' => '02-09-2026']],
                    ['title' => 'Reid advice', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Reid', 'Kind' => 'Advice', 'Reference' => 'R-1102', 'Next step' => 'Waiting on their reply.', 'Note' => 'Asked for the lease note.', 'Reply due' => '12-11-2026', 'Opened on' => '20-09-2026']],
                    ['title' => 'Nguyen dispute', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Nguyen', 'Kind' => 'Dispute', 'Reference' => 'N-220', 'Next step' => 'Waiting on their reply.', 'Reply due' => '06-11-2026', 'Opened on' => '14-08-2026']],
                    ['title' => 'Walsh licence', 'fields' => ['Status' => 'Done', 'Client' => 'Walsh', 'Kind' => 'Property', 'Reference' => 'W-7', 'Note' => 'The file is complete.', 'Opened on' => '01-06-2026', 'Closed on' => '02-10-2026']],
                ],
            ],
            'conveyancing' => [
                'blueprint' => self::BLUEPRINT_CONVEYANCING,
                'sort' => 20,
                'cards' => [
                    ['title' => 'Barton Street sale', 'fields' => ['Status' => 'Enquiries', 'Client' => 'Reid', 'Place' => '14 Barton Street', 'Side' => 'Sale', 'Reference' => 'C-441', 'Next step' => 'Reply on the enquiries.', 'Note' => 'The boundary is the open point.', 'Reply due' => '14-11-2026', 'Instructed on' => '04-08-2026']],
                    ['title' => 'Reid purchase', 'fields' => ['Status' => 'Searches', 'Client' => 'Reid', 'Place' => 'Kingston', 'Side' => 'Purchase', 'Reference' => 'C-460', 'Next step' => 'Follow up the searches.', 'Instructed on' => '01-10-2026']],
                    ['title' => 'Campbell unit', 'fields' => ['Status' => 'Instructed', 'Client' => 'Campbell', 'Place' => '8 Civic', 'Side' => 'Purchase', 'Reference' => 'C-450', 'Note' => 'Instructions are still to come.', 'Instructed on' => '20-10-2026']],
                    ['title' => 'Walsh leasehold', 'fields' => ['Status' => 'Complete', 'Client' => 'Walsh', 'Place' => '2 Braddon', 'Side' => 'Sale', 'Reference' => 'C-438', 'Note' => 'Exchange and completion are done.', 'Instructed on' => '02-06-2026', 'Completed on' => '18-09-2026']],
                ],
            ],
            'claims' => [
                'blueprint' => self::BLUEPRINT_CLAIMS,
                'sort' => 30,
                'cards' => [
                    ['title' => 'Reid roof', 'fields' => ['Status' => 'With the insurer', 'Client' => 'Reid', 'Insurer' => 'QBE', 'Kind' => 'Property', 'Reference' => 'CL-19', 'Reserve' => '18000', 'Next step' => 'With the insurer.', 'Note' => 'The photos are still to go.', 'Reply due' => '20-11-2026', 'Notified on' => '12-09-2026']],
                    ['title' => 'Reid motor', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Reid', 'Insurer' => 'Allianz', 'Kind' => 'Motor', 'Reference' => 'CL-31', 'Reserve' => '4200', 'Next step' => 'Waiting on them.', 'Reply due' => '05-11-2026', 'Notified on' => '02-08-2026']],
                    ['title' => 'Civic stock', 'fields' => ['Status' => 'Notified', 'Client' => 'Campbell', 'Insurer' => 'QBE', 'Kind' => 'Liability', 'Reference' => 'CL-30', 'Reserve' => '25000', 'Note' => 'The file is just opened.', 'Notified on' => '20-10-2026']],
                    ['title' => 'Walsh flood', 'fields' => ['Status' => 'Settled', 'Client' => 'Walsh', 'Insurer' => 'QBE', 'Kind' => 'Property', 'Reference' => 'CL-11', 'Reserve' => '9100', 'Note' => 'The settlement is on the file.', 'Notified on' => '01-07-2026', 'Settled on' => '28-09-2026']],
                ],
                'notice' => [
                    'subject' => 'Walsh flood',
                    'message' => 'Walsh flood has been settled.',
                ],
            ],
            'repairs' => [
                'blueprint' => self::BLUEPRINT_REPAIRS,
                'sort' => 40,
                'cards' => [
                    ['title' => 'Boiler, flat 4', 'fields' => ['Status' => 'On site', 'Place' => 'Flat 4', 'Trade' => 'Plumbing', 'Reported by' => 'Porter', 'Next step' => 'On site.', 'Urgent' => 'Yes', 'Booked for' => '27-10-2026', 'Reported on' => '22-10-2026']],
                    ['title' => 'Tap, flat 4', 'fields' => ['Status' => 'Booked', 'Place' => 'Flat 4', 'Trade' => 'Plumbing', 'Reported by' => 'Porter', 'Next step' => 'Attend on the booked day.', 'Booked for' => '30-10-2026', 'Reported on' => '25-10-2026']],
                    ['title' => 'Gate latch', 'fields' => ['Status' => 'Reported', 'Place' => 'Yard', 'Trade' => 'Joinery', 'Reported by' => 'Reid', 'Note' => 'The latch does not catch.', 'Reported on' => '26-10-2026']],
                    ['title' => 'Roof, block C', 'fields' => ['Status' => 'Done', 'Place' => 'Block C', 'Trade' => 'Roof', 'Reported by' => 'Estate', 'Note' => 'The invoice is on the file.', 'Reported on' => '01-10-2026', 'Done on' => '11-10-2026']],
                ],
            ],
            'complaints' => [
                'blueprint' => self::BLUEPRINT_COMPLAINTS,
                'sort' => 50,
                'cards' => [
                    ['title' => 'Missed appointment', 'fields' => ['Status' => 'With us', 'Client' => 'Reid', 'Channel' => 'Phone', 'Reference' => 'CO-12', 'Next step' => 'Deal with the complaint.', 'Response due' => '01-12-2026', 'Opened on' => '10-10-2026']],
                    ['title' => 'Reid letter', 'fields' => ['Status' => 'With them', 'Client' => 'Reid', 'Channel' => 'Letter', 'Reference' => 'CO-15', 'Next step' => 'Waiting on their reply.', 'Response due' => '18-11-2026', 'Opened on' => '01-10-2026']],
                    ['title' => 'Website form', 'fields' => ['Status' => 'Opened', 'Client' => 'Campbell', 'Channel' => 'Web', 'Reference' => 'CO-14', 'Note' => 'The form came in overnight.', 'Response due' => '14-12-2026', 'Opened on' => '24-10-2026']],
                    ['title' => 'Late refund', 'fields' => ['Status' => 'Upheld', 'Client' => 'Walsh', 'Channel' => 'Email', 'Reference' => 'CO-4', 'Note' => 'The refund was paid.', 'Opened on' => '01-08-2026', 'Upheld on' => '20-09-2026']],
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
                    ['title' => 'Barton fund', 'fields' => ['Status' => 'With the applicant', 'Applicant' => 'Barton trust', 'Kind' => 'Grant', 'Amount' => '40000', 'Next step' => 'Waiting on the applicant.', 'Note' => 'The budget was asked for.', 'Sitting due' => '05-11-2026', 'Received on' => '12-09-2026']],
                    ['title' => 'Barton hall', 'fields' => ['Status' => 'Reading', 'Applicant' => 'Barton trust', 'Kind' => 'Grant', 'Amount' => '15000', 'Next step' => 'Finish the reading.', 'Sitting due' => '05-11-2026', 'Received on' => '02-10-2026']],
                    ['title' => 'Campbell facility', 'fields' => ['Status' => 'Received', 'Applicant' => 'Campbell', 'Kind' => 'Credit', 'Amount' => '120000', 'Note' => 'The security is still to be checked.', 'Received on' => '20-10-2026']],
                    ['title' => 'Walsh award', 'fields' => ['Status' => 'Awarded', 'Applicant' => 'Walsh', 'Kind' => 'Award', 'Amount' => '5000', 'Note' => 'The letter is on the file.', 'Received on' => '01-08-2026', 'Awarded on' => '03-10-2026']],
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
                    ['title' => 'Cash handling', 'fields' => ['Status' => 'Accepted', 'Place' => 'Front office', 'Rating' => 'High', 'Next step' => 'Start the work.', 'Due' => '30-10-2026', 'Raised on' => '01-10-2026']],
                    ['title' => 'Petty cash', 'fields' => ['Status' => 'In progress', 'Place' => 'Front office', 'Rating' => 'Medium', 'Next step' => 'Finish the work.', 'Due' => '14-11-2026', 'Raised on' => '15-09-2026']],
                    ['title' => 'Supplier list', 'fields' => ['Status' => 'Raised', 'Place' => 'Accounts', 'Rating' => 'Low', 'Note' => 'The last twelve months are still to be pulled.', 'Due' => '28-11-2026', 'Raised on' => '20-10-2026']],
                    ['title' => 'Fire doors', 'fields' => ['Status' => 'Closed', 'Place' => 'West stair', 'Rating' => 'High', 'Note' => 'The check is on the file.', 'Raised on' => '01-08-2026', 'Closed on' => '19-09-2026']],
                ],
            ],
            'incidents' => [
                'blueprint' => self::BLUEPRINT_INCIDENTS,
                'sort' => 80,
                'cards' => [
                    ['title' => 'Slip, west stair', 'fields' => ['Status' => 'Investigating', 'Place' => 'West stair', 'Kind' => 'Injury', 'Reported by' => 'Porter', 'Next step' => 'Speak to the people who were there.', 'Action due' => '31-10-2026', 'Reported on' => '21-10-2026']],
                    ['title' => 'Rail, west stair', 'fields' => ['Status' => 'Actions underway', 'Place' => 'West stair', 'Kind' => 'Near miss', 'Reported by' => 'Porter', 'Next step' => 'Finish the actions.', 'Action due' => '07-11-2026', 'Reported on' => '18-10-2026']],
                    ['title' => 'Flood, store', 'fields' => ['Status' => 'Reported', 'Place' => 'Store', 'Kind' => 'Damage', 'Reported by' => 'Estate', 'Note' => 'The stock is still wet.', 'Reported on' => '26-10-2026']],
                    ['title' => 'Cut, kitchen', 'fields' => ['Status' => 'Closed', 'Place' => 'Kitchen', 'Kind' => 'Injury', 'Reported by' => 'Chef', 'Note' => 'The note is on the file.', 'Reported on' => '01-10-2026', 'Closed on' => '07-10-2026']],
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
                    ['title' => 'A. Reid', 'fields' => ['Status' => 'Shortlist', 'Client' => 'Campbell', 'Role' => 'Finance lead', 'Next step' => 'Arrange the meeting.', 'Listed on' => '01-10-2026']],
                    ['title' => 'J. Nguyen', 'fields' => ['Status' => 'Listed', 'Client' => 'Campbell', 'Role' => 'Finance lead', 'Note' => 'The brief is still to be read.', 'Listed on' => '20-10-2026']],
                    ['title' => 'M. Walsh', 'fields' => ['Status' => 'Offer', 'Client' => 'Barton', 'Role' => 'Operations', 'Next step' => 'Waiting on the offer.', 'Reply due' => '06-11-2026', 'Listed on' => '12-09-2026']],
                    ['title' => 'S. Campbell', 'fields' => ['Status' => 'Placed', 'Client' => 'Nguyen', 'Role' => 'Counsel', 'Note' => 'The placement is confirmed.', 'Listed on' => '01-08-2026', 'Placed on' => '15-10-2026']],
                ],
                'notice' => [
                    'subject' => 'S. Campbell',
                    'message' => 'S. Campbell has been placed.',
                ],
            ],
            'contracts' => [
                'blueprint' => self::BLUEPRINT_CONTRACTS,
                'sort' => 100,
                'cards' => [
                    ['title' => 'Reid services', 'fields' => ['Status' => 'In review', 'Client' => 'Reid', 'Kind' => 'Customer', 'Reference' => 'K-100', 'Next step' => 'Read it.', 'Note' => 'The liability cap is the open point.', 'Received on' => '10-10-2026']],
                    ['title' => 'Reid schedule', 'fields' => ['Status' => 'With the business', 'Client' => 'Reid', 'Kind' => 'Customer', 'Reference' => 'K-112', 'Next step' => 'Back with the person who opened it.', 'Reply due' => '13-11-2026', 'Received on' => '01-09-2026']],
                    ['title' => 'Campbell NDA', 'fields' => ['Status' => 'Received', 'Client' => 'Campbell', 'Kind' => 'NDA', 'Reference' => 'K-110', 'Note' => 'It arrived this week.', 'Received on' => '22-10-2026']],
                    ['title' => 'Walsh employment', 'fields' => ['Status' => 'Signed', 'Client' => 'Walsh', 'Kind' => 'Employment', 'Reference' => 'K-74', 'Note' => 'The copy is on the file.', 'Received on' => '01-08-2026', 'Signed on' => '15-09-2026']],
                ],
                'notice' => [
                    'subject' => 'Walsh employment',
                    'message' => 'Walsh employment has been signed.',
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
     * Work, New, then the screens this work uses, then Search and Saved.
     *
     * @return list<string>
     */
    private static function workScreens(bool $diary, bool $parties, bool $announcements, bool $intake): array
    {
        $screens = [ScreenCatalog::WORK, ScreenCatalog::CREATE];
        if ($diary) {
            $screens[] = ScreenCatalog::DIARY;
        }
        if ($parties) {
            $screens[] = ScreenCatalog::PARTIES;
        }
        if ($announcements) {
            $screens[] = ScreenCatalog::ANNOUNCEMENTS;
        }
        if ($intake) {
            $screens[] = ScreenCatalog::INTAKE;
        }
        $screens[] = ScreenCatalog::SEARCH_ADVANCED;
        $screens[] = ScreenCatalog::SEARCH_LIST;

        return $screens;
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
        ?array $search,
        array $closed = [],
        string $guide = ''
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
            'closed' => $closed,
            'guide' => $guide,
        ];
    }

    private static function publishingGuide(): string
    {
        return <<<'TEXT'
Each record is one manuscript. The title of the book is the name of the record. The writer is named on the card, and the editor who has it is the owner.

Move Status as the book moves. Received means it has arrived. Reading means an editor has it. With the writer means a rewrite has been asked for. Editing means the rewrite is back and the house is editing. Approved means the book will be published. Declined means it will not.

When you ask for a rewrite, set Reply due to the day you expect it, then write to the writer yourself. The application records the request and sends you an email. Parties lists every manuscript under the writer's name. The diary shows a rewrite that is overdue.

A notice is posted when a manuscript is approved. A decline is written in the log and stays off the wall.
TEXT;
    }

    private static function mattersGuide(): string
    {
        return <<<'TEXT'
Each record is one matter. The name of the matter is the name of the record. The client is named on the card, and the person who has the file is the owner.

Move Status as the file moves. Waiting on us means the next action is ours. Waiting on them means a reply has been asked for. Done means the matter is finished.

Set Reply due when you are waiting on them. The application stamps Opened on when the matter is created, writes the log, and emails you when the status moves. You still write to the client yourself. Parties lists every matter under the client's name. The diary shows a reply that is overdue. A finished matter is stamped Closed on.
TEXT;
    }

    private static function conveyancingGuide(): string
    {
        return <<<'TEXT'
Each record is one conveyance. The name of the record is the property. The client is named on the card, and the person who has the file is the owner.

Move Status as the file moves. Instructed means the work has been taken on. Searches means the searches are out. Enquiries means questions are being asked. Exchange means the parties are ready to exchange. Complete means the transaction has finished.

Set Reply due while the file is in enquiries, then write to the other side yourself. The application stamps Instructed on when the record is created, writes the log, and emails you when enquiries start. Parties lists every conveyance under the client's name. The diary shows a reply that is overdue. A completed conveyance is stamped Completed on.
TEXT;
    }

    private static function claimsGuide(): string
    {
        return <<<'TEXT'
Each record is one insurance claim. The name of the claim is the name of the record. The client is named on the card, and the person handling the claim is the owner.

Move Status as the claim moves. Notified means it has been reported. With the insurer means it has been sent to the insurer. Waiting on them means a reply has been asked for. Settled means the claim has been paid. Withdrawn means it will not proceed.

Set Reply due while you are waiting, and send the papers yourself. The application stamps Notified on when the claim is created, writes the log, and emails you when it goes to the insurer. A file added while it is with the insurer is written in the log. Parties lists every claim under the client's name. The diary shows a reply that is overdue.

A notice is posted when a claim is settled. A withdrawal is written in the log and stays off the wall.
TEXT;
    }

    private static function repairsGuide(): string
    {
        return <<<'TEXT'
Each record is one repair. The name of the repair is the name of the record. The place is named on the card, and the person arranging the repair is the owner.

Move Status as the job moves. Reported means it has been raised. Booked means a day has been set. On site means someone is there. Done means the repair is finished.

Set Booked for to the day of the visit. Report a repair from Intake, or add the record yourself. The application stamps Reported on when the record is created, writes the log, and emails you when someone is on site. An urgent repair is attended first. Parties lists every repair under the place. The diary shows a booking that is due. A finished repair is stamped Done on.
TEXT;
    }

    private static function complaintsGuide(): string
    {
        return <<<'TEXT'
Each record is one complaint. The name of the complaint is the name of the record. The client is named on the card, and the person dealing with it is the owner.

Move Status as the complaint moves. Opened means it has arrived. With us means we are dealing with it. With them means a reply has been asked for. Upheld means the complaint is accepted. Not upheld means it is not.

Set Response due to the day a reply is required, then write the reply yourself. The application stamps Opened on when the complaint is created and writes the log. A response is due within eight weeks of opening. Parties lists every complaint under the client's name. The diary shows a response that is overdue.

A notice is posted when a complaint is upheld. A complaint that is not upheld is written in the log and stays off the wall.
TEXT;
    }

    private static function committeeGuide(): string
    {
        return <<<'TEXT'
Each record is one paper. The name of the paper is the name of the record. The applicant is named on the card, and the person reading it is the owner.

Move Status as the paper moves. Received means it has arrived. Reading means it is being read. With the applicant means a further question has been asked. Awarded means the paper is granted. Refused means it is not.

Set Sitting due to the day the committee sits. Ask the applicant yourself when you need more. The application stamps Received on when the paper is created, writes the log, and emails you when it is with the applicant. Parties lists every paper under the applicant's name. The diary shows a sitting that is due.

A notice is posted when a paper is awarded. A refusal is written in the log and stays off the wall.
TEXT;
    }

    private static function auditGuide(): string
    {
        return <<<'TEXT'
Each record is one finding. The name of the finding is the name of the record. The place is named on the card, and the person responsible for it is the owner.

Move Status as the finding moves. Raised means it has been recorded. Accepted means the finding is agreed and the work has not started. In progress means the work is underway. Closed means the finding is finished.

Set Due to the day the work should be finished. The application stamps Raised on when the finding is created, writes the log, and emails you when it is accepted. Parties lists every finding under the place. The diary shows work that is overdue. A closed finding is stamped Closed on.
TEXT;
    }

    private static function incidentsGuide(): string
    {
        return <<<'TEXT'
Each record is one incident. The name of the incident is the name of the record. The place is named on the card, and the person following it up is the owner.

Move Status as the incident moves. Reported means it has been raised. Investigating means the facts are being gathered. Actions underway means the work to put it right has started. Closed means the incident is finished.

Set Action due to the day the actions should be finished. Report an incident from Intake, or add the record yourself. The application stamps Reported on when the record is created, emails you, and writes the log. A file added while it is being investigated is written in the log. Parties lists every incident under the place. The diary shows an action that is overdue.

A notice is posted when an incident is reported.
TEXT;
    }

    private static function recruitmentGuide(): string
    {
        return <<<'TEXT'
Each record is one candidate. The candidate's name is the name of the record. The client is named on the card, and the person handling the search is the owner.

Move Status as the search moves. Listed means the candidate is on the list. Shortlist means they have been chosen to meet. Interviewed means the meeting has happened. Offer means an offer is out. Placed means they have been placed. Withdrawn means they will not proceed.

Set Reply due when you make an offer, then write to the candidate yourself. The application stamps Listed on when the record is created, writes the log, and emails you when they reach the shortlist. Parties lists every candidate under the client's name. The diary shows an offer that is overdue.

A notice is posted when a candidate is placed. A withdrawal is written in the log and stays off the wall.
TEXT;
    }

    private static function contractsGuide(): string
    {
        return <<<'TEXT'
Each record is one contract. The name of the contract is the name of the record. The client is named on the card, and the person who has it is the owner.

Move Status as the contract moves. Received means it has arrived. In review means it is being read. With the business means it has gone back to the person who opened it. Signed means it is signed. Declined means it will not proceed.

Set Reply due while it is with the business. The application stamps Received on when the contract is created and writes the log. When it moves to With the business, it is handed back to the person who opened it and that person is emailed. Parties lists every contract under the client's name. The diary shows a reply that is overdue.

A notice is posted when a contract is signed. A decline is written in the log and stays off the wall.
TEXT;
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
