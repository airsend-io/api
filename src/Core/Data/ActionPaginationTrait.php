<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\Action;

trait ActionPaginationTrait
{

    abstract protected function normalizeUsersForActions(array $records): array;

    protected function paginationOrderSql($sortBy, $sortDesc): string
    {
        $forward = $sortDesc ? 'DESC' : 'ASC';
        $backward = $sortDesc ? 'ASC' : 'DESC';
        switch ($sortBy) {
            case 'default':
                return "ORDER BY a.order_position $backward, a.id $forward";
            case 'name':
                return "ORDER BY a.action_name $forward, a.id $forward";
            case 'channel':
                return "ORDER BY c.channel_name $forward, a.order_position $backward, a.id $forward";
            case 'due_date':
                return "ORDER BY ISNULL(a.due_on) ASC, due_order_$forward $forward, a.action_name $forward, a.id $forward";
        }
    }

    protected function paginationCursorCondition(string $sortBy, bool $sortDesc, Action $cursor): array
    {
        $cursorCondition = 'AND ';
        $bindings = [];
        switch ($sortBy) {
            case 'default':
                $cursorCondition .= $sortDesc ? 'a.order_position >= :order_position' : 'a.order_position <= :order_position';
                $bindings = ['order_position' => $cursor->getOrderPosition()];
                break;
            case 'name':
                $cursorCondition .= $sortDesc ? "a.action_name <= :action_name" : "a.action_name >= :action_name";
                $bindings = ['action_name' => $cursor->getName()];
                break;
            case 'channel':
                $cursorCondition .= $sortDesc
                    ? "c.channel_name <= :channel_name"
                    : "c.channel_name >= :channel_name";
                $cursorChannelName = $this->dbs->selectOne('SELECT channel_name FROM channels WHERE id = :id', ['id' => $cursor->getChannelId()]);
                $cursorChannelName = $cursorChannelName['channel_name'];
                $bindings = [
                    'channel_name' => $cursorChannelName,
                ];
                break;
            case 'due_date':
                $cursorCondition .= ' true';
                break;
        }
        return [$cursorCondition, $bindings];
    }

    /**
     * @param string $sql
     * @param array $bindings
     * @param int $limit
     * @param int|null $cursorId
     * @return array
     */
    protected function findPageBySql(string $sql, array $bindings, int $limit, ?int $cursorId = null): array
    {

        // initialize the offset as 0
        $offset = 0;

        // default chunk size is 50 (we always bring 50 records from the database per query)
        $chunkSize = 50;

        // initialize the cursor passed flag. If there is no cursor defined, it's initialized as true, so we get the
        // $limit first registries
        $cursorPassed = $cursorId === null;

        // initialize the $output
        $output = [];

        // initialize the SQL query
        $sql = "$sql LIMIT :limit OFFSET :offset";

        // To ensure that we find the correct page, we query the database
        // by chunks/pages of 50 results, and then go through the results.
        // First we try to find the cursor row (when cursor is defined). If the cursor is not defined, we just start
        // to grab the records.
        // Once found the cursor, we skip it, and keep going until
        // find the $limit number of rows, or the result set is null (no more rows to check) whatever happens first.
        do {

            $rows = $this->dbs->select($sql, array_merge($bindings, ['limit' => $chunkSize, 'offset' => $offset]));
            $rows = $this->normalizeUsersForActions($rows);

            foreach ($rows as $row) {

                $currentId = (int)$row['id'];

                // we're on the cursor? Set cursorPassed, and continue
                if (!$cursorPassed && $currentId === $cursorId) {
                    $cursorPassed = true;
                    continue;
                }

                // have we passed the cursor?
                if ($cursorPassed) {
                    // if yes, include the row on the output, until we reach the limit
                    $output[] = $row;
                    if (count($output) >= $limit) {
                        break;
                    }
                }

            }

            $offset+=$chunkSize;

        } while (!empty($rows) && count($output) < $limit);

        return $output;
    }

    protected function findPageByCursor(Action $cursor,
                                        string $sortBy,
                                        bool $sortDesc,
                                        string $baseSql,
                                        string $groupSql,
                                        array $bindings,
                                        int $limit): array
    {

        // to find records based on a cursor, defined by a limit (before or after)
        // we'll add an condition to the query, based on the sorting option.
        // This doesn't ensure that the first row of the query will be the cursor
        // So we call the findPage method to take care of this.

        [$cursorCondition, $cursorConditionBindings] = $this->paginationCursorCondition($sortBy, $sortDesc, $cursor);
        $bindings = array_merge($bindings, $cursorConditionBindings);

        $orderSql = $this->paginationOrderSql($sortBy, $sortDesc);
        $sql = "$baseSql $cursorCondition $groupSql $orderSql";

        return $this->findPageBySql($sql, $bindings, $limit, $cursor->getId());

    }
}