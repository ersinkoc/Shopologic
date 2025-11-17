<?php

declare(strict_types=1);

namespace Shopologic\Core\Search;

use Shopologic\Core\Database\DB;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Advanced search engine with full-text search and faceting
 */
class SearchEngine
{
    private DB $db;
    private CacheInterface $cache;
    private EventDispatcherInterface $events;
    private array $config;
    private array $analyzers = [];
    private array $filters = [];

    public function __construct(
        DB $db,
        CacheInterface $cache,
        EventDispatcherInterface $events,
        array $config = []
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->events = $events;
        $this->config = array_merge([
            'min_word_length' => 3,
            'max_results' => 1000,
            'cache_ttl' => 3600,
            'highlight_tag' => 'mark',
            'fuzzy_distance' => 2,
            'boost_recent' => true,
            'index_fields' => [
                'title' => ['weight' => 3.0, 'analyzer' => 'standard'],
                'content' => ['weight' => 1.0, 'analyzer' => 'standard'],
                'tags' => ['weight' => 2.0, 'analyzer' => 'keyword'],
                'category' => ['weight' => 1.5, 'analyzer' => 'keyword']
            ]
        ], $config);
        
        $this->registerDefaultAnalyzers();
        $this->registerDefaultFilters();
    }

    /**
     * Search documents
     */
    public function search(string $query, array $options = []): SearchResult
    {
        $options = array_merge([
            'index' => null,
            'fields' => null,
            'filters' => [],
            'facets' => [],
            'sort' => null,
            'from' => 0,
            'size' => 20,
            'highlight' => true,
            'fuzzy' => true,
            'suggest' => true
        ], $options);
        
        // Parse and analyze query
        $analyzedQuery = $this->analyzeQuery($query);
        
        // Check cache
        $cacheKey = $this->generateCacheKey($analyzedQuery, $options);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Execute search
        $results = $this->executeSearch($analyzedQuery, $options);
        
        // Generate facets
        if (!empty($options['facets'])) {
            $results->setFacets($this->generateFacets($analyzedQuery, $options));
        }
        
        // Generate suggestions
        if ($options['suggest'] && $results->getTotal() === 0) {
            $results->setSuggestions($this->generateSuggestions($query));
        }
        
        // Cache results
        $this->cache->set($cacheKey, $results, $this->config['cache_ttl']);
        
        // Track search
        $this->trackSearch($query, $results);
        
        return $results;
    }

    /**
     * Index document
     */
    public function index(string $type, string $id, array $document): void
    {
        // Extract indexable content
        $indexData = $this->extractIndexData($document);
        
        // Generate tokens
        $tokens = $this->tokenize($indexData);
        
        // Store document
        $this->db->table('search_documents')->updateOrInsert(
            ['type' => $type, 'document_id' => $id],
            [
                'content' => json_encode($document),
                'tokens' => json_encode($tokens),
                'indexed_at' => date('Y-m-d H:i:s'),
                'boost' => $document['_boost'] ?? 1.0
            ]
        );
        
        // Update index
        $this->updateIndex($type, $id, $tokens);
        
        $this->events->dispatch('search.document_indexed', [
            'type' => $type,
            'id' => $id
        ]);
    }

    /**
     * Delete document from index
     */
    public function delete(string $type, string $id): void
    {
        // Delete document
        $this->db->table('search_documents')
            ->where('type', $type)
            ->where('document_id', $id)
            ->delete();
        
        // Delete from index
        $this->db->table('search_index')
            ->where('type', $type)
            ->where('document_id', $id)
            ->delete();
        
        $this->events->dispatch('search.document_deleted', [
            'type' => $type,
            'id' => $id
        ]);
    }

    /**
     * Bulk index documents
     */
    public function bulkIndex(string $type, array $documents): void
    {
        $this->db->transaction(function () use ($type, $documents) {
            foreach ($documents as $id => $document) {
                $this->index($type, (string)$id, $document);
            }
        });
    }

    /**
     * Reindex all documents of type
     */
    public function reindex(string $type): void
    {
        // Clear existing index for type
        $this->db->table('search_index')->where('type', $type)->delete();
        
        // Get all documents
        $documents = $this->db->table('search_documents')
            ->where('type', $type)
            ->get();
        
        foreach ($documents as $doc) {
            $tokens = json_decode($doc->tokens, true);
            $this->updateIndex($type, $doc->document_id, $tokens);
        }
        
        $this->events->dispatch('search.reindexed', ['type' => $type]);
    }

    /**
     * Get search suggestions
     */
    public function suggest(string $prefix, array $options = []): array
    {
        $options = array_merge([
            'size' => 10,
            'fuzzy' => true,
            'context' => []
        ], $options);
        
        $suggestions = [];
        
        // Get term suggestions
        $terms = $this->getTermSuggestions($prefix, $options);
        
        // Get phrase suggestions
        $phrases = $this->getPhraseSuggestions($prefix, $options);
        
        // Get completion suggestions
        $completions = $this->getCompletionSuggestions($prefix, $options);
        
        return array_merge($terms, $phrases, $completions);
    }

    /**
     * Get popular searches
     */
    public function getPopularSearches(int $limit = 10): array
    {
        return $this->cache->remember('popular_searches', 3600, function () use ($limit) {
            return $this->db->table('search_queries')
                ->select('query', 'COUNT(*) as count')
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
                ->groupBy('query')
                ->orderBy('count', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Register custom analyzer
     */
    public function registerAnalyzer(string $name, AnalyzerInterface $analyzer): void
    {
        $this->analyzers[$name] = $analyzer;
    }

    /**
     * Register custom filter
     */
    public function registerFilter(string $name, FilterInterface $filter): void
    {
        $this->filters[$name] = $filter;
    }

    // Private methods

    private function registerDefaultAnalyzers(): void
    {
        $this->analyzers['standard'] = new StandardAnalyzer();
        $this->analyzers['keyword'] = new KeywordAnalyzer();
        $this->analyzers['stemming'] = new StemmingAnalyzer();
        $this->analyzers['phonetic'] = new PhoneticAnalyzer();
    }

    private function registerDefaultFilters(): void
    {
        $this->filters['lowercase'] = new LowercaseFilter();
        $this->filters['stopwords'] = new StopwordsFilter();
        $this->filters['synonyms'] = new SynonymsFilter();
        $this->filters['ngram'] = new NgramFilter();
    }

    private function analyzeQuery(string $query): AnalyzedQuery
    {
        $analyzer = $this->analyzers['standard'];
        
        // Tokenize query
        $tokens = $analyzer->analyze($query);
        
        // Parse special operators
        $parsed = $this->parseQueryOperators($query);
        
        return new AnalyzedQuery($query, $tokens, $parsed);
    }

    private function parseQueryOperators(string $query): array
    {
        $parsed = [
            'must' => [],
            'should' => [],
            'must_not' => [],
            'phrase' => [],
            'wildcard' => []
        ];
        
        // Parse quoted phrases
        if (preg_match_all('/"([^"]+)"/', $query, $matches)) {
            $parsed['phrase'] = $matches[1];
            $query = preg_replace('/"[^"]+"/', '', $query);
        }
        
        // Parse exclusions (-)
        if (preg_match_all('/-(\w+)/', $query, $matches)) {
            $parsed['must_not'] = $matches[1];
            $query = preg_replace('/-\w+/', '', $query);
        }
        
        // Parse required terms (+)
        if (preg_match_all('/\+(\w+)/', $query, $matches)) {
            $parsed['must'] = $matches[1];
            $query = preg_replace('/\+\w+/', '', $query);
        }
        
        // Parse wildcards (*)
        if (preg_match_all('/(\w+\*|\*\w+)/', $query, $matches)) {
            $parsed['wildcard'] = $matches[1];
            $query = preg_replace('/\w+\*|\*\w+/', '', $query);
        }
        
        // Remaining terms are optional
        $parsed['should'] = array_filter(explode(' ', trim($query)));
        
        return $parsed;
    }

    private function executeSearch(AnalyzedQuery $query, array $options): SearchResult
    {
        $qb = $this->buildSearchQuery($query, $options);
        
        // Get total count
        $total = (clone $qb)->count();
        
        // Apply pagination
        $qb->offset($options['from'])->limit($options['size']);
        
        // Apply sorting
        if ($options['sort']) {
            $this->applySorting($qb, $options['sort']);
        } else {
            $qb->orderBy('score', 'desc');
        }
        
        // Execute query
        $results = $qb->get();
        
        // Process results
        $hits = [];
        foreach ($results as $row) {
            $document = json_decode($row->content, true);
            
            // Add highlighting
            if ($options['highlight']) {
                $document['_highlight'] = $this->highlight($document, $query);
            }
            
            $hits[] = [
                'id' => $row->document_id,
                'type' => $row->type,
                'score' => $row->score,
                'document' => $document
            ];
        }
        
        return new SearchResult($hits, $total, $options['from'], $options['size']);
    }

    private function buildSearchQuery(AnalyzedQuery $query, array $options)
    {
        $qb = $this->db->table('search_documents as d')
            ->join('search_index as i', function ($join) {
                $join->on('d.type', '=', 'i.type')
                    ->on('d.document_id', '=', 'i.document_id');
            })
            ->select('d.*')
            ->selectRaw('SUM(i.score * i.weight * d.boost) as score')
            ->groupBy('d.type', 'd.document_id');
        
        // Apply type filter
        if ($options['index']) {
            $qb->where('d.type', $options['index']);
        }
        
        // Apply search conditions
        $this->applySearchConditions($qb, $query);
        
        // Apply filters
        if (!empty($options['filters'])) {
            $this->applyFilters($qb, $options['filters']);
        }
        
        return $qb;
    }

    private function applySearchConditions($qb, AnalyzedQuery $query): void
    {
        $qb->where(function ($q) use ($query) {
            // Must conditions (AND)
            foreach ($query->getMustTerms() as $term) {
                $q->whereExists(function ($sub) use ($term) {
                    $sub->select('1')
                        ->from('search_index')
                        ->whereColumn('search_index.document_id', '=', 'd.document_id')
                        ->whereColumn('search_index.type', '=', 'd.type')
                        ->where('term', $term);
                });
            }
            
            // Should conditions (OR)
            if ($query->hasShouldTerms()) {
                $q->whereIn('i.term', $query->getShouldTerms());
            }
            
            // Must not conditions
            foreach ($query->getMustNotTerms() as $term) {
                $q->whereNotExists(function ($sub) use ($term) {
                    $sub->select('1')
                        ->from('search_index')
                        ->whereColumn('search_index.document_id', '=', 'd.document_id')
                        ->whereColumn('search_index.type', '=', 'd.type')
                        ->where('term', $term);
                });
            }
            
            // Phrase queries
            foreach ($query->getPhrases() as $phrase) {
                $q->whereRaw("d.content LIKE ?", ['%' . $phrase . '%']);
            }
            
            // Wildcard queries
            foreach ($query->getWildcards() as $wildcard) {
                $pattern = str_replace('*', '%', $wildcard);
                $q->whereExists(function ($sub) use ($pattern) {
                    $sub->select('1')
                        ->from('search_index')
                        ->whereColumn('search_index.document_id', '=', 'd.document_id')
                        ->whereColumn('search_index.type', '=', 'd.type')
                        ->where('term', 'LIKE', $pattern);
                });
            }
        });
    }

    private function applyFilters($qb, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $qb->whereIn("JSON_EXTRACT(d.content, '$.{$field}')", $value);
            } else {
                $qb->where("JSON_EXTRACT(d.content, '$.{$field}')", $value);
            }
        }
    }

    /**
     * Apply sorting with SQL injection protection
     * BUG-SEC-002 FIX: Added whitelist validation to prevent SQL injection
     */
    private function applySorting($qb, $sort): void
    {
        $allowedFields = ['name', 'created_at', 'updated_at', 'price', 'rating', 'relevance', 'popularity'];
        $allowedDirections = ['ASC', 'DESC'];

        if (is_string($sort)) {
            if (in_array($sort, $allowedFields)) {
                $qb->orderBy($sort);
            }
        } elseif (is_array($sort)) {
            foreach ($sort as $field => $direction) {
                $direction = strtoupper($direction);
                if (!in_array($direction, $allowedDirections)) {
                    continue;
                }

                if ($field === '_score') {
                    $qb->orderBy('score', $direction);
                } elseif (in_array($field, $allowedFields)) {
                    // Validate field name against whitelist before using in JSON_EXTRACT
                    $qb->orderBy("JSON_EXTRACT(d.content, '$.{$field}')", $direction);
                }
            }
        }
    }

    private function generateFacets(AnalyzedQuery $query, array $options): array
    {
        $facets = [];
        
        foreach ($options['facets'] as $field => $config) {
            $facets[$field] = $this->generateFacet($field, $config, $query, $options);
        }
        
        return $facets;
    }

    private function generateFacet(string $field, $config, AnalyzedQuery $query, array $options): array
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }
        
        $config = array_merge([
            'type' => 'terms',
            'size' => 10,
            'min_count' => 1
        ], $config);
        
        switch ($config['type']) {
            case 'terms':
                return $this->generateTermsFacet($field, $config, $query, $options);
                
            case 'range':
                return $this->generateRangeFacet($field, $config, $query, $options);
                
            case 'histogram':
                return $this->generateHistogramFacet($field, $config, $query, $options);
                
            default:
                return [];
        }
    }

    private function generateTermsFacet(string $field, array $config, AnalyzedQuery $query, array $options): array
    {
        $qb = $this->buildSearchQuery($query, $options);
        
        $results = (clone $qb)
            ->select("JSON_EXTRACT(d.content, '$.{$field}') as value", 'COUNT(*) as count')
            ->groupBy('value')
            ->orderBy('count', 'desc')
            ->limit($config['size'])
            ->having('count', '>=', $config['min_count'])
            ->get();
        
        return $results->map(function ($row) {
            return [
                'value' => json_decode($row->value),
                'count' => (int)$row->count
            ];
        })->toArray();
    }

    private function generateRangeFacet(string $field, array $config, AnalyzedQuery $query, array $options): array
    {
        $qb = $this->buildSearchQuery($query, $options);
        
        $stats = (clone $qb)
            ->selectRaw("
                MIN(CAST(JSON_EXTRACT(d.content, '$.{$field}') AS DECIMAL)) as min,
                MAX(CAST(JSON_EXTRACT(d.content, '$.{$field}') AS DECIMAL)) as max,
                COUNT(*) as count
            ")
            ->first();
        
        $ranges = [];
        
        if (isset($config['ranges'])) {
            foreach ($config['ranges'] as $range) {
                $count = (clone $qb)
                    ->whereRaw("CAST(JSON_EXTRACT(d.content, '$.{$field}') AS DECIMAL) >= ?", [$range['from'] ?? 0])
                    ->whereRaw("CAST(JSON_EXTRACT(d.content, '$.{$field}') AS DECIMAL) < ?", [$range['to'] ?? PHP_INT_MAX])
                    ->count();
                
                $ranges[] = [
                    'from' => $range['from'] ?? null,
                    'to' => $range['to'] ?? null,
                    'count' => $count
                ];
            }
        }
        
        return [
            'min' => $stats->min,
            'max' => $stats->max,
            'ranges' => $ranges
        ];
    }

    private function generateHistogramFacet(string $field, array $config, AnalyzedQuery $query, array $options): array
    {
        $qb = $this->buildSearchQuery($query, $options);
        
        $interval = $config['interval'] ?? 1;
        
        $results = (clone $qb)
            ->selectRaw("
                FLOOR(CAST(JSON_EXTRACT(d.content, '$.{$field}') AS DECIMAL) / ?) * ? as bucket,
                COUNT(*) as count
            ", [$interval, $interval])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();
        
        return $results->map(function ($row) use ($interval) {
            return [
                'key' => $row->bucket,
                'from' => $row->bucket,
                'to' => $row->bucket + $interval,
                'count' => (int)$row->count
            ];
        })->toArray();
    }

    private function generateSuggestions(string $query): array
    {
        $suggestions = [];
        
        // Check for typos using Levenshtein distance
        $terms = explode(' ', strtolower($query));
        
        foreach ($terms as $term) {
            if (strlen($term) < $this->config['min_word_length']) {
                continue;
            }
            
            $similar = $this->db->table('search_terms')
                ->select('term', 'frequency')
                ->whereRaw('LEVENSHTEIN(term, ?) <= ?', [$term, $this->config['fuzzy_distance']])
                ->where('term', '!=', $term)
                ->orderBy('frequency', 'desc')
                ->limit(5)
                ->get();
            
            foreach ($similar as $suggestion) {
                $suggestions[] = [
                    'text' => str_replace($term, $suggestion->term, $query),
                    'score' => $suggestion->frequency
                ];
            }
        }
        
        return array_slice($suggestions, 0, 5);
    }

    private function extractIndexData(array $document): array
    {
        $indexData = [];
        
        foreach ($this->config['index_fields'] as $field => $config) {
            if (isset($document[$field])) {
                $indexData[$field] = [
                    'value' => $document[$field],
                    'weight' => $config['weight'],
                    'analyzer' => $config['analyzer']
                ];
            }
        }
        
        return $indexData;
    }

    private function tokenize(array $indexData): array
    {
        $tokens = [];
        
        foreach ($indexData as $field => $data) {
            $analyzer = $this->analyzers[$data['analyzer']] ?? $this->analyzers['standard'];
            $fieldTokens = $analyzer->analyze($data['value']);
            
            foreach ($fieldTokens as $token) {
                $tokens[] = [
                    'term' => $token,
                    'field' => $field,
                    'weight' => $data['weight']
                ];
            }
        }
        
        return $tokens;
    }

    private function updateIndex(string $type, string $id, array $tokens): void
    {
        // Delete existing index entries
        $this->db->table('search_index')
            ->where('type', $type)
            ->where('document_id', $id)
            ->delete();
        
        // Calculate term frequencies
        $termFreqs = [];
        foreach ($tokens as $token) {
            $key = $token['term'] . '|' . $token['field'];
            if (!isset($termFreqs[$key])) {
                $termFreqs[$key] = [
                    'term' => $token['term'],
                    'field' => $token['field'],
                    'weight' => $token['weight'],
                    'frequency' => 0
                ];
            }
            $termFreqs[$key]['frequency']++;
        }
        
        // Insert new index entries
        $entries = [];
        foreach ($termFreqs as $data) {
            $entries[] = [
                'type' => $type,
                'document_id' => $id,
                'term' => $data['term'],
                'field' => $data['field'],
                'frequency' => $data['frequency'],
                'weight' => $data['weight'],
                'score' => $this->calculateTermScore($data)
            ];
        }
        
        if (!empty($entries)) {
            $this->db->table('search_index')->insert($entries);
        }
        
        // Update term statistics
        $this->updateTermStatistics($tokens);
    }

    private function calculateTermScore(array $data): float
    {
        // TF-IDF scoring
        $tf = 1 + log($data['frequency']);
        $idf = $this->getInverseDocumentFrequency($data['term']);
        
        return $tf * $idf * $data['weight'];
    }

    private function getInverseDocumentFrequency(string $term): float
    {
        $totalDocs = $this->db->table('search_documents')->count();
        $termDocs = $this->db->table('search_index')
            ->where('term', $term)
            ->distinct('document_id')
            ->count();
        
        if ($termDocs === 0) {
            return 0;
        }
        
        return log($totalDocs / $termDocs);
    }

    private function updateTermStatistics(array $tokens): void
    {
        $terms = array_unique(array_column($tokens, 'term'));
        
        foreach ($terms as $term) {
            $this->db->table('search_terms')->updateOrInsert(
                ['term' => $term],
                [
                    'frequency' => $this->db->raw('frequency + 1'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
        }
    }

    private function highlight(array $document, AnalyzedQuery $query): array
    {
        $highlights = [];
        
        foreach ($this->config['index_fields'] as $field => $config) {
            if (!isset($document[$field])) {
                continue;
            }
            
            $text = $document[$field];
            $highlighted = $text;
            
            // Highlight query terms
            foreach ($query->getAllTerms() as $term) {
                $pattern = '/\b(' . preg_quote($term, '/') . ')\b/i';
                $replacement = '<' . $this->config['highlight_tag'] . '>$1</' . $this->config['highlight_tag'] . '>';
                $highlighted = preg_replace($pattern, $replacement, $highlighted);
            }
            
            if ($highlighted !== $text) {
                $highlights[$field] = $highlighted;
            }
        }
        
        return $highlights;
    }

    private function trackSearch(string $query, SearchResult $results): void
    {
        $this->db->table('search_queries')->insert([
            'query' => $query,
            'results_count' => $results->getTotal(),
            'clicked_position' => null,
            'search_time' => $results->getTook(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->events->dispatch('search.query_executed', [
            'query' => $query,
            'results' => $results->getTotal()
        ]);
    }

    private function generateCacheKey(AnalyzedQuery $query, array $options): string
    {
        return 'search:' . md5(serialize([$query->getOriginal(), $options]));
    }

    private function getTermSuggestions(string $prefix, array $options): array
    {
        $terms = $this->db->table('search_terms')
            ->where('term', 'LIKE', $prefix . '%')
            ->orderBy('frequency', 'desc')
            ->limit($options['size'])
            ->pluck('term')
            ->toArray();
        
        return array_map(function ($term) {
            return ['type' => 'term', 'value' => $term];
        }, $terms);
    }

    private function getPhraseSuggestions(string $prefix, array $options): array
    {
        $phrases = $this->db->table('search_queries')
            ->select('query', 'COUNT(*) as count')
            ->where('query', 'LIKE', $prefix . '%')
            ->where('results_count', '>', 0)
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit($options['size'])
            ->get();
        
        return $phrases->map(function ($phrase) {
            return ['type' => 'phrase', 'value' => $phrase->query];
        })->toArray();
    }

    private function getCompletionSuggestions(string $prefix, array $options): array
    {
        // Context-aware completions based on user behavior
        $completions = [];
        
        if (!empty($options['context'])) {
            // Get completions based on context
        }
        
        return $completions;
    }
}

/**
 * Analyzed query class
 */
class AnalyzedQuery
{
    private string $original;
    private array $tokens;
    private array $parsed;

    public function __construct(string $original, array $tokens, array $parsed)
    {
        $this->original = $original;
        $this->tokens = $tokens;
        $this->parsed = $parsed;
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getMustTerms(): array
    {
        return $this->parsed['must'];
    }

    public function getShouldTerms(): array
    {
        return $this->parsed['should'];
    }

    public function getMustNotTerms(): array
    {
        return $this->parsed['must_not'];
    }

    public function getPhrases(): array
    {
        return $this->parsed['phrase'];
    }

    public function getWildcards(): array
    {
        return $this->parsed['wildcard'];
    }

    public function hasShouldTerms(): bool
    {
        return !empty($this->parsed['should']);
    }

    public function getAllTerms(): array
    {
        return array_merge(
            $this->parsed['must'],
            $this->parsed['should'],
            $this->tokens
        );
    }
}

/**
 * Search result class
 */
class SearchResult
{
    private array $hits;
    private int $total;
    private int $from;
    private int $size;
    private array $facets = [];
    private array $suggestions = [];
    private float $took;

    public function __construct(array $hits, int $total, int $from, int $size)
    {
        $this->hits = $hits;
        $this->total = $total;
        $this->from = $from;
        $this->size = $size;
        $this->took = microtime(true);
    }

    public function getHits(): array
    {
        return $this->hits;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    public function setFacets(array $facets): void
    {
        $this->facets = $facets;
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function setSuggestions(array $suggestions): void
    {
        $this->suggestions = $suggestions;
    }

    public function getTook(): float
    {
        return microtime(true) - $this->took;
    }

    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'total' => $this->total,
            'from' => $this->from,
            'size' => $this->size,
            'facets' => $this->facets,
            'suggestions' => $this->suggestions,
            'took' => $this->getTook()
        ];
    }
}

// Analyzer interfaces and implementations

interface AnalyzerInterface
{
    public function analyze(string $text): array;
}

interface FilterInterface
{
    public function filter(array $tokens): array;
}

class StandardAnalyzer implements AnalyzerInterface
{
    public function analyze(string $text): array
    {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove punctuation and split into tokens
        $tokens = preg_split('/[\s\-_,\.;:!?\'"]+/', $text);
        
        // Remove empty tokens
        return array_filter($tokens);
    }
}

class KeywordAnalyzer implements AnalyzerInterface
{
    public function analyze(string $text): array
    {
        return [strtolower(trim($text))];
    }
}

class StemmingAnalyzer implements AnalyzerInterface
{
    public function analyze(string $text): array
    {
        $tokens = (new StandardAnalyzer())->analyze($text);
        
        // Apply Porter stemming algorithm
        return array_map([$this, 'stem'], $tokens);
    }
    
    private function stem(string $word): string
    {
        // Simplified Porter stemmer
        if (substr($word, -3) === 'ing') {
            return substr($word, 0, -3);
        }
        if (substr($word, -2) === 'ed') {
            return substr($word, 0, -2);
        }
        if (substr($word, -1) === 's' && substr($word, -2) !== 'ss') {
            return substr($word, 0, -1);
        }
        
        return $word;
    }
}

class PhoneticAnalyzer implements AnalyzerInterface
{
    public function analyze(string $text): array
    {
        $tokens = (new StandardAnalyzer())->analyze($text);
        
        // Apply Soundex algorithm
        return array_map('soundex', $tokens);
    }
}

class LowercaseFilter implements FilterInterface
{
    public function filter(array $tokens): array
    {
        return array_map('strtolower', $tokens);
    }
}

class StopwordsFilter implements FilterInterface
{
    private array $stopwords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for',
        'from', 'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on',
        'that', 'the', 'to', 'was', 'will', 'with'
    ];
    
    public function filter(array $tokens): array
    {
        return array_filter($tokens, function ($token) {
            return !in_array($token, $this->stopwords);
        });
    }
}

class SynonymsFilter implements FilterInterface
{
    private array $synonyms = [
        'buy' => ['purchase', 'order'],
        'search' => ['find', 'look for'],
        'product' => ['item', 'article']
    ];
    
    public function filter(array $tokens): array
    {
        $expanded = [];
        
        foreach ($tokens as $token) {
            $expanded[] = $token;
            
            if (isset($this->synonyms[$token])) {
                $expanded = array_merge($expanded, $this->synonyms[$token]);
            }
        }
        
        return array_unique($expanded);
    }
}

class NgramFilter implements FilterInterface
{
    private int $min = 3;
    private int $max = 5;
    
    public function filter(array $tokens): array
    {
        $ngrams = [];
        
        foreach ($tokens as $token) {
            $length = strlen($token);
            
            for ($n = $this->min; $n <= min($this->max, $length); $n++) {
                for ($i = 0; $i <= $length - $n; $i++) {
                    $ngrams[] = substr($token, $i, $n);
                }
            }
        }
        
        return array_unique(array_merge($tokens, $ngrams));
    }
}