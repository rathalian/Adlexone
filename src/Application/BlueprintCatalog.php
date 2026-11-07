<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Data\FieldTypes;


final class BlueprintCatalog
{


    /**
     * @return array<string, array{label: string, hint: string, record: string, icon: string, rule: string, fields: list<array<string, mixed>>, screens: list<string>}>
     */
    public static function blueprints(): array
    {
        $status = static fn (array $values, array $waits = []): array => self::menu('Status', $values, $waits);
        $close = static function (string $name, string $status, string $date, string $line): array {
            return self::when($name, Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, $status, [
                self::stamp($date),
                self::logLine($line),
                self::setField('Next step', ''),
            ]);
        };

        return [
            ApplicationBuilder::BLUEPRINT_PUBLISHING => self::shape(
                'Publishing',
                'A manuscript from the day it arrives until it is approved or declined.',
                'Manuscript',
                'ic-book',
                'Writer',
                'Posts a notice and stamps the approved date when a manuscript is approved.',
                [
                    $status(['Received', 'Reading', 'With the writer', 'Editing', 'Approved', 'Declined'], ['With the writer']),
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
            ApplicationBuilder::BLUEPRINT_MATTERS => self::shape(
                'Client matters',
                'A matter from the instruction until it is done.',
                'Matter',
                'ic-briefcase',
                'Opened on',
                'Stamps the closed date when a matter is done.',
                [$status(['Waiting on us', 'Waiting on them', 'Done'], ['Waiting on them']), self::text('Client'), self::menu('Kind', ['Contract', 'Dispute', 'Advice', 'Property']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Opened on'), self::date('Closed on')],
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
            ApplicationBuilder::BLUEPRINT_CONVEYANCING => self::shape(
                'Conveyancing',
                'A sale or a purchase, from instruction to completion.',
                'Conveyance',
                'ic-home',
                'Instructed on',
                'Stamps the completed date when a conveyance is complete.',
                [$status(['Instructed', 'Searches', 'Enquiries', 'Exchange', 'Complete'], ['Enquiries']), self::text('Client'), self::text('Place'), self::menu('Side', ['Sale', 'Purchase']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Instructed on'), self::date('Completed on')],
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
            ApplicationBuilder::BLUEPRINT_CLAIMS => self::shape(
                'Insurance claims',
                'An insurance claim from the first notification until it is settled or withdrawn.',
                'Claim',
                'ic-shield',
                'Notified on',
                'Posts a notice and stamps the settled date when a claim is settled.',
                [$status(['Notified', 'With the insurer', 'Waiting on them', 'Settled', 'Withdrawn'], ['With the insurer', 'Waiting on them']), self::text('Client'), self::text('Insurer'), self::menu('Kind', ['Property', 'Motor', 'Liability', 'Travel']), self::text('Reference'), self::number('Reserve'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Notified on'), self::date('Settled on'), self::date('Withdrawn on')],
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
            ApplicationBuilder::BLUEPRINT_LEAVE => self::shape(
                'Leave requests',
                'A request from the day it is asked until it is approved or declined.',
                'Request',
                'ic-leave',
                'First day',
                'Posts a notice and stamps the approved date when a request is approved.',
                [$status(['Asked', 'Approved', 'Declined']), self::text('Person'), self::date('First day'), self::date('Last day'), self::text('Next step'), self::note(), self::date('Asked on'), self::date('Approved on'), self::date('Declined on')],
                [
                    self::when('Note the request', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Asked on'),
                        self::logLine('Asked for ITEM_TITLE.'),
                        self::setField('Next step', 'Decide the request.'),
                    ]),
                    self::when('Approve the request', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Approved', [
                        self::stamp('Approved on'),
                        self::logLine('Approved ITEM_TITLE.'),
                        self::notice('Approved: ITEM_TITLE', 'ITEM_TITLE has been approved.'),
                        self::email('Approved: ITEM_TITLE', 'ITEM_TITLE has been approved.'),
                        self::setField('Next step', ''),
                    ]),
                    self::when('Decline the request', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Declined', [
                        self::stamp('Declined on'),
                        self::logLine('Declined ITEM_TITLE.'),
                        self::setField('Next step', ''),
                    ]),
                ],
                self::workScreens(true, true, true, false),
                self::search('Asked', 'Requests still to decide.', 'Asked'),
                ['Approved', 'Declined'],
                self::leaveGuide()
            ),
            ApplicationBuilder::BLUEPRINT_COMPLAINTS => self::shape(
                'Complaints',
                'A complaint from the day it is opened until it is upheld or not upheld.',
                'Complaint',
                'ic-complaint',
                'Upheld on',
                'Posts a notice and stamps the date when a complaint is upheld.',
                [$status(['Opened', 'With us', 'With them', 'Upheld', 'Not upheld'], ['With them']), self::text('Client'), self::menu('Channel', ['Letter', 'Phone', 'Email', 'Web']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Response due'), self::date('Opened on'), self::date('Upheld on'), self::date('Not upheld on')],
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
            ApplicationBuilder::BLUEPRINT_COMMITTEE => self::shape(
                'Committee papers',
                'A paper from the day it arrives until it is awarded or refused.',
                'Paper',
                'ic-landmark',
                'Sitting due',
                'Posts a notice and stamps the awarded date when a paper is awarded.',
                [$status(['Received', 'Reading', 'With the applicant', 'Awarded', 'Refused'], ['With the applicant']), self::text('Applicant'), self::menu('Kind', ['Grant', 'Credit', 'Award']), self::number('Amount'), self::text('Next step'), self::note(), self::date('Sitting due'), self::date('Received on'), self::date('Awarded on'), self::date('Refused on')],
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
            ApplicationBuilder::BLUEPRINT_AUDIT => self::shape(
                'Audit findings',
                'A finding from the day it is raised until it is closed.',
                'Finding',
                'ic-audit',
                'Raised on',
                'Emails the owner when a finding is accepted.',
                [$status(['Raised', 'Accepted', 'In progress', 'Closed'], ['Accepted']), self::text('Place'), self::menu('Rating', ['High', 'Medium', 'Low']), self::text('Next step'), self::note(), self::date('Due'), self::date('Raised on'), self::date('Closed on')],
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
            ApplicationBuilder::BLUEPRINT_INCIDENTS => self::shape(
                'Incidents',
                'An incident from the report until it is closed.',
                'Incident',
                'ic-alert',
                'Action due',
                'Posts a notice when an incident is reported.',
                [$status(['Reported', 'Investigating', 'Actions underway', 'Closed'], ['Actions underway']), self::text('Place'), self::menu('Kind', ['Injury', 'Near miss', 'Damage', 'Environment']), self::text('Reported by'), self::text('Next step'), self::note(), self::date('Action due'), self::date('Reported on'), self::date('Closed on')],
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
            ApplicationBuilder::BLUEPRINT_CANDIDATES => self::shape(
                'Recruitment',
                'A candidate from the first approach until they are placed or withdrawn.',
                'Candidate',
                'ic-user',
                'Listed on',
                'Posts a notice and stamps the placed date when a candidate is placed.',
                [$status(['Listed', 'Shortlist', 'Interviewed', 'Offer', 'Placed', 'Withdrawn'], ['Offer']), self::text('Client'), self::text('Role'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Listed on'), self::date('Placed on'), self::date('Withdrawn on')],
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
            ApplicationBuilder::BLUEPRINT_CONTRACTS => self::shape(
                'Contracts',
                'A contract from the day it arrives until it is signed or declined.',
                'Contract',
                'ic-contract',
                'Received on',
                'Hands a contract back to the person who opened it, and posts a notice when it is signed.',
                [$status(['Received', 'In review', 'With the business', 'Signed', 'Declined'], ['With the business']), self::text('Client'), self::menu('Kind', ['Customer', 'Supplier', 'Employment', 'NDA']), self::text('Reference'), self::text('Next step'), self::note(), self::date('Reply due'), self::date('Received on'), self::date('Signed on'), self::date('Declined on')],
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
            ApplicationBuilder::BLUEPRINT_REPAIRS => self::shape(
                'Repairs',
                'A repair from the day it is reported until it is fixed or called off.',
                'Repair',
                'ic-wrench',
                'Reported by',
                'Posts a notice and stamps the fixed date when a repair is fixed.',
                [
                    $status(['Reported', 'Under repair', 'Waiting on a part', 'Fixed', 'Called off'], ['Waiting on a part']),
                    self::text('Where'),
                    ['name' => 'What is wrong', 'type' => FieldTypes::TEXT_AREA],
                    self::menu('Priority', ['When you can', 'Today', 'Now']),
                    self::text('Reported by'),
                    self::text('Next step'),
                    self::date('Part due'),
                    self::number('Cost'),
                    self::date('Reported on'),
                    self::date('Fixed on'),
                    self::date('Called off on'),
                ],
                [
                    self::when('Note the report', Rules::EVENT_CREATE, '', Rules::OP_ALWAYS, '', [
                        self::stamp('Reported on'),
                        self::logLine('Reported ITEM_TITLE.'),
                        self::setField('Next step', 'Assign someone.'),
                    ]),
                    self::when('Start the repair', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Under repair', [
                        self::email('Under repair: ITEM_TITLE', 'ITEM_TITLE is under repair.'),
                        self::logLine('ITEM_TITLE is under repair.'),
                        self::setField('Next step', 'Fix it.'),
                    ]),
                    self::when('Wait on a part', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Waiting on a part', [
                        self::email('Waiting on a part: ITEM_TITLE', 'ITEM_TITLE is waiting on a part.'),
                        self::logLine('ITEM_TITLE is waiting on a part.'),
                        self::setField('Next step', 'Waiting on the part.'),
                    ]),
                    self::when('Note a photo on a new report', Rules::EVENT_ATTACHMENT, 'Status', Rules::OP_EQ, 'Reported', [
                        self::logLine('A photo was added to ITEM_TITLE.'),
                    ]),
                    self::when('Fix the repair', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Fixed', [
                        self::stamp('Fixed on'),
                        self::logLine('Fixed ITEM_TITLE.'),
                        self::notice('Fixed: ITEM_TITLE', 'ITEM_TITLE has been fixed.'),
                        self::email('Fixed: ITEM_TITLE', 'ITEM_TITLE has been fixed.'),
                        self::setField('Next step', ''),
                    ]),
                    self::when('Call off the repair', Rules::EVENT_UPDATE, 'Status', Rules::OP_BECOMES, 'Called off', [
                        self::stamp('Called off on'),
                        self::logLine('Called off ITEM_TITLE.'),
                        self::setField('Next step', ''),
                    ]),
                ],
                [
                    ScreenCatalog::WORK,
                    ScreenCatalog::CREATE,
                    ScreenCatalog::DIARY,
                    ScreenCatalog::PARTIES,
                    ScreenCatalog::FILES,
                    ScreenCatalog::ANNOUNCEMENTS,
                    ScreenCatalog::SEARCH_ADVANCED,
                    ScreenCatalog::SEARCH_LIST,
                ],
                self::search('Under repair', 'Repairs under repair.', 'Under repair'),
                ['Fixed', 'Called off'],
                self::repairsGuide()
            ),
            ApplicationBuilder::BLUEPRINT_BLANK => self::shape(
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
     * @return array<string, array{blueprint: string, sort: int, cards: list<array{title: string, fields: array<string, string>}>, notice?: array{subject: string, message: string}}>
     */
    public static function showcase(): array
    {
        return [
            'publishing' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_PUBLISHING,
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
            'repairs' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_REPAIRS,
                'sort' => 8,
                'cards' => [
                    ['title' => 'Store door', 'fields' => ['Status' => 'Reported', 'Where' => 'Store', 'What is wrong' => 'The door sticks.', 'Priority' => 'When you can', 'Reported by' => 'Nguyen', 'Next step' => 'Assign someone.', 'Reported on' => '06-11-2026']],
                    ['title' => 'Kitchen light', 'fields' => ['Status' => 'Under repair', 'Where' => 'Kitchen', 'What is wrong' => 'The light over the bench is out.', 'Priority' => 'Today', 'Reported by' => 'Reid', 'Next step' => 'Fix it.', 'Reported on' => '04-11-2026']],
                    ['title' => 'West stair rail', 'fields' => ['Status' => 'Waiting on a part', 'Where' => 'West stair', 'What is wrong' => 'The rail is loose.', 'Priority' => 'Now', 'Reported by' => 'Reid', 'Next step' => 'Waiting on the part.', 'Part due' => '14-11-2026', 'Reported on' => '01-11-2026']],
                    ['title' => 'Civic tap', 'fields' => ['Status' => 'Fixed', 'Where' => 'Civic', 'What is wrong' => 'The tap was dripping.', 'Priority' => 'Today', 'Reported by' => 'Walsh', 'Cost' => '45', 'Reported on' => '20-10-2026', 'Fixed on' => '28-10-2026']],
                ],
                'notice' => [
                    'subject' => 'Civic tap',
                    'message' => 'Civic tap has been fixed.',
                ],
            ],
            'matters' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_MATTERS,
                'sort' => 10,
                'cards' => [
                    ['title' => 'Reid lease', 'fields' => ['Status' => 'Waiting on us', 'Client' => 'Reid', 'Kind' => 'Contract', 'Reference' => 'R-1044', 'Next step' => 'Take the next action.', 'Note' => 'The break clause is to come out.', 'Opened on' => '02-09-2026']],
                    ['title' => 'Reid advice', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Reid', 'Kind' => 'Advice', 'Reference' => 'R-1102', 'Next step' => 'Waiting on their reply.', 'Note' => 'Asked for the lease note.', 'Reply due' => '12-11-2026', 'Opened on' => '20-09-2026']],
                    ['title' => 'Nguyen dispute', 'fields' => ['Status' => 'Waiting on them', 'Client' => 'Nguyen', 'Kind' => 'Dispute', 'Reference' => 'N-220', 'Next step' => 'Waiting on their reply.', 'Reply due' => '06-11-2026', 'Opened on' => '14-08-2026']],
                    ['title' => 'Walsh licence', 'fields' => ['Status' => 'Done', 'Client' => 'Walsh', 'Kind' => 'Property', 'Reference' => 'W-7', 'Note' => 'The file is complete.', 'Opened on' => '01-06-2026', 'Closed on' => '02-10-2026']],
                ],
            ],
            'conveyancing' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_CONVEYANCING,
                'sort' => 20,
                'cards' => [
                    ['title' => 'Barton Street sale', 'fields' => ['Status' => 'Enquiries', 'Client' => 'Reid', 'Place' => '14 Barton Street', 'Side' => 'Sale', 'Reference' => 'C-441', 'Next step' => 'Reply on the enquiries.', 'Note' => 'The boundary is the open point.', 'Reply due' => '14-11-2026', 'Instructed on' => '04-08-2026']],
                    ['title' => 'Reid purchase', 'fields' => ['Status' => 'Searches', 'Client' => 'Reid', 'Place' => 'Kingston', 'Side' => 'Purchase', 'Reference' => 'C-460', 'Next step' => 'Follow up the searches.', 'Instructed on' => '01-10-2026']],
                    ['title' => 'Campbell unit', 'fields' => ['Status' => 'Instructed', 'Client' => 'Campbell', 'Place' => '8 Civic', 'Side' => 'Purchase', 'Reference' => 'C-450', 'Note' => 'Instructions are still to come.', 'Instructed on' => '20-10-2026']],
                    ['title' => 'Walsh leasehold', 'fields' => ['Status' => 'Complete', 'Client' => 'Walsh', 'Place' => '2 Braddon', 'Side' => 'Sale', 'Reference' => 'C-438', 'Note' => 'Exchange and completion are done.', 'Instructed on' => '02-06-2026', 'Completed on' => '18-09-2026']],
                ],
            ],
            'claims' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_CLAIMS,
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
            'leave' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_LEAVE,
                'sort' => 40,
                'cards' => [
                    ['title' => 'Reid, 12 November', 'fields' => ['Status' => 'Asked', 'Person' => 'Reid', 'First day' => '12-11-2026', 'Last day' => '12-11-2026', 'Next step' => 'Decide the request.', 'Note' => 'A Thursday.', 'Asked on' => '19-10-2026']],
                    ['title' => 'Reid, 4 December', 'fields' => ['Status' => 'Asked', 'Person' => 'Reid', 'First day' => '04-12-2026', 'Last day' => '04-12-2026', 'Next step' => 'Decide the request.', 'Asked on' => '15-10-2026']],
                    ['title' => 'Nguyen, 20 November', 'fields' => ['Status' => 'Asked', 'Person' => 'Nguyen', 'First day' => '20-11-2026', 'Last day' => '20-11-2026', 'Next step' => 'Decide the request.', 'Asked on' => '16-10-2026']],
                    ['title' => 'Walsh, 6 November', 'fields' => ['Status' => 'Approved', 'Person' => 'Walsh', 'First day' => '06-11-2026', 'Last day' => '06-11-2026', 'Note' => 'The day is covered.', 'Asked on' => '01-10-2026', 'Approved on' => '17-10-2026']],
                ],
                'notice' => [
                    'subject' => 'Walsh, 6 November',
                    'message' => 'Walsh, 6 November has been approved.',
                ],
            ],
            'complaints' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_COMPLAINTS,
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
                'blueprint' => ApplicationBuilder::BLUEPRINT_COMMITTEE,
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
                'blueprint' => ApplicationBuilder::BLUEPRINT_AUDIT,
                'sort' => 70,
                'cards' => [
                    ['title' => 'Cash handling', 'fields' => ['Status' => 'Accepted', 'Place' => 'Front office', 'Rating' => 'High', 'Next step' => 'Start the work.', 'Due' => '30-10-2026', 'Raised on' => '01-10-2026']],
                    ['title' => 'Petty cash', 'fields' => ['Status' => 'In progress', 'Place' => 'Front office', 'Rating' => 'Medium', 'Next step' => 'Finish the work.', 'Due' => '14-11-2026', 'Raised on' => '15-09-2026']],
                    ['title' => 'Supplier list', 'fields' => ['Status' => 'Raised', 'Place' => 'Accounts', 'Rating' => 'Low', 'Note' => 'The last twelve months are still to be pulled.', 'Due' => '28-11-2026', 'Raised on' => '20-10-2026']],
                    ['title' => 'Fire doors', 'fields' => ['Status' => 'Closed', 'Place' => 'West stair', 'Rating' => 'High', 'Note' => 'The check is on the file.', 'Raised on' => '01-08-2026', 'Closed on' => '19-09-2026']],
                ],
            ],
            'incidents' => [
                'blueprint' => ApplicationBuilder::BLUEPRINT_INCIDENTS,
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
                'blueprint' => ApplicationBuilder::BLUEPRINT_CANDIDATES,
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
                'blueprint' => ApplicationBuilder::BLUEPRINT_CONTRACTS,
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


    private static function leaveGuide(): string
    {
        return <<<'TEXT'
Each record is one request. The name of the record is the person and the day. The person is named on the card, and the person who decides the request is the owner.

Move Status as the request moves. Asked means it has been made and is still to be decided. Approved means the leave is agreed. Declined means it is not.

Set First day and Last day to the days away. The application stamps Asked on when the request is created, writes the log, and sets Next step to Decide the request. Approving it stamps Approved on, writes the log, posts a notice, sends an email, and clears Next step. Parties lists every request under the person's name. The diary shows a first day that is due.

A notice is posted when a request is approved. A decline is written in the log and stays off the wall.
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


    private static function repairsGuide(): string
    {
        return <<<'TEXT'
Each record is one repair. The name of the record is the thing to fix. The person who reported it is named on the card, and the person doing the repair is the owner.

Move Status as the repair moves. Reported means it has come in. Under repair means someone is fixing it. Waiting on a part means the work has stopped until a part arrives. Fixed means it is done. Called off means it will not be done.

Set Part due when you are waiting on a part, then order the part yourself. The application stamps Reported on when the repair is created and writes the log. It emails you when the repair goes under repair, when it is waiting on a part, and when it is fixed. Add a photo while it is reported. The photo is written in the log. Parties lists every repair under the name of the person who reported it. The diary shows a part that is overdue.

A notice is posted when a repair is fixed. A repair that is called off is written in the log and stays off the wall.
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
    private static function menu(string $name, array $values, array $waits = []): array
    {
        $field = ['name' => $name, 'type' => FieldTypes::MENU, 'values' => $values];
        if ($waits !== []) {
            $field['waits'] = array_values($waits);
        }

        return $field;
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
}
