<?php
/**
 * Pagination controls for list pages.
 * Usage:
 *   require_once __DIR__ . '/../../includes/pagination.php';
 *   echo render_pagination($totalRows, $page, $pageSize, $baseUrl);
 *
 * @param int $totalRows     Total number of list entries (not paged)
 * @param int $page          Current page number (starts from 1)
 * @param int $pageSize      Rows per page (10/25/50/100/250)
 * @param string $baseUrl    URL prefix, e.g. 'list.php?' or '?search=abc&' (must include trailing & if params)
 * @return string            HTML for pagination controls
 */
function render_pagination($totalRows, $page, $pageSize, $baseUrl) {
    $totalPages = max(1, ceil($totalRows / $pageSize));
    if ($totalPages < 2) return ''; // No pagination needed

    // Show window of page numbers: e.g., 1 ... 4 5 [6] 7 8 ... 20
    $window = 2; // pages to show left/right of current
    $html = '<nav class="pagination-wrap"><ul class="pagination">';

    // Prev
    if ($page > 1) {
        $html .= '<li><a href="'.$baseUrl.'page='.($page-1).'&pagesize='.$pageSize.'">&laquo; Prev</a></li>';
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        if (
            $i == 1 ||
            $i == $totalPages ||
            ($i >= $page - $window && $i <= $page + $window)
        ) {
            $active = ($i == $page) ? ' class="active"' : '';
            $html .= '<li'.$active.'><a href="'.$baseUrl.'page='.$i.'&pagesize='.$pageSize.'">'.$i.'</a></li>';
        } elseif (
            $i == $page - $window - 1 ||
            $i == $page + $window + 1
        ) {
            $html .= '<li class="ellipsis">...</li>';
        }
    }

    // Next
    if ($page < $totalPages) {
        $html .= '<li><a href="'.$baseUrl.'page='.($page+1).'&pagesize='.$pageSize.'">Next &raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}
?>