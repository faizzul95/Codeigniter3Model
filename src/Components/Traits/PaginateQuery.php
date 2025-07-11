<?php

namespace OnlyPHP\Codeigniter3Model\Components\Traits;

trait PaginateQuery
{
    protected $_paginateColumn = [];
    protected $_paginateSearchValue = '';

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
        $page = $page ?: ($this->input->get('page') ? $this->input->get('page') : 1);
        $offset = ($page - 1) * $perPage;

        $this->_paginateSearchValue = !empty($searchValue) ? trim($searchValue) : '';
        $columns = $this->_database->list_fields($this->table);

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
        $this->_paginateSearchValue = !empty($dataPost['search']['value']) ? trim($dataPost['search']['value']) : '';
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
        $this->limit($dataPost['length'])->offset($dataPost['start']);

        // Apply Order data if exists
        $orderBy = $dataPost['order'];
        if (!empty($orderBy)) {
            $this->orderBy($columns[$orderBy[0]['column']], $orderBy[0]['dir']);
        }

        return [
            'draw' => $dataPost['draw'],
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $total,
            'data' => $this->get(),
        ];
    }

    public function paginate_select_input($perPage = 10, $page = null, $searchValue = '', $customFilter = null)
    {
        $offset = ($page - 1) * $perPage;

        $this->_paginateSearchValue = !empty($searchValue) ? trim($searchValue) : '';
        $columns = $this->_database->list_fields($this->table);

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

        // $matchType : 1-Match Exactly (default), 2-Match Beginning, 3-Match Anywhere 
        if ($this->_isMultidimensional($condition)) {
            $matchType = $condition['filter_type'] ?? 1; // Default to exact match
            $condition = $condition['filter'] ?? [];
        } else {
            $matchType = 1; // Default to exact match
        }

        $filterData = array_reduce(array_keys($condition), function ($carry, $key) use ($condition) {
            if (!empty($condition[$key]) || $condition[$key] == 0) {
                $carry[$key] = $condition[$key];
            }
            return $carry;
        }, []);

        if (!empty($filterData)) {
            $this->_database->group_start();

            foreach ($condition as $column => $value) {
                if (!empty($value) || $value == 0) {
                    switch ($matchType) {
                        case 2:
                            $this->_database->like($column, $value, 'after'); // `column` LIKE 'value%' ESCAPE '!'
                            break;
                        case 3:
                            $this->_database->like($column, $value); // `column` LIKE '%value%' ESCAPE '!'
                            break;
                        default:
                            $this->_database->where($column, $value);
                    }
                }
            }

            $this->_database->group_end();
        }
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