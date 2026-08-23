<?php

namespace OnlyPHP\Codeigniter3Model\Components\Traits;

trait PaginateQuery
{
    protected $_paginateColumn = [];
    protected $_paginateSearchValue = '';

    /**
     * @var int Upper bound on rows a single paginated request may ask for.
     *          Override per model if a screen genuinely needs more.
     */
    public $paginateMaxLength = 1000;

    # PAGINATION SECTION

    public function setPaginateFilterColumn($column = [])
    {
        $this->_paginateColumn = is_array($column) ? $column : [];
        return $this;
    }

    /**
     * Paginate the query results
     *
     * @param int $perPage Items per page
     * @param int|null $page Current page
     * @param string|null $searchValue The search value for the specific column
     * @param array|null $customFilter The advanced filter search
     * @return array Paginated results
     */
    public function paginate($perPage = 10, $page = null, $searchValue = '', $customFilter = null)
    {
        $page = (int) ($page ?: ($this->input->get('page') ?: 1));
        $page = $page < 1 ? 1 : $page;

        $perPage = (int) $perPage;
        if ($perPage < 1 || $perPage > $this->paginateMaxLength) {
            $perPage = $this->paginateMaxLength;
        }

        $offset = ($page - 1) * $perPage;

        $this->_paginateSearchValue = !empty($searchValue) ? trim((string) $searchValue) : '';
        $columns = empty($this->_paginateColumn) ? $this->_database->list_fields($this->table) : $this->_paginateColumn;

        $this->_withTrashQueryFilter();
        $this->_applyAggregates();

        // Count total rows before filter
        $totalRecords = (int) (clone $this->_database)->count_all_results($this->getTableWithIndex());

        // Apply custom filter (advanced search)
        if (!empty($customFilter) && is_array($customFilter)) {
            $this->_paginateFilterCondition($customFilter);
        }

        // Apply search filter
        $this->_paginateSearchFilter($columns);

        // Count total rows after filter
        $total = (int) (clone $this->_database)->count_all_results($this->getTableWithIndex());

        // Fetch only the required page of results
        $this->limit($perPage)->offset($offset);
        $data = $this->get();

        // Calculate pagination details
        $totalPages = (int) ceil($total / $perPage);
        $nextPage = ($page < $totalPages) ? $page + 1 : null;
        $previousPage = ($page > 1) ? $page - 1 : null;

        // Configure pagination
        $this->load->library('pagination');
        $config = [
            'base_url' => current_url(),
            'total_rows' => $total,
            'per_page' => $perPage,
            'use_page_numbers' => TRUE,
            'page_query_string' => TRUE,
            'query_string_segment' => 'page',
            'full_tag_open' => '<ul class="pagination">',
            'full_tag_close' => '</ul>',
            'first_link' => '&laquo;',
            'first_tag_open' => '<li class="page-item">',
            'first_tag_close' => '</li>',
            'last_link' => '&raquo;',
            'last_tag_open' => '<li class="page-item">',
            'last_tag_close' => '</li>',
            'next_link' => '&gt;',
            'next_tag_open' => '<li class="page-item">',
            'next_tag_close' => '</li>',
            'prev_link' => '&lt;',
            'prev_tag_open' => '<li class="page-item">',
            'prev_tag_close' => '</li>',
            'cur_tag_open' => '<li class="page-item active"><a class="page-link">',
            'cur_tag_close' => '</a></li>',
            'num_tag_open' => '<li class="page-item">',
            'num_tag_close' => '</li>',
            'attributes' => ['class' => 'page-link'],
        ];

        $this->pagination->initialize($config);

        return [
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $total,
            'data' => $data,
            'current_page' => $page,
            'next_page' => $nextPage,
            'previous_page' => $previousPage,
            'last_page' => $totalPages,
            'error' => $page > $totalPages ? "Current page ({$page}) is more than total pages ({$totalPages})" : '',
            'links' => $this->pagination->create_links()
        ];
    }

    public function paginate_ajax($dataPost, $customFilter = null)
    {
        // The request body is client-controlled, so validate and clamp every field
        // before use - `length` in particular is unbounded on the wire.
        if (!is_array($dataPost)) {
            throw new \InvalidArgumentException('paginate_ajax() expects the DataTables request array.');
        }

        $columns = empty($this->_paginateColumn) ? $this->_database->list_fields($this->table) : $this->_paginateColumn;

        $draw = isset($dataPost['draw']) ? (int) $dataPost['draw'] : 0;
        $start = isset($dataPost['start']) ? max(0, (int) $dataPost['start']) : 0;

        $length = isset($dataPost['length']) ? (int) $dataPost['length'] : $this->paginateMaxLength;
        // -1 is DataTables' "all". Treat it, 0, and anything oversized as the cap.
        if ($length < 1 || $length > $this->paginateMaxLength) {
            $length = $this->paginateMaxLength;
        }

        $this->_paginateSearchValue = !empty($dataPost['search']['value']) ? trim((string) $dataPost['search']['value']) : '';

        $this->_withTrashQueryFilter();
        $this->_applyAggregates();

        // Count total rows before filter
        $totalRecords = (int) (clone $this->_database)->count_all_results($this->getTableWithIndex());

        // Apply custom filter (advanced search)
        if (!empty($customFilter) && is_array($customFilter)) {
            $this->_paginateFilterCondition($customFilter);
        }

        // Apply search filter
        $this->_paginateSearchFilter($columns);

        // Count total rows after filter
        $total = (int) (clone $this->_database)->count_all_results($this->getTableWithIndex());

        // Order before limiting, and resolve the sort index against the column list
        // so an out-of-range value cannot reach orderBy().
        $orderBy = isset($dataPost['order']) && is_array($dataPost['order']) ? $dataPost['order'] : [];
        if (!empty($orderBy[0]) && isset($orderBy[0]['column'])) {
            $sortIndex = (int) $orderBy[0]['column'];

            if (array_key_exists($sortIndex, $columns)) {
                $direction = isset($orderBy[0]['dir']) && strtolower((string) $orderBy[0]['dir']) === 'desc' ? 'DESC' : 'ASC';
                $this->orderBy($columns[$sortIndex], $direction);
            }
        }

        $this->limit($length)->offset($start);

        return [
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $total,
            'data' => $this->get(),
        ];
    }

    public function paginate_select_input($perPage = 10, $page = null, $searchValue = '', $customFilter = null)
    {
        // $page defaults to null here, which would make the offset negative.
        $page = (int) ($page ?: 1);
        $page = $page < 1 ? 1 : $page;

        $perPage = (int) $perPage;
        if ($perPage < 1 || $perPage > $this->paginateMaxLength) {
            $perPage = $this->paginateMaxLength;
        }

        $offset = ($page - 1) * $perPage;

        $this->_paginateSearchValue = !empty($searchValue) ? trim((string) $searchValue) : '';
        $columns = empty($this->_paginateColumn) ? $this->_database->list_fields($this->table) : $this->_paginateColumn;

        $this->_withTrashQueryFilter();
        $this->_applyAggregates();

        // Apply custom filter (advanced search)
        if (!empty($customFilter) && is_array($customFilter)) {
            $this->_paginateFilterCondition($customFilter);
        }

        // Apply search filter
        $this->_paginateSearchFilter($columns);

        // Count total rows after filter
        $total = (int) (clone $this->_database)->count_all_results($this->getTableWithIndex());

        // Fetch only the required page of results
        $this->limit($perPage)->offset($offset);

        // Check if there are more results (for infinite scrolling)
        $hasMore = ($offset + $perPage) < $total;

        return [
            'results' => $this->toArray()->get(),
            'pagination' => ['more' => $hasMore]
        ];
    }

    private function _paginateFilterCondition($condition = null)
    {
        if (empty($condition)) {
            return;
        }

        // Accepts a flat [column => value] map, or ['filter_type' => 1|2|3,
        // 'filter' => [column => value]]. matchType: 1 exact, 2 prefix, 3 anywhere.
        if (is_array($condition) && array_key_exists('filter', $condition) && is_array($condition['filter'])) {
            $matchType = $condition['filter_type'] ?? 1;
            $condition = $condition['filter'];
        } else {
            $matchType = 1;
        }

        $filterData = [];
        foreach ($condition as $column => $value) {
            if (!empty($value) || $value === 0 || $value === '0') {
                $filterData[$column] = $value;
            }
        }

        if (empty($filterData)) {
            return;
        }

        $this->_database->group_start();

        foreach ($filterData as $column => $value) {
            switch ((int) $matchType) {
                case 2:
                    $this->_database->like($column, $value, 'after'); // `column` LIKE 'value%'
                    break;
                case 3:
                    $this->_database->like($column, $value); // `column` LIKE '%value%'
                    break;
                default:
                    $this->_database->where($column, $value);
            }
        }

        $this->_database->group_end();
    }

    private function _paginateSearchFilter($columns)
    {
        $searchValue = $this->_paginateSearchValue;

        if (empty($searchValue)) {
            return;
        }

        $i = 0;
        $this->_database->group_start();
        foreach ($columns as $column) {
            if (!empty($column)) {
                if ($i === 0) {
                    $this->_database->like($column, $searchValue);
                } else {
                    $this->_database->or_like($column, $searchValue);
                }
            }
            $i++;
        }
        $this->_database->group_end();
    }
}