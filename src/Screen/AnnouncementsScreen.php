<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationAccess;
use Adlexone\support\Database;
use Adlexone\Http\Page;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class AnnouncementsScreen
{
    /**
     * Announcements for the application that is open.
     */
    public static function open(): void
    {
        $slug = ApplicationAccess::slug();

        switch ((string) ($_GET['option'] ?? 'show_announcements')) {
            case 'show_announcement_item':
                ApplicationAccess::requireUse();
                self::showOne((string) ($_GET['id'] ?? ''), $slug);
                break;
            case 'new_announcement':
                if (!ApplicationAccess::canAnnounce()) {
                    \Adlexone\Auth\Access::deny();
                }
                self::showCreate();
                break;
            case 'add_announcement':
                if (!ApplicationAccess::canAnnounce()) {
                    \Adlexone\Auth\Access::deny();
                }
                self::create($slug);
                break;
            case 'edit_announcement':
                if (!ApplicationAccess::canAnnounce()) {
                    \Adlexone\Auth\Access::deny();
                }
                self::showEdit((string) ($_GET['id'] ?? ''), $slug);
                break;
            case 'update_announcement':
                if (!ApplicationAccess::canAnnounce()) {
                    \Adlexone\Auth\Access::deny();
                }
                self::update($slug);
                break;
            case 'delete_announcement':
                if (!ApplicationAccess::canAnnounce()) {
                    \Adlexone\Auth\Access::deny();
                }
                self::delete((string) ($_GET['id'] ?? ''), $slug);
                break;
            case 'show_announcements':
            default:
                ApplicationAccess::requireUse();
                self::showList($slug);
                break;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id, string $slug): ?array
    {
        if ($slug !== '' && Database::columnExists('announcements', 'application')) {
            return Database::first('announcements', '*', 'id = ? AND application = ?', [$id, $slug]);
        }
        return Database::first('announcements', '*', 'id = ?', [$id]);
    }

    public static function showOne(string $id, string $slug): void
    {
        $row = self::find((int) $id, $slug);
        if ($row === null) {
            View::respond('Announcement not found.', Blocks::link(MAN_BASE_URL . '&option=show_announcements', 'Announcements', 'URL'));
            return;
        }

        $heading = date(SET_DATE_FORMAT, (int) $row['time']) . ': ' . (string) $row['subject'];
        Page::current()->setBody(Blocks::vertical([
            [
                'title' => $heading,
                'html' => Form::fields([
                    '' => Form::textarea('', (string) $row['message'], '', true),
                ]),
            ],
        ]));
    }

    public static function showCreate(): void
    {
        Page::current()->setBody(Form::form(
            'New announcement',
            MAN_BASE_URL . '&option=add_announcement',
            [
                'Subject' => Form::text('subject', ''),
                'Message' => Form::textarea('message', ''),
            ],
            [
                Form::button('submit', 'submit_button', 'Save'),
                Form::button('reset', 'reset', 'Reset'),
            ]
        ));
    }

    public static function create(string $slug): void
    {
        $data = [
            'time' => time(),
            'message' => (string) ($_POST['message'] ?? ''),
            'subject' => (string) ($_POST['subject'] ?? ''),
            'type' => 'user',
        ];
        if (Database::columnExists('announcements', 'application')) {
            $data['application'] = $slug;
        }
        Database::insert('announcements', $data);
        self::showList($slug);
    }

    public static function showList(string $slug): void
    {
        if ($slug !== '' && Database::columnExists('announcements', 'application')) {
            $result = Database::select('announcements', ['id', 'subject', 'message', 'time'], 'application = ?', [$slug], 'time DESC');
        } else {
            $result = Database::select('announcements', ['id', 'subject', 'message', 'time'], '', [], 'time DESC');
        }

        $canManage = ApplicationAccess::canAnnounce();
        $html = '';
        $index = 0;
        $art = \Adlexone\Theme\Theme::illustrationHref('notice');
        foreach ($result as $row) {
            $lead = $index === 0;
            $actions = '';
            if ($canManage) {
                $actions = '<div class="notice__actions">'
                    . ($lead ? Blocks::link(MAN_BASE_URL . '&option=new_announcement', 'Post a notice', '', 'btn btn--primary') : '')
                    . Blocks::link(MAN_BASE_URL . '&option=edit_announcement&id=' . $row['id'], 'Edit', '', 'btn btn--sm btn--quiet')
                    . Blocks::link(MAN_BASE_URL . '&option=delete_announcement&id=' . $row['id'], 'Delete', '', 'btn btn--sm btn--danger', '', '_parent', 'Delete this announcement?')
                    . '</div>';
            }
            $picture = $lead && $art !== ''
                ? '<img class="notice__art" src="' . Html::e($art) . '" alt="" width="160" height="120">'
                : '';
            $kicker = $lead ? 'On the wall' : '';
            $html .= '<article class="notice' . ($lead ? ' notice--lead' : '') . '">'
                . $picture
                . '<div class="notice__copy">'
                . ($kicker !== '' ? '<p class="notice__kicker">' . $kicker . '</p>' : '')
                . '<h2 class="notice__subject">' . Html::e((string) $row['subject']) . '</h2>'
                . '<div class="notice__body">' . $row['message'] . '</div>'
                . '<div class="notice__meta"><time datetime="' . date('c', (int) $row['time']) . '">' . Html::e(date(SET_DATE_FORMAT, (int) $row['time'])) . '</time>' . $actions . '</div>'
                . '</div></article>';
            $index++;
        }
        if ($html === '') {
            $html = Blocks::emptyState([
                'illustration' => 'notice',
                'title' => 'Nothing on the wall',
                'copy' => $canManage
                    ? 'Post a notice for the people who use this.'
                    : 'When a notice is posted, it will show up here.',
                'action' => $canManage
                    ? Blocks::link(MAN_BASE_URL . '&option=new_announcement', 'Post a notice', '', 'btn btn--primary')
                    : '',
            ]);
        } else {
            $html = '<div class="notice-wall">' . $html . '</div>';
        }

        Page::current()->setBody(Blocks::vertical([
            ['title' => 'Announcements', 'html' => $html],
        ]));
    }

    public static function showEdit(string $id, string $slug): void
    {
        $row = self::find((int) $id, $slug);
        if ($row === null) {
            View::respond('Announcement not found.', Blocks::link(MAN_BASE_URL . '&option=show_announcements', 'Announcements', 'URL'));
            return;
        }
        Page::current()->setBody(Form::form(
            'Edit announcement',
            MAN_BASE_URL . '&option=update_announcement',
            [
                'Subject' => Form::text('subject', (string) $row['subject']),
                'Message' => Form::textarea('message', (string) $row['message'], SET_FORM_FIELD_HEIGHT),
                '' => Form::hidden('id', (string) (int) $id),
            ],
            [
                Form::button('submit', 'submit_button', 'Save'),
                Form::button('reset', 'reset', 'Reset'),
            ]
        ));
    }

    public static function update(string $slug): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (self::find($id, $slug) === null) {
            View::respond('Announcement not found.', Blocks::link(MAN_BASE_URL . '&option=show_announcements', 'Announcements', 'URL'));
            return;
        }
        Database::update('announcements', [
            'message' => (string) ($_POST['message'] ?? ''),
            'subject' => (string) ($_POST['subject'] ?? ''),
        ], 'id = ?', [$id]);
        self::showList($slug);
    }

    public static function delete(string $id, string $slug): void
    {
        $row = self::find((int) $id, $slug);
        if ($row !== null) {
            Database::delete('announcements', 'id = ?', [(int) $id]);
        }
        self::showList($slug);
    }
}
