<?php
namespace SmartSearch;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Smart Search Engine Plugin
 * 
 * Advanced search with NLP, autocomplete, faceted filtering, and visual search
 */
class SmartSearchPlugin extends AbstractPlugin
{
    private $searchEngine;
    private $nlpProcessor;
    private $indexManager;
    private $facetEngine;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeSearchIndex();
    }

    private function registerServices(): void
    {
        $this->searchEngine = new Services\SearchEngine($this->api);
        $this->nlpProcessor = new Services\NLPProcessor($this->api);
        $this->indexManager = new Services\IndexManager($this->api);
        $this->facetEngine = new Services\FacetEngine($this->api);
    }

    private function registerHooks(): void
    {
        // Search interface enhancements
        Hook::addFilter('search.form', [$this, 'enhanceSearchForm'], 10, 1);
        Hook::addFilter('search.query', [$this, 'processSearchQuery'], 5, 1);
        Hook::addFilter('search.results', [$this, 'enhanceSearchResults'], 10, 2);
        
        // Autocomplete and suggestions
        Hook::addAction('search.autocomplete', [$this, 'generateAutocomplete'], 10, 1);
        Hook::addFilter('search.suggestions', [$this, 'generateSuggestions'], 10, 2);
        
        // Index management
        Hook::addAction('product.created', [$this, 'indexProduct'], 10, 1);
        Hook::addAction('product.updated', [$this, 'reindexProduct'], 10, 1);
        Hook::addAction('product.deleted', [$this, 'removeFromIndex'], 10, 1);
        Hook::addAction('product.indexed', [$this, 'enhanceProductIndex'], 10, 2);
        
        // Analytics
        Hook::addAction('search.performed', [$this, 'trackSearchQuery'], 10, 2);
        Hook::addAction('search.result_clicked', [$this, 'trackClickThrough'], 10, 3);
    }

    public function enhanceSearchForm($form): string
    {
        $enhancements = $this->api->view('smart-search/form-enhancements', [
            'enable_autocomplete' => $this->getConfig('enable_autocomplete', true),
            'enable_voice_search' => true,
            'enable_image_search' => true,
            'popular_searches' => $this->getPopularSearches(),
            'search_filters' => $this->facetEngine->getAvailableFilters()
        ]);

        return str_replace('</form>', $enhancements . '</form>', $form);
    }

    public function processSearchQuery($query): string
    {
        // NLP processing
        $processedQuery = $this->nlpProcessor->processQuery($query);
        
        // Spell check and correction
        if ($this->getConfig('enable_spell_check', true)) {
            $corrected = $this->nlpProcessor->spellCheck($processedQuery);
            if ($corrected !== $processedQuery) {
                $this->api->session()->flash('search_correction', [
                    'original' => $query,
                    'corrected' => $corrected
                ]);
                $processedQuery = $corrected;
            }
        }
        
        // Synonym expansion
        if ($this->getConfig('enable_synonym_search', true)) {
            $processedQuery = $this->nlpProcessor->expandSynonyms($processedQuery);
        }
        
        // Extract intent and entities
        $intent = $this->nlpProcessor->extractIntent($processedQuery);
        $this->api->session()->set('search_intent', $intent);
        
        return $processedQuery;
    }

    public function enhanceSearchResults($results, $query): array
    {
        // Apply relevance scoring
        $scoredResults = $this->searchEngine->scoreResults($results, $query);
        
        // Boost exact matches
        if ($this->getConfig('boost_exact_matches', true)) {
            $scoredResults = $this->boostExactMatches($scoredResults, $query);
        }
        
        // Add facets
        $facets = $this->facetEngine->generateFacets($scoredResults, $query);
        
        // Add related searches
        $relatedSearches = $this->generateRelatedSearches($query, $scoredResults);
        
        // Visual grouping
        $groupedResults = $this->groupResults($scoredResults);
        
        return [
            'results' => $scoredResults,
            'facets' => $facets,
            'related_searches' => $relatedSearches,
            'groups' => $groupedResults,
            'total_count' => count($results),
            'query_info' => [
                'original' => $query,
                'processed' => $this->api->session()->get('processed_query'),
                'intent' => $this->api->session()->get('search_intent')
            ]
        ];
    }

    public function generateAutocomplete($query): array
    {
        if (!$this->getConfig('enable_autocomplete', true)) {
            return [];
        }

        $suggestions = [];
        
        // Product suggestions
        $productSuggestions = $this->searchEngine->getProductSuggestions($query, 5);
        foreach ($productSuggestions as $product) {
            $suggestions[] = [
                'type' => 'product',
                'title' => $product->name,
                'subtitle' => '$' . $product->price,
                'url' => "/products/{$product->slug}",
                'image' => $product->thumbnail,
                'category' => $product->category_name
            ];
        }
        
        // Category suggestions
        $categorySuggestions = $this->searchEngine->getCategorySuggestions($query, 3);
        foreach ($categorySuggestions as $category) {
            $suggestions[] = [
                'type' => 'category',
                'title' => $category->name,
                'subtitle' => $category->product_count . ' products',
                'url' => "/categories/{$category->slug}",
                'icon' => 'folder'
            ];
        }
        
        // Search query suggestions
        $querySuggestions = $this->searchEngine->getQuerySuggestions($query, 5);
        foreach ($querySuggestions as $suggestion) {
            $suggestions[] = [
                'type' => 'query',
                'title' => $suggestion->query,
                'subtitle' => 'Search for "' . $suggestion->query . '"',
                'url' => "/search?q=" . urlencode($suggestion->query),
                'icon' => 'search'
            ];
        }
        
        // Brand suggestions
        $brandSuggestions = $this->searchEngine->getBrandSuggestions($query, 3);
        foreach ($brandSuggestions as $brand) {
            $suggestions[] = [
                'type' => 'brand',
                'title' => $brand->name,
                'subtitle' => 'View all ' . $brand->name . ' products',
                'url' => "/brands/{$brand->slug}",
                'logo' => $brand->logo
            ];
        }
        
        return $suggestions;
    }

    public function generateSuggestions($results, $query): array
    {
        $suggestions = [];
        
        // Did you mean suggestions
        if (empty($results) || count($results) < 3) {
            $alternatives = $this->nlpProcessor->findAlternatives($query);
            if (!empty($alternatives)) {
                $suggestions['did_you_mean'] = $alternatives;
            }
        }
        
        // Trending related searches
        $trending = $this->getTrendingSearches($query);
        if (!empty($trending)) {
            $suggestions['trending'] = $trending;
        }
        
        // Category suggestions based on results
        $categories = $this->extractCategories($results);
        if (!empty($categories)) {
            $suggestions['browse_categories'] = $categories;
        }
        
        return $suggestions;
    }

    public function indexProduct($product): void
    {
        $indexData = $this->prepareProductForIndex($product);
        $this->indexManager->index('products', $product->id, $indexData);
        
        Hook::doAction('product.indexed', $product, $indexData);
    }

    public function reindexProduct($product): void
    {
        $this->removeFromIndex($product->id);
        $this->indexProduct($product);
    }

    public function removeFromIndex($productId): void
    {
        $this->indexManager->remove('products', $productId);
    }

    public function enhanceProductIndex($product, &$indexData): void
    {
        // Add semantic vectors
        $indexData['semantic_vector'] = $this->nlpProcessor->generateSemanticVector($product);
        
        // Add search keywords
        $indexData['search_keywords'] = $this->generateSearchKeywords($product);
        
        // Add facet data
        $indexData['facets'] = $this->extractFacetData($product);
        
        // Add popularity score
        $indexData['popularity_score'] = $this->calculatePopularityScore($product);
    }

    public function trackSearchQuery($query, $results): void
    {
        $this->api->database()->table('search_queries')->insert([
            'query' => $query,
            'results_count' => count($results),
            'user_id' => $this->api->auth()->user()?->id,
            'session_id' => session_id(),
            'ip_address' => $this->api->request()->ip(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Update search trends
        $this->updateSearchTrends($query);
    }

    public function trackClickThrough($query, $resultId, $position): void
    {
        $this->api->database()->table('search_analytics')->insert([
            'query' => $query,
            'clicked_result_id' => $resultId,
            'position' => $position,
            'user_id' => $this->api->auth()->user()?->id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Update relevance scores
        $this->searchEngine->updateRelevanceScore($query, $resultId, $position);
    }

    private function prepareProductForIndex($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => strip_tags($product->description),
            'category' => $product->category_name,
            'brand' => $product->brand,
            'price' => $product->price,
            'sku' => $product->sku,
            'tags' => $product->tags,
            'attributes' => $product->attributes,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at
        ];
    }

    private function boostExactMatches($results, $query): array
    {
        $queryLower = strtolower($query);
        
        foreach ($results as &$result) {
            if (strtolower($result['name']) === $queryLower) {
                $result['score'] *= 2.0;
            } elseif (strpos(strtolower($result['name']), $queryLower) === 0) {
                $result['score'] *= 1.5;
            }
        }
        
        // Re-sort by score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $results;
    }

    private function generateRelatedSearches($query, $results): array
    {
        $related = [];
        
        // Extract common attributes from results
        $attributes = $this->extractCommonAttributes($results);
        
        foreach ($attributes as $attribute => $values) {
            foreach ($values as $value) {
                $related[] = $query . ' ' . $value;
            }
        }
        
        // Add category-based suggestions
        $categories = array_unique(array_column($results, 'category'));
        foreach ($categories as $category) {
            $related[] = $category . ' ' . $query;
        }
        
        return array_slice(array_unique($related), 0, 6);
    }

    private function groupResults($results): array
    {
        $groups = [];
        
        foreach ($results as $result) {
            $groupKey = $result['category'] ?? 'Other';
            $groups[$groupKey][] = $result;
        }
        
        return $groups;
    }

    private function getPopularSearches(): array
    {
        return $this->api->cache()->remember('popular_searches', 3600, function() {
            return $this->api->database()->table('search_queries')
                ->select('query', $this->api->database()->raw('COUNT(*) as count'))
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
                ->groupBy('query')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray();
        });
    }

    private function getTrendingSearches($relatedTo = null): array
    {
        $query = $this->api->database()->table('search_queries')
            ->select('query', $this->api->database()->raw('COUNT(*) as count'))
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')));
            
        if ($relatedTo) {
            $query->where('query', 'like', '%' . $relatedTo . '%');
        }
        
        return $query->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function generateSearchKeywords($product): array
    {
        $keywords = [];
        
        // Extract from name
        $keywords = array_merge($keywords, $this->nlpProcessor->extractKeywords($product->name));
        
        // Extract from description
        $keywords = array_merge($keywords, $this->nlpProcessor->extractKeywords($product->description));
        
        // Add attributes
        foreach ($product->attributes as $attr => $value) {
            $keywords[] = $attr . ':' . $value;
        }
        
        // Add category hierarchy
        $keywords = array_merge($keywords, $this->getCategoryHierarchy($product->category_id));
        
        return array_unique($keywords);
    }

    private function extractFacetData($product): array
    {
        return [
            'price_range' => $this->getPriceRange($product->price),
            'brand' => $product->brand,
            'category' => $product->category_id,
            'rating' => $product->average_rating,
            'availability' => $product->stock_quantity > 0 ? 'in_stock' : 'out_of_stock',
            'attributes' => $product->attributes
        ];
    }

    private function calculatePopularityScore($product): float
    {
        $score = 0;
        
        // Sales volume
        $salesCount = $this->api->database()->table('order_items')
            ->where('product_id', $product->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->count();
        $score += $salesCount * 0.5;
        
        // View count
        $viewCount = $this->api->database()->table('product_views')
            ->where('product_id', $product->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->count();
        $score += $viewCount * 0.3;
        
        // Rating
        $score += $product->average_rating * 0.2;
        
        return $score;
    }

    private function initializeSearchIndex(): void
    {
        // Schedule index updates
        $this->api->scheduler()->addJob('search_index_update', '0 3 * * *', function() {
            $this->indexManager->rebuildIndex('products');
        });
        
        // Schedule analytics aggregation
        $this->api->scheduler()->addJob('search_analytics', '0 2 * * *', function() {
            $this->aggregateSearchAnalytics();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/search/autocomplete', 'Controllers\SearchController@autocomplete');
        $this->api->router()->post('/search/query', 'Controllers\SearchController@search');
        $this->api->router()->get('/search/facets', 'Controllers\SearchController@getFacets');
        $this->api->router()->post('/search/visual', 'Controllers\SearchController@visualSearch');
        $this->api->router()->get('/search/trending', 'Controllers\SearchController@getTrending');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createSearchIndex();
        $this->loadDefaultSynonyms();
    }

    private function createSearchIndex(): void
    {
        $this->indexManager->createIndex('products', [
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'text', 'analyzer' => 'standard'],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                    'category' => ['type' => 'keyword'],
                    'brand' => ['type' => 'keyword'],
                    'price' => ['type' => 'float'],
                    'attributes' => ['type' => 'object'],
                    'semantic_vector' => ['type' => 'dense_vector', 'dims' => 128]
                ]
            ]
        ]);
    }

    private function loadDefaultSynonyms(): void
    {
        $synonyms = [
            ['term' => 'laptop', 'synonyms' => ['notebook', 'computer', 'pc']],
            ['term' => 'phone', 'synonyms' => ['mobile', 'smartphone', 'cellphone']],
            ['term' => 'tv', 'synonyms' => ['television', 'display', 'monitor']]
        ];

        foreach ($synonyms as $synonym) {
            $this->api->database()->table('search_synonyms')->insert($synonym);
        }
    }
}