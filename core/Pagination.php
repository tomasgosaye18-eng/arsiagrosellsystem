<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : core/Pagination.php
 * Purpose  : Pagination utility.
 *            Generates HTML pagination controls.
 *            Outputs Bootstrap 5 pagination markup.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Core;

class Pagination
{
    private int    $total;
    private int    $perPage;
    private int    $currentPage;
    private int    $totalPages;
    private string $baseUrl;
    private int    $pageRange;

    /**
     * @param int    $total       Total number of records
     * @param int    $perPage     Records per page
     * @param int    $currentPage Current active page (1-based)
     * @param string $baseUrl     URL without page parameter
     * @param int    $pageRange   Number of page buttons around current
     */
    public function __construct(
        int    $total,
        int    $perPage,
        int    $currentPage,
        string $baseUrl,
        int    $pageRange = 2
    ) {
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->totalPages  = (int) ceil($this->total / $this->perPage);
        $this->baseUrl     = $baseUrl;
        $this->pageRange   = $pageRange;
    }

    /**
     * Get pagination metadata.
     *
     * @return array{total: int, perPage: int, currentPage: int, totalPages: int, from: int, to: int}
     */
    public function meta(): array
    {
        $from = $this->total > 0 ? (($this->currentPage - 1) * $this->perPage) + 1 : 0;
        $to   = min($this->currentPage * $this->perPage, $this->total);

        return [
            'total'       => $this->total,
            'perPage'     => $this->perPage,
            'currentPage' => $this->currentPage,
            'totalPages'  => $this->totalPages,
            'from'        => $from,
            'to'          => $to,
        ];
    }

    /**
     * Generate Bootstrap 5 pagination HTML.
     * Returns empty string if only one page.
     */
    public function render(): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }

        $meta = $this->meta();
        $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center flex-wrap">';

        // ── Previous Button ──────────────────────────────────────────────────
        if ($this->currentPage > 1) {
            $prevUrl = $this->pageUrl($this->currentPage - 1);
            $html .= '<li class="page-item"><a class="page-link" href="' . e($prevUrl) . '" aria-label="Previous">'
                   . '<span aria-hidden="true">&laquo;</span></a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }

        // ── First Page + Ellipsis ─────────────────────────────────────────────
        $start = max(1, $this->currentPage - $this->pageRange);
        $end   = min($this->totalPages, $this->currentPage + $this->pageRange);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($this->pageUrl(1)) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
        }

        // ── Page Number Buttons ───────────────────────────────────────────────
        for ($page = $start; $page <= $end; $page++) {
            $active  = $page === $this->currentPage;
            $classes = 'page-item' . ($active ? ' active' : '');
            $aria    = $active ? ' aria-current="page"' : '';
            $url     = e($this->pageUrl($page));
            $html   .= "<li class=\"{$classes}\"{$aria}>";
            $html   .= $active
                ? "<span class=\"page-link\">{$page}</span>"
                : "<a class=\"page-link\" href=\"{$url}\">{$page}</a>";
            $html   .= '</li>';
        }

        // ── Last Page + Ellipsis ──────────────────────────────────────────────
        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            $lastUrl = e($this->pageUrl($this->totalPages));
            $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$lastUrl}\">{$this->totalPages}</a></li>";
        }

        // ── Next Button ───────────────────────────────────────────────────────
        if ($this->currentPage < $this->totalPages) {
            $nextUrl = $this->pageUrl($this->currentPage + 1);
            $html .= '<li class="page-item"><a class="page-link" href="' . e($nextUrl) . '" aria-label="Next">'
                   . '<span aria-hidden="true">&raquo;</span></a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }

        $html .= '</ul></nav>';

        // Info text
        $html .= '<p class="text-center text-muted small mt-1">'
               . 'Showing ' . e($meta['from']) . '–' . e($meta['to'])
               . ' of ' . e($meta['total']) . ' records'
               . '</p>';

        return $html;
    }

    /**
     * Build a page URL by adding/replacing the page query parameter.
     */
    private function pageUrl(int $page): string
    {
        $url   = $this->baseUrl;
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($query, $params);
        $params['page'] = $page;

        $base = strtok($url, '?');
        return $base . '?' . http_build_query($params);
    }
}
