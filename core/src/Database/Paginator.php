<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class Paginator implements \JsonSerializable
{
    protected Collection $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;
    protected string $path = '/';
    protected array $query = [];
    protected string $fragment = '';
    protected string $pageName = 'page';

    public function __construct(Collection $items, int $total, int $perPage, int $currentPage = 1)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        
        $this->path = $this->resolveCurrentPath();
    }

    public function items(): Collection
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    public function firstItem(): ?int
    {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    public function lastItem(): ?int
    {
        return count($this->items) > 0 ? $this->firstItem() + count($this->items) - 1 : null;
    }

    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = array_merge($this->query, [$this->pageName => $page]);

        $url = $this->path . '?' . http_build_query($parameters);

        if ($this->fragment) {
            $url .= '#' . $this->fragment;
        }

        return $url;
    }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage > 1) {
            return $this->url($this->currentPage - 1);
        }

        return null;
    }

    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage + 1);
        }

        return null;
    }

    public function links(int $onEachSide = 3): array
    {
        $window = $onEachSide * 2;

        if ($this->lastPage < $window + 6) {
            return $this->getSmallSlider();
        }

        if ($this->currentPage <= $window) {
            return $this->getSliderTooCloseToBeginning($window);
        } elseif ($this->currentPage > ($this->lastPage - $window)) {
            return $this->getSliderTooCloseToEnding($window);
        }

        return $this->getFullSlider($onEachSide);
    }

    protected function getSmallSlider(): array
    {
        $links = [];

        for ($page = 1; $page <= $this->lastPage; $page++) {
            $links[] = $this->createLink($page);
        }

        return $links;
    }

    protected function getSliderTooCloseToBeginning(int $window): array
    {
        $links = [];

        for ($page = 1; $page <= $window + 2; $page++) {
            $links[] = $this->createLink($page);
        }

        $links[] = $this->createEllipsis();
        $links[] = $this->createLink($this->lastPage - 1);
        $links[] = $this->createLink($this->lastPage);

        return $links;
    }

    protected function getSliderTooCloseToEnding(int $window): array
    {
        $links = [];

        $links[] = $this->createLink(1);
        $links[] = $this->createLink(2);
        $links[] = $this->createEllipsis();

        for ($page = $this->lastPage - $window - 1; $page <= $this->lastPage; $page++) {
            $links[] = $this->createLink($page);
        }

        return $links;
    }

    protected function getFullSlider(int $onEachSide): array
    {
        $links = [];

        $links[] = $this->createLink(1);
        $links[] = $this->createLink(2);
        $links[] = $this->createEllipsis();

        for ($page = $this->currentPage - $onEachSide; $page <= $this->currentPage + $onEachSide; $page++) {
            $links[] = $this->createLink($page);
        }

        $links[] = $this->createEllipsis();
        $links[] = $this->createLink($this->lastPage - 1);
        $links[] = $this->createLink($this->lastPage);

        return $links;
    }

    protected function createLink(int $page): array
    {
        return [
            'url' => $this->url($page),
            'label' => (string) $page,
            'active' => $this->currentPage === $page,
        ];
    }

    protected function createEllipsis(): array
    {
        return [
            'url' => null,
            'label' => '...',
            'active' => false,
        ];
    }

    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function appends($key, $value = null): self
    {
        if (is_array($key)) {
            $this->query = array_merge($this->query, $key);
        } else {
            $this->query[$key] = $value;
        }

        return $this;
    }

    public function fragment(string $fragment): self
    {
        $this->fragment = $fragment;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'links' => $this->links(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function resolveCurrentPath(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }
}