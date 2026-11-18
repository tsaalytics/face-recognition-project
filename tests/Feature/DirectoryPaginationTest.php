<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DirectoryPaginationTest extends TestCase
{
    private function renderPaginator(LengthAwarePaginator $paginator): string
    {
        return Blade::render('<x-list-pagination :paginator="$paginator"/>', compact('paginator'));
    }

    public function test_empty_and_single_page_lists_show_the_active_page_and_disabled_navigation(): void
    {
        foreach ([0, 1, 5] as $total) {
            $paginator = new LengthAwarePaginator([], $total, 5, 1, ['path' => '/hr/employees']);
            $html = $this->renderPaginator($paginator);
            $this->assertStringContainsString('aria-current="page"', $html);
            $this->assertStringContainsString('Halaman 1, halaman saat ini', $html);
            $this->assertStringContainsString('ft-pagination-previous ft-pagination-disabled', $html);
            $this->assertStringContainsString('ft-pagination-next ft-pagination-disabled', $html);
            $this->assertStringNotContainsString('<a ', $html);
            $this->assertStringNotContainsString('Menampilkan', $html);
        }
    }

    public function test_many_pages_have_laravel_urls_ellipsis_and_preserve_all_query_parameters(): void
    {
        $paginator = new LengthAwarePaginator([], 500, 5, 50, ['path' => '/hr/employees']);
        $paginator->appends(['q' => 'Nama & Tim', 'division_id' => 2, 'active' => '0', 'status' => 'late',
            'from' => '2026-11-01', 'to' => '2026-11-18', 'per_page' => 5]);
        $html = $this->renderPaginator($paginator);
        foreach ([1, 49, 51, 100] as $page) {
            $this->assertStringContainsString('href="'.e($paginator->url($page)).'"', $html);
        }
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('ft-pagination-ellipsis', $html);
        $this->assertStringContainsString('Halaman 50, halaman saat ini', $html);
        $this->assertStringContainsString('ft-pagination-mobile', $html);
        $this->assertStringNotContainsString('Menampilkan', $html);
    }

    public function test_first_and_last_page_disable_only_the_correct_direction(): void
    {
        $first = $this->renderPaginator(new LengthAwarePaginator([], 30, 5, 1, ['path' => '/admin/employees']));
        $last = $this->renderPaginator(new LengthAwarePaginator([], 30, 5, 6, ['path' => '/admin/employees']));
        $this->assertStringContainsString('ft-pagination-previous ft-pagination-disabled', $first);
        $this->assertStringNotContainsString('ft-pagination-next ft-pagination-disabled', $first);
        $this->assertStringContainsString('ft-pagination-next ft-pagination-disabled', $last);
        $this->assertStringNotContainsString('ft-pagination-previous ft-pagination-disabled', $last);
    }

    public function test_audit_pagination_keeps_its_custom_page_parameter(): void
    {
        $paginator = new LengthAwarePaginator([], 35, 10, 2, ['path' => '/hr/employees/1/edit', 'pageName' => 'audit_page']);
        $html = $this->renderPaginator($paginator);
        $this->assertStringContainsString('href="/hr/employees/1/edit?audit_page=1"', $html);
        $this->assertStringContainsString('href="/hr/employees/1/edit?audit_page=3"', $html);
        $this->assertStringNotContainsString('?page=', $html);
        $this->assertStringNotContainsString('Menampilkan', $html);
    }
}
