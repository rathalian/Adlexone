<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Database\RecordListQuery;
use Adlexone\Html\Form;
use Adlexone\Database\Database;

/**
 * One query and one page control for a list of records.
 * The page that calls this still chooses its columns, sort links, and row actions.
 */
final class RecordList
{
    /**
     * @param list<int> $ids
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    public static function fetch(array $ids, array $columns, string $order = '', ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = RecordListQuery::idWhere($ids);

        return Database::select('items', $columns, $where, $params, $order, $limit, $offset);
    }

    /**
     * The numbered page control shared by record lists.
     *
     * @return array{html: string, page: mixed}
     */
    public static function pager(int $count, int $pageSize, mixed $postedPage): array
    {
        $pageSize = max(1, $pageSize);
        if (($count / $pageSize) <= 1) {
            return ['html' => '', 'page' => ''];
        }

        $html = '<div class="pagination pagination-centered "><ul>';
        $page = $postedPage;
        if ($page > 1) {
            $html .= Access::showUse('<li>' . Form::pageButton('prev', 'Prev') . '</li>');
        }
        $start = max(1, ($page - 3));
        $end = max(10, $page + 3);
        $end = ($end > (int) (($count / $pageSize) + 1)) ? (int) ($count / $pageSize) + 1 : $end;
        if ($start > 1 && $end - $start < 10) {
            $start = max($end - 10, 1);
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                $html .= '<li class="active"><a href ="#">' . $i . '</a></li>';
            } else {
                $html .= Access::showUse('<li>' . Form::pageButton((string) $i, (string) $i) . '</li>');
            }
        }
        if ($page != $end) {
            $html .= Access::showUse('<li>' . Form::pageButton('next', 'Next') . '</li>');
        }

        return ['html' => $html, 'page' => $page];
    }
}
