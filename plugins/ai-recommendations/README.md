# AI Product Recommendations Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Intelligent product recommendations using machine learning to increase sales through personalized suggestions, cross-selling, and upselling.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem with cross-plugin integration, real-time events, performance monitoring, and automated testing capabilities.

## Features

### 🧠 Multiple AI Algorithms
- **Collaborative Filtering**: Recommendations based on user behavior similarities
- **Content-Based Filtering**: Recommendations based on product characteristics
- **Hybrid Engine**: Combines collaborative and content-based approaches
- **Deep Learning**: Neural network-based recommendations for advanced personalization

### 📊 Recommendation Types
- **Similar Products**: Find products similar to currently viewed items
- **Frequently Bought Together**: Cross-selling opportunities
- **Personalized**: User-specific recommendations based on behavior
- **Trending**: Popular products in relevant categories
- **New Arrivals**: Recent additions to boost discovery
- **Best Sellers**: Top-performing products
- **Recently Viewed**: User's browsing history
- **Cross-Category**: Suggestions from different categories

### 🎯 Smart Targeting
- Real-time personalization
- Behavior-based user profiling
- Price range preferences
- Category and brand affinities
- Seasonal pattern recognition
- Purchase frequency analysis

### 🔬 A/B Testing
- Test different recommendation strategies
- Compare algorithm performance
- Optimize conversion rates
- Data-driven decision making

### 📈 Performance Analytics
- Click-through rates
- Conversion tracking
- Revenue attribution
- Algorithm comparison
- User engagement metrics

## Installation

1. **Install the plugin:**
   ```bash
   php cli/plugin.php install ai-recommendations
   ```

2. **Activate the plugin:**
   ```bash
   php cli/plugin.php activate ai-recommendations
   ```

3. **Run migrations:**
   ```bash
   php cli/migrate.php up
   ```

4. **Train initial model:**
   ```bash
   php cli/recommendations.php train --initial
   ```

## Configuration

### Basic Settings

```php
// Algorithm selection
'algorithm' => 'hybrid', // collaborative_filtering, content_based, hybrid, deep_learning

// Enabled recommendation types
'recommendation_types' => [
    'similar_products',
    'frequently_bought',
    'personalized',
    'trending'
],

// Display settings
'max_recommendations' => 8,
'min_confidence_score' => 0.3,
'display_confidence' => false,

// Personalization
'personalization_weight' => 0.7,
'enable_real_time' => true,
'tracking_window' => 30, // days
```

### Advanced Settings

```php
// A/B Testing
'enable_ab_testing' => true,
'ab_test_percentage' => 20,

// Product filtering
'exclude_out_of_stock' => true,
'price_range_factor' => 50, // % range from viewed product
'boost_new_products' => true,
'new_product_days' => 30,

// Cross-selling & Upselling
'enable_cross_selling' => true,
'enable_upselling' => true,

// Performance
'cache_duration' => 60, // minutes
'training_schedule' => 'weekly'
```

## Usage

### API Endpoints

#### Get Product Recommendations
```http
GET /api/v1/recommendations/products/{id}
```

**Parameters:**
- `algorithm`: Algorithm to use (optional)
- `types`: Comma-separated recommendation types
- `limit`: Number of recommendations (default: 8)
- `min_confidence`: Minimum confidence score

**Example:**
```bash
curl "https://yourstore.com/api/v1/recommendations/products/123?types=similar_products,frequently_bought&limit=6"
```

#### Get User Recommendations
```http
GET /api/v1/recommendations/user
```

**Headers:**
- `Authorization: Bearer {token}`

**Parameters:**
- `algorithm`: Algorithm to use
- `limit`: Number of recommendations (default: 12)
- `categories`: Filter by category IDs
- `price_range`: Price range (e.g., "10-50")

#### Get Cart Recommendations
```http
GET /api/v1/recommendations/cart
```

**Parameters:**
- `cart_id`: Cart identifier
- `type`: Recommendation type (frequently_bought, upsell, cross_sell)
- `limit`: Number of recommendations

#### Record Feedback
```http
POST /api/v1/recommendations/feedback
```

**Body:**
```json
{
    "recommendation_id": 123,
    "action": "clicked",
    "context": {
        "position": 2,
        "source": "product_page"
    }
}
```

### Template Integration

#### Product Page Recommendations
```twig
{# This hook is automatically triggered #}
{{ hook('product.display', product) }}
```

#### Manual Display
```twig
{% set recommendations = ai_recommendations(product.id, {
    types: ['similar_products', 'frequently_bought'],
    limit: 6
}) %}

{% if recommendations %}
<div class="product-recommendations">
    <h3>You Might Also Like</h3>
    <div class="recommendation-grid">
        {% for rec in recommendations %}
        <div class="recommendation-item" data-rec-id="{{ rec.id }}">
            <img src="{{ rec.image }}" alt="{{ rec.name }}">
            <h4>{{ rec.name }}</h4>
            <span class="price">${{ rec.price }}</span>
            {% if rec.confidence_score and display_confidence %}
            <small>Confidence: {{ (rec.confidence_score * 100)|round }}%</small>
            {% endif %}
        </div>
        {% endfor %}
    </div>
</div>
{% endif %}
```

### JavaScript Integration

```javascript
// Track recommendation clicks
document.addEventListener('click', function(e) {
    if (e.target.closest('.recommendation-item')) {
        const recId = e.target.closest('.recommendation-item').dataset.recId;
        
        fetch('/api/v1/recommendations/feedback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                recommendation_id: recId,
                action: 'clicked',
                context: {
                    position: getRecommendationPosition(e.target),
                    source: 'product_page'
                }
            })
        });
    }
});

// Real-time recommendations
async function updateRecommendations(productId) {
    const response = await fetch(`/api/v1/recommendations/products/${productId}`);
    const data = await response.json();
    
    if (data.success) {
        renderRecommendations(data.data.recommendations);
    }
}
```

## CLI Commands

### Train Recommendation Model
```bash
# Train with current data
php cli/recommendations.php train

# Initial training with seed data
php cli/recommendations.php train --initial

# Train specific algorithm
php cli/recommendations.php train --algorithm=collaborative_filtering

# Force retrain all models
php cli/recommendations.php train --force
```

### Analyze Performance
```bash
# Generate performance report
php cli/recommendations.php analyze

# Compare algorithms
php cli/recommendations.php analyze --compare

# Analyze specific date range
php cli/recommendations.php analyze --from=2024-01-01 --to=2024-01-31
```

### Cache Management
```bash
# Clear recommendation cache
php cli/recommendations.php clear-cache

# Clear specific cache type
php cli/recommendations.php clear-cache --type=product

# Warm cache for popular products
php cli/recommendations.php warm-cache
```

### Export Data
```bash
# Export recommendations data
php cli/recommendations.php export --format=csv

# Export user behavior data
php cli/recommendations.php export --type=behavior --format=json
```

## Dashboard Widgets

The plugin provides several dashboard widgets:

### Recommendation Performance
- Click-through rates by algorithm
- Conversion rates by recommendation type
- Revenue attribution
- A/B test results

### Popular Recommendations
- Most clicked recommendations
- Best performing products
- Trending recommendation types

### Conversion Rates
- Algorithm performance comparison
- Context-based conversion rates
- Time-based trend analysis

## Machine Learning Details

### Collaborative Filtering
- Uses user-item interaction matrix
- Finds similar users based on behavior
- Recommends items liked by similar users
- Best for users with purchase history

### Content-Based Filtering
- Analyzes product features and attributes
- Finds similar products based on characteristics
- Works well for new users and products
- Uses category, brand, price, and text features

### Hybrid Engine
- Combines collaborative and content-based approaches
- Weights recommendations based on data availability
- Provides balanced recommendations
- Falls back gracefully when data is sparse

### Deep Learning
- Neural network-based embeddings
- Learns complex user-product relationships
- Handles sparse data effectively
- Requires more computational resources

## Performance Optimization

### Caching Strategy
- Recommendation results cached by default
- User preferences cached for session
- Product similarities pre-computed
- Cache invalidation on model updates

### Background Processing
- Model training runs asynchronously
- Similarity calculations in background
- Behavior data processed in batches
- Performance metrics updated regularly

### Database Optimization
- Indexed for fast lookups
- Partitioned by date for large datasets
- Regular cleanup of old data
- Optimized queries for real-time serving

## Monitoring & Analytics

### Key Metrics
- **Click-Through Rate (CTR)**: Percentage of recommendations clicked
- **Conversion Rate**: Percentage of clicks that result in purchases
- **Revenue Per Recommendation**: Average revenue generated
- **Coverage**: Percentage of products that get recommended
- **Diversity**: Variety in recommendation types

### A/B Testing
- Test different algorithms
- Compare recommendation types
- Optimize placement and design
- Measure impact on business metrics

### Real-time Monitoring
- API response times
- Cache hit rates
- Model accuracy metrics
- User engagement levels

## Troubleshooting

### Common Issues

#### Low Recommendation Quality
1. Check training data volume
2. Verify user behavior tracking
3. Adjust confidence thresholds
4. Consider algorithm change

#### Poor Performance
1. Enable caching
2. Optimize database queries
3. Use background processing
4. Scale infrastructure

#### No Recommendations
1. Verify plugin activation
2. Check training data
3. Review configuration
4. Check algorithm compatibility

### Debug Mode
```php
// Enable debug logging
'debug_mode' => true,
'log_level' => 'debug'
```

### Support
- Check logs in `storage/logs/ai-recommendations.log`
- Use debug mode for detailed output
- Monitor performance metrics
- Contact support with specific error messages

## License

This plugin is licensed under the MIT License.