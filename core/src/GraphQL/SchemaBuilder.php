<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

use Shopologic\Core\Container\ContainerInterface;

/**
 * GraphQL schema builder for e-commerce
 */
class SchemaBuilder
{
    private ContainerInterface $container;
    private array $types = [];
    private array $queries = [];
    private array $mutations = [];
    private array $subscriptions = [];
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->registerDefaultTypes();
        $this->registerDefaultQueries();
        $this->registerDefaultMutations();
        $this->registerDefaultSubscriptions();
    }
    
    /**
     * Build the GraphQL schema
     */
    public function build(): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => $this->queries
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => $this->mutations
            ]),
            'subscription' => new ObjectType([
                'name' => 'Subscription',
                'fields' => $this->subscriptions
            ]),
            'types' => $this->types
        ]);
    }
    
    /**
     * Register custom type
     */
    public function registerType(Type $type): void
    {
        $this->types[] = $type;
    }
    
    /**
     * Register query field
     */
    public function registerQuery(string $name, array $config): void
    {
        $this->queries[$name] = $config;
    }
    
    /**
     * Register mutation field
     */
    public function registerMutation(string $name, array $config): void
    {
        $this->mutations[$name] = $config;
    }
    
    /**
     * Register subscription field
     */
    public function registerSubscription(string $name, array $config): void
    {
        $this->subscriptions[$name] = $config;
    }
    
    private function registerDefaultTypes(): void
    {
        // Product type
        $this->types[] = new ObjectType([
            'name' => 'Product',
            'description' => 'A product in the catalog',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'name' => ['type' => Type::nonNull(ScalarType::string())],
                'slug' => ['type' => Type::nonNull(ScalarType::string())],
                'description' => ['type' => ScalarType::string()],
                'shortDescription' => ['type' => ScalarType::string()],
                'sku' => ['type' => Type::nonNull(ScalarType::string())],
                'price' => ['type' => Type::nonNull(ScalarType::float())],
                'comparePrice' => ['type' => ScalarType::float()],
                'cost' => ['type' => ScalarType::float()],
                'stock' => ['type' => Type::nonNull(ScalarType::int())],
                'trackStock' => ['type' => Type::nonNull(ScalarType::boolean())],
                'weight' => ['type' => ScalarType::float()],
                'status' => ['type' => Type::nonNull($this->getProductStatusType())],
                'featured' => ['type' => Type::nonNull(ScalarType::boolean())],
                'virtual' => ['type' => Type::nonNull(ScalarType::boolean())],
                'downloadable' => ['type' => Type::nonNull(ScalarType::boolean())],
                'category' => [
                    'type' => $this->getCategoryType(),
                    'resolve' => function ($product) {
                        return $this->container->get('db')
                            ->table('categories')
                            ->find($product->category_id);
                    }
                ],
                'images' => [
                    'type' => Type::list($this->getProductImageType()),
                    'resolve' => function ($product) {
                        return $this->container->get('db')
                            ->table('product_images')
                            ->where('product_id', $product->id)
                            ->orderBy('position')
                            ->get();
                    }
                ],
                'attributes' => [
                    'type' => Type::list($this->getProductAttributeType()),
                    'resolve' => function ($product) {
                        return $this->container->get('db')
                            ->table('product_attributes')
                            ->where('product_id', $product->id)
                            ->get();
                    }
                ],
                'variants' => [
                    'type' => Type::list($this->getProductVariantType()),
                    'resolve' => function ($product) {
                        return $this->container->get('db')
                            ->table('product_variants')
                            ->where('product_id', $product->id)
                            ->get();
                    }
                ],
                'reviews' => [
                    'type' => Type::list($this->getReviewType()),
                    'args' => [
                        'limit' => ['type' => ScalarType::int(), 'defaultValue' => 10],
                        'offset' => ['type' => ScalarType::int(), 'defaultValue' => 0]
                    ],
                    'resolve' => function ($product, $args) {
                        return $this->container->get('db')
                            ->table('reviews')
                            ->where('product_id', $product->id)
                            ->where('approved', true)
                            ->limit($args['limit'])
                            ->offset($args['offset'])
                            ->orderBy('created_at', 'desc')
                            ->get();
                    }
                ],
                'averageRating' => [
                    'type' => ScalarType::float(),
                    'resolve' => function ($product) {
                        return $this->container->get('db')
                            ->table('reviews')
                            ->where('product_id', $product->id)
                            ->where('approved', true)
                            ->avg('rating');
                    }
                ],
                'reviewCount' => [
                    'type' => ScalarType::int(),
                    'resolve' => function ($product) {
                        return $this->container->get('db')
                            ->table('reviews')
                            ->where('product_id', $product->id)
                            ->where('approved', true)
                            ->count();
                    }
                ],
                'tags' => ['type' => Type::list(ScalarType::string())],
                'metaTitle' => ['type' => ScalarType::string()],
                'metaDescription' => ['type' => ScalarType::string()],
                'metaKeywords' => ['type' => ScalarType::string()],
                'createdAt' => ['type' => Type::nonNull(ScalarType::string())],
                'updatedAt' => ['type' => Type::nonNull(ScalarType::string())]
            ]
        ]);
        
        // Category type
        $categoryType = new ObjectType([
            'name' => 'Category',
            'description' => 'A product category',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'name' => ['type' => Type::nonNull(ScalarType::string())],
                'slug' => ['type' => Type::nonNull(ScalarType::string())],
                'description' => ['type' => ScalarType::string()],
                'image' => ['type' => ScalarType::string()],
                'parentId' => ['type' => ScalarType::id()],
                'parent' => [
                    'type' => $this->getCategoryType(),
                    'resolve' => function ($category) {
                        if (!$category->parent_id) {
                            return null;
                        }
                        return $this->container->get('db')
                            ->table('categories')
                            ->find($category->parent_id);
                    }
                ],
                'children' => [
                    'type' => Type::list($this->getCategoryType()),
                    'resolve' => function ($category) {
                        return $this->container->get('db')
                            ->table('categories')
                            ->where('parent_id', $category->id)
                            ->get();
                    }
                ],
                'products' => [
                    'type' => Type::list($this->getProductType()),
                    'args' => [
                        'limit' => ['type' => ScalarType::int(), 'defaultValue' => 20],
                        'offset' => ['type' => ScalarType::int(), 'defaultValue' => 0]
                    ],
                    'resolve' => function ($category, $args) {
                        return $this->container->get('db')
                            ->table('products')
                            ->where('category_id', $category->id)
                            ->where('status', 'active')
                            ->limit($args['limit'])
                            ->offset($args['offset'])
                            ->get();
                    }
                ],
                'productCount' => [
                    'type' => ScalarType::int(),
                    'resolve' => function ($category) {
                        return $this->container->get('db')
                            ->table('products')
                            ->where('category_id', $category->id)
                            ->where('status', 'active')
                            ->count();
                    }
                ],
                'position' => ['type' => ScalarType::int()],
                'status' => ['type' => Type::nonNull(ScalarType::string())],
                'metaTitle' => ['type' => ScalarType::string()],
                'metaDescription' => ['type' => ScalarType::string()],
                'createdAt' => ['type' => Type::nonNull(ScalarType::string())],
                'updatedAt' => ['type' => Type::nonNull(ScalarType::string())]
            ]
        ]);
        $this->types[] = $categoryType;
        
        // Order type
        $this->types[] = new ObjectType([
            'name' => 'Order',
            'description' => 'A customer order',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'orderNumber' => ['type' => Type::nonNull(ScalarType::string())],
                'status' => ['type' => Type::nonNull($this->getOrderStatusType())],
                'customer' => [
                    'type' => $this->getCustomerType(),
                    'resolve' => function ($order) {
                        return $this->container->get('db')
                            ->table('users')
                            ->find($order->user_id);
                    }
                ],
                'items' => [
                    'type' => Type::list($this->getOrderItemType()),
                    'resolve' => function ($order) {
                        return $this->container->get('db')
                            ->table('order_items')
                            ->where('order_id', $order->id)
                            ->get();
                    }
                ],
                'subtotal' => ['type' => Type::nonNull(ScalarType::float())],
                'taxTotal' => ['type' => Type::nonNull(ScalarType::float())],
                'shippingTotal' => ['type' => Type::nonNull(ScalarType::float())],
                'discountTotal' => ['type' => Type::nonNull(ScalarType::float())],
                'total' => ['type' => Type::nonNull(ScalarType::float())],
                'currency' => ['type' => Type::nonNull(ScalarType::string())],
                'paymentMethod' => ['type' => ScalarType::string()],
                'paymentStatus' => ['type' => Type::nonNull(ScalarType::string())],
                'shippingMethod' => ['type' => ScalarType::string()],
                'shippingStatus' => ['type' => Type::nonNull(ScalarType::string())],
                'shippingAddress' => ['type' => $this->getAddressType()],
                'billingAddress' => ['type' => $this->getAddressType()],
                'notes' => ['type' => ScalarType::string()],
                'couponCode' => ['type' => ScalarType::string()],
                'trackingNumber' => ['type' => ScalarType::string()],
                'createdAt' => ['type' => Type::nonNull(ScalarType::string())],
                'updatedAt' => ['type' => Type::nonNull(ScalarType::string())]
            ]
        ]);
        
        // Customer type
        $this->types[] = new ObjectType([
            'name' => 'Customer',
            'description' => 'A customer user',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'email' => ['type' => Type::nonNull(ScalarType::string())],
                'name' => ['type' => Type::nonNull(ScalarType::string())],
                'firstName' => ['type' => ScalarType::string()],
                'lastName' => ['type' => ScalarType::string()],
                'phone' => ['type' => ScalarType::string()],
                'dateOfBirth' => ['type' => ScalarType::string()],
                'gender' => ['type' => ScalarType::string()],
                'status' => ['type' => Type::nonNull(ScalarType::string())],
                'emailVerified' => ['type' => Type::nonNull(ScalarType::boolean())],
                'acceptsMarketing' => ['type' => Type::nonNull(ScalarType::boolean())],
                'addresses' => [
                    'type' => Type::list($this->getAddressType()),
                    'resolve' => function ($customer) {
                        return $this->container->get('db')
                            ->table('addresses')
                            ->where('user_id', $customer->id)
                            ->get();
                    }
                ],
                'orders' => [
                    'type' => Type::list($this->getOrderType()),
                    'args' => [
                        'limit' => ['type' => ScalarType::int(), 'defaultValue' => 10],
                        'offset' => ['type' => ScalarType::int(), 'defaultValue' => 0]
                    ],
                    'resolve' => function ($customer, $args) {
                        return $this->container->get('db')
                            ->table('orders')
                            ->where('user_id', $customer->id)
                            ->limit($args['limit'])
                            ->offset($args['offset'])
                            ->orderBy('created_at', 'desc')
                            ->get();
                    }
                ],
                'orderCount' => [
                    'type' => ScalarType::int(),
                    'resolve' => function ($customer) {
                        return $this->container->get('db')
                            ->table('orders')
                            ->where('user_id', $customer->id)
                            ->count();
                    }
                ],
                'totalSpent' => [
                    'type' => ScalarType::float(),
                    'resolve' => function ($customer) {
                        return $this->container->get('db')
                            ->table('orders')
                            ->where('user_id', $customer->id)
                            ->where('status', '!=', 'cancelled')
                            ->sum('total');
                    }
                ],
                'tags' => ['type' => Type::list(ScalarType::string())],
                'notes' => ['type' => ScalarType::string()],
                'createdAt' => ['type' => Type::nonNull(ScalarType::string())],
                'updatedAt' => ['type' => Type::nonNull(ScalarType::string())],
                'lastOrderAt' => ['type' => ScalarType::string()]
            ]
        ]);
        
        // Cart type
        $this->types[] = new ObjectType([
            'name' => 'Cart',
            'description' => 'Shopping cart',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'items' => [
                    'type' => Type::list($this->getCartItemType()),
                    'resolve' => function ($cart) {
                        return $this->container->get('db')
                            ->table('cart_items')
                            ->where('cart_id', $cart->id)
                            ->get();
                    }
                ],
                'itemCount' => [
                    'type' => ScalarType::int(),
                    'resolve' => function ($cart) {
                        return $this->container->get('db')
                            ->table('cart_items')
                            ->where('cart_id', $cart->id)
                            ->sum('quantity');
                    }
                ],
                'subtotal' => ['type' => Type::nonNull(ScalarType::float())],
                'taxTotal' => ['type' => Type::nonNull(ScalarType::float())],
                'shippingTotal' => ['type' => Type::nonNull(ScalarType::float())],
                'discountTotal' => ['type' => Type::nonNull(ScalarType::float())],
                'total' => ['type' => Type::nonNull(ScalarType::float())],
                'currency' => ['type' => Type::nonNull(ScalarType::string())],
                'couponCode' => ['type' => ScalarType::string()],
                'appliedCoupon' => [
                    'type' => $this->getCouponType(),
                    'resolve' => function ($cart) {
                        if (!$cart->coupon_code) {
                            return null;
                        }
                        return $this->container->get('db')
                            ->table('coupons')
                            ->where('code', $cart->coupon_code)
                            ->first();
                    }
                ],
                'shippingAddress' => ['type' => $this->getAddressType()],
                'billingAddress' => ['type' => $this->getAddressType()],
                'selectedShippingMethod' => ['type' => ScalarType::string()],
                'availableShippingMethods' => [
                    'type' => Type::list($this->getShippingMethodType()),
                    'resolve' => function ($cart) {
                        // Calculate available shipping methods based on cart contents and address
                        return $this->container->get('shipping')->getAvailableMethods($cart);
                    }
                ],
                'createdAt' => ['type' => Type::nonNull(ScalarType::string())],
                'updatedAt' => ['type' => Type::nonNull(ScalarType::string())],
                'expiresAt' => ['type' => ScalarType::string()]
            ]
        ]);
        
        // Additional types
        $this->registerProductImageType();
        $this->registerProductAttributeType();
        $this->registerProductVariantType();
        $this->registerReviewType();
        $this->registerAddressType();
        $this->registerOrderItemType();
        $this->registerCartItemType();
        $this->registerCouponType();
        $this->registerShippingMethodType();
        $this->registerPaymentMethodType();
        $this->registerPageInfoType();
        $this->registerFilterTypes();
        $this->registerSortTypes();
        $this->registerInputTypes();
        $this->registerEnumTypes();
    }
    
    private function registerDefaultQueries(): void
    {
        // Product queries
        $this->queries['product'] = [
            'type' => $this->getProductType(),
            'args' => [
                'id' => ['type' => ScalarType::id()],
                'slug' => ['type' => ScalarType::string()],
                'sku' => ['type' => ScalarType::string()]
            ],
            'resolve' => function ($root, $args) {
                $query = $this->container->get('db')->table('products');
                
                if (isset($args['id'])) {
                    return $query->find($args['id']);
                } elseif (isset($args['slug'])) {
                    return $query->where('slug', $args['slug'])->first();
                } elseif (isset($args['sku'])) {
                    return $query->where('sku', $args['sku'])->first();
                }
                
                throw new \InvalidArgumentException('Product identifier required');
            }
        ];
        
        $this->queries['products'] = [
            'type' => $this->getProductConnectionType(),
            'args' => [
                'first' => ['type' => ScalarType::int(), 'defaultValue' => 20],
                'after' => ['type' => ScalarType::string()],
                'filter' => ['type' => $this->getProductFilterType()],
                'sort' => ['type' => $this->getProductSortType()]
            ],
            'resolve' => function ($root, $args) {
                return $this->container->get('graphql.resolvers.product')
                    ->resolveProducts($args);
            }
        ];
        
        // Category queries
        $this->queries['category'] = [
            'type' => $this->getCategoryType(),
            'args' => [
                'id' => ['type' => ScalarType::id()],
                'slug' => ['type' => ScalarType::string()]
            ],
            'resolve' => function ($root, $args) {
                $query = $this->container->get('db')->table('categories');
                
                if (isset($args['id'])) {
                    return $query->find($args['id']);
                } elseif (isset($args['slug'])) {
                    return $query->where('slug', $args['slug'])->first();
                }
                
                throw new \InvalidArgumentException('Category identifier required');
            }
        ];
        
        $this->queries['categories'] = [
            'type' => Type::list($this->getCategoryType()),
            'args' => [
                'parentId' => ['type' => ScalarType::id()],
                'onlyRoot' => ['type' => ScalarType::boolean(), 'defaultValue' => false]
            ],
            'resolve' => function ($root, $args) {
                $query = $this->container->get('db')->table('categories')
                    ->where('status', 'active')
                    ->orderBy('position');
                
                if ($args['onlyRoot']) {
                    $query->whereNull('parent_id');
                } elseif (isset($args['parentId'])) {
                    $query->where('parent_id', $args['parentId']);
                }
                
                return $query->get();
            }
        ];
        
        // Order queries
        $this->queries['order'] = [
            'type' => $this->getOrderType(),
            'args' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'resolve' => function ($root, $args, $context) {
                $order = $this->container->get('db')->table('orders')->find($args['id']);
                
                // Check permission
                if ($order && $order->user_id !== $context->get('user')->id) {
                    throw new \Exception('Access denied');
                }
                
                return $order;
            }
        ];
        
        $this->queries['orders'] = [
            'type' => $this->getOrderConnectionType(),
            'args' => [
                'first' => ['type' => ScalarType::int(), 'defaultValue' => 10],
                'after' => ['type' => ScalarType::string()],
                'filter' => ['type' => $this->getOrderFilterType()],
                'sort' => ['type' => $this->getOrderSortType()]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.order')
                    ->resolveOrders($args, $context);
            }
        ];
        
        // Customer queries
        $this->queries['me'] = [
            'type' => $this->getCustomerType(),
            'resolve' => function ($root, $args, $context) {
                return $context->get('user');
            }
        ];
        
        $this->queries['customer'] = [
            'type' => $this->getCustomerType(),
            'args' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'resolve' => function ($root, $args, $context) {
                // Check admin permission
                if (!$context->get('user')->hasPermission('admin.customers.view')) {
                    throw new \Exception('Access denied');
                }
                
                return $this->container->get('db')->table('users')->find($args['id']);
            }
        ];
        
        // Cart queries
        $this->queries['cart'] = [
            'type' => $this->getCartType(),
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('cart')->getCart($context->get('request'));
            }
        ];
        
        // Search queries
        $this->queries['search'] = [
            'type' => $this->getSearchResultType(),
            'args' => [
                'query' => ['type' => Type::nonNull(ScalarType::string())],
                'type' => ['type' => $this->getSearchTypeEnum()],
                'limit' => ['type' => ScalarType::int(), 'defaultValue' => 20]
            ],
            'resolve' => function ($root, $args) {
                return $this->container->get('search')->search(
                    $args['query'],
                    ['index' => $args['type'] ?? null, 'size' => $args['limit']]
                );
            }
        ];
        
        // Configuration queries
        $this->queries['shop'] = [
            'type' => $this->getShopType(),
            'resolve' => function () {
                return $this->container->get('config')['shop'];
            }
        ];
        
        $this->queries['currencies'] = [
            'type' => Type::list($this->getCurrencyType()),
            'resolve' => function () {
                return $this->container->get('currency')->getAvailableCurrencies();
            }
        ];
        
        $this->queries['languages'] = [
            'type' => Type::list($this->getLanguageType()),
            'resolve' => function () {
                return $this->container->get('translator')->getAvailableLanguages();
            }
        ];
    }
    
    private function registerDefaultMutations(): void
    {
        // Authentication mutations
        $this->mutations['login'] = [
            'type' => $this->getAuthPayloadType(),
            'args' => [
                'email' => ['type' => Type::nonNull(ScalarType::string())],
                'password' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args) {
                return $this->container->get('graphql.resolvers.auth')->login($args);
            }
        ];
        
        $this->mutations['register'] = [
            'type' => $this->getAuthPayloadType(),
            'args' => [
                'input' => ['type' => Type::nonNull($this->getRegisterInputType())]
            ],
            'resolve' => function ($root, $args) {
                return $this->container->get('graphql.resolvers.auth')->register($args['input']);
            }
        ];
        
        $this->mutations['logout'] = [
            'type' => ScalarType::boolean(),
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.auth')->logout($context);
            }
        ];
        
        // Cart mutations
        $this->mutations['addToCart'] = [
            'type' => $this->getCartType(),
            'args' => [
                'productId' => ['type' => Type::nonNull(ScalarType::id())],
                'quantity' => ['type' => Type::nonNull(ScalarType::int())],
                'variantId' => ['type' => ScalarType::id()]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.cart')
                    ->addToCart($args, $context);
            }
        ];
        
        $this->mutations['updateCartItem'] = [
            'type' => $this->getCartType(),
            'args' => [
                'itemId' => ['type' => Type::nonNull(ScalarType::id())],
                'quantity' => ['type' => Type::nonNull(ScalarType::int())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.cart')
                    ->updateCartItem($args, $context);
            }
        ];
        
        $this->mutations['removeFromCart'] = [
            'type' => $this->getCartType(),
            'args' => [
                'itemId' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.cart')
                    ->removeFromCart($args, $context);
            }
        ];
        
        $this->mutations['applyCoupon'] = [
            'type' => $this->getCartType(),
            'args' => [
                'code' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.cart')
                    ->applyCoupon($args, $context);
            }
        ];
        
        $this->mutations['removeCoupon'] = [
            'type' => $this->getCartType(),
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.cart')
                    ->removeCoupon($context);
            }
        ];
        
        // Checkout mutations
        $this->mutations['setShippingAddress'] = [
            'type' => $this->getCartType(),
            'args' => [
                'address' => ['type' => Type::nonNull($this->getAddressInputType())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.checkout')
                    ->setShippingAddress($args['address'], $context);
            }
        ];
        
        $this->mutations['setBillingAddress'] = [
            'type' => $this->getCartType(),
            'args' => [
                'address' => ['type' => Type::nonNull($this->getAddressInputType())],
                'sameAsShipping' => ['type' => ScalarType::boolean()]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.checkout')
                    ->setBillingAddress($args['address'], $args['sameAsShipping'] ?? false, $context);
            }
        ];
        
        $this->mutations['setShippingMethod'] = [
            'type' => $this->getCartType(),
            'args' => [
                'methodId' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.checkout')
                    ->setShippingMethod($args['methodId'], $context);
            }
        ];
        
        $this->mutations['placeOrder'] = [
            'type' => $this->getOrderType(),
            'args' => [
                'paymentMethodId' => ['type' => Type::nonNull(ScalarType::string())],
                'paymentData' => ['type' => $this->getPaymentDataInputType()]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.checkout')
                    ->placeOrder($args, $context);
            }
        ];
        
        // Customer mutations
        $this->mutations['updateProfile'] = [
            'type' => $this->getCustomerType(),
            'args' => [
                'input' => ['type' => Type::nonNull($this->getUpdateProfileInputType())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.customer')
                    ->updateProfile($args['input'], $context);
            }
        ];
        
        $this->mutations['changePassword'] = [
            'type' => ScalarType::boolean(),
            'args' => [
                'currentPassword' => ['type' => Type::nonNull(ScalarType::string())],
                'newPassword' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.customer')
                    ->changePassword($args, $context);
            }
        ];
        
        $this->mutations['addAddress'] = [
            'type' => $this->getAddressType(),
            'args' => [
                'address' => ['type' => Type::nonNull($this->getAddressInputType())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.customer')
                    ->addAddress($args['address'], $context);
            }
        ];
        
        $this->mutations['updateAddress'] = [
            'type' => $this->getAddressType(),
            'args' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'address' => ['type' => Type::nonNull($this->getAddressInputType())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.customer')
                    ->updateAddress($args['id'], $args['address'], $context);
            }
        ];
        
        $this->mutations['deleteAddress'] = [
            'type' => ScalarType::boolean(),
            'args' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.customer')
                    ->deleteAddress($args['id'], $context);
            }
        ];
        
        // Review mutations
        $this->mutations['createReview'] = [
            'type' => $this->getReviewType(),
            'args' => [
                'productId' => ['type' => Type::nonNull(ScalarType::id())],
                'rating' => ['type' => Type::nonNull(ScalarType::int())],
                'title' => ['type' => ScalarType::string()],
                'comment' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.review')
                    ->createReview($args, $context);
            }
        ];
        
        // Wishlist mutations
        $this->mutations['addToWishlist'] = [
            'type' => $this->getWishlistType(),
            'args' => [
                'productId' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.wishlist')
                    ->addToWishlist($args['productId'], $context);
            }
        ];
        
        $this->mutations['removeFromWishlist'] = [
            'type' => $this->getWishlistType(),
            'args' => [
                'productId' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'resolve' => function ($root, $args, $context) {
                return $this->container->get('graphql.resolvers.wishlist')
                    ->removeFromWishlist($args['productId'], $context);
            }
        ];
        
        // Newsletter mutations
        $this->mutations['subscribeNewsletter'] = [
            'type' => ScalarType::boolean(),
            'args' => [
                'email' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args) {
                return $this->container->get('newsletter')->subscribe($args['email']);
            }
        ];
        
        $this->mutations['unsubscribeNewsletter'] = [
            'type' => ScalarType::boolean(),
            'args' => [
                'token' => ['type' => Type::nonNull(ScalarType::string())]
            ],
            'resolve' => function ($root, $args) {
                return $this->container->get('newsletter')->unsubscribe($args['token']);
            }
        ];
    }
    
    private function registerDefaultSubscriptions(): void
    {
        // Order status updates
        $this->subscriptions['orderStatusChanged'] = [
            'type' => $this->getOrderStatusUpdateType(),
            'args' => [
                'orderId' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'subscribe' => function ($root, $args, $context) {
                return $this->container->get('graphql.subscriptions')
                    ->subscribeToOrderStatus($args['orderId'], $context);
            }
        ];
        
        // Product updates
        $this->subscriptions['productUpdated'] = [
            'type' => $this->getProductType(),
            'args' => [
                'productId' => ['type' => Type::nonNull(ScalarType::id())]
            ],
            'subscribe' => function ($root, $args) {
                return $this->container->get('graphql.subscriptions')
                    ->subscribeToProduct($args['productId']);
            }
        ];
        
        // Stock updates
        $this->subscriptions['stockLevelChanged'] = [
            'type' => $this->getStockUpdateType(),
            'args' => [
                'productIds' => ['type' => Type::list(ScalarType::id())]
            ],
            'subscribe' => function ($root, $args) {
                return $this->container->get('graphql.subscriptions')
                    ->subscribeToStock($args['productIds'] ?? []);
            }
        ];
        
        // Price updates
        $this->subscriptions['priceChanged'] = [
            'type' => $this->getPriceUpdateType(),
            'args' => [
                'productIds' => ['type' => Type::list(ScalarType::id())]
            ],
            'subscribe' => function ($root, $args) {
                return $this->container->get('graphql.subscriptions')
                    ->subscribeToPrices($args['productIds'] ?? []);
            }
        ];
        
        // Cart reminders
        $this->subscriptions['cartReminder'] = [
            'type' => $this->getCartReminderType(),
            'subscribe' => function ($root, $args, $context) {
                return $this->container->get('graphql.subscriptions')
                    ->subscribeToCartReminders($context);
            }
        ];
    }
    
    // Helper methods to get types (to avoid circular references)
    private function getProductType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Product') {
                return $type;
            }
        }
        throw new \RuntimeException('Product type not found');
    }
    
    private function getCategoryType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Category') {
                return $type;
            }
        }
        throw new \RuntimeException('Category type not found');
    }
    
    private function getOrderType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Order') {
                return $type;
            }
        }
        throw new \RuntimeException('Order type not found');
    }
    
    private function getCustomerType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Customer') {
                return $type;
            }
        }
        throw new \RuntimeException('Customer type not found');
    }
    
    private function getCartType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Cart') {
                return $type;
            }
        }
        throw new \RuntimeException('Cart type not found');
    }
    
    // Additional type registration methods...
    private function registerProductImageType(): void
    {
        $this->types[] = new ObjectType([
            'name' => 'ProductImage',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'url' => ['type' => Type::nonNull(ScalarType::string())],
                'alt' => ['type' => ScalarType::string()],
                'position' => ['type' => ScalarType::int()],
                'width' => ['type' => ScalarType::int()],
                'height' => ['type' => ScalarType::int()]
            ]
        ]);
    }
    
    private function registerProductAttributeType(): void
    {
        $this->types[] = new ObjectType([
            'name' => 'ProductAttribute',
            'fields' => [
                'name' => ['type' => Type::nonNull(ScalarType::string())],
                'value' => ['type' => Type::nonNull(ScalarType::string())]
            ]
        ]);
    }
    
    private function registerProductVariantType(): void
    {
        $this->types[] = new ObjectType([
            'name' => 'ProductVariant',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'sku' => ['type' => Type::nonNull(ScalarType::string())],
                'name' => ['type' => Type::nonNull(ScalarType::string())],
                'price' => ['type' => Type::nonNull(ScalarType::float())],
                'comparePrice' => ['type' => ScalarType::float()],
                'stock' => ['type' => Type::nonNull(ScalarType::int())],
                'weight' => ['type' => ScalarType::float()],
                'attributes' => ['type' => Type::list($this->getProductAttributeType())],
                'image' => ['type' => $this->getProductImageType()],
                'available' => ['type' => Type::nonNull(ScalarType::boolean())]
            ]
        ]);
    }
    
    private function registerReviewType(): void
    {
        $this->types[] = new ObjectType([
            'name' => 'Review',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'rating' => ['type' => Type::nonNull(ScalarType::int())],
                'title' => ['type' => ScalarType::string()],
                'comment' => ['type' => Type::nonNull(ScalarType::string())],
                'author' => ['type' => Type::nonNull($this->getCustomerType())],
                'verified' => ['type' => Type::nonNull(ScalarType::boolean())],
                'helpful' => ['type' => ScalarType::int()],
                'createdAt' => ['type' => Type::nonNull(ScalarType::string())]
            ]
        ]);
    }
    
    private function registerAddressType(): void
    {
        $this->types[] = new ObjectType([
            'name' => 'Address',
            'fields' => [
                'id' => ['type' => Type::nonNull(ScalarType::id())],
                'firstName' => ['type' => Type::nonNull(ScalarType::string())],
                'lastName' => ['type' => Type::nonNull(ScalarType::string())],
                'company' => ['type' => ScalarType::string()],
                'address1' => ['type' => Type::nonNull(ScalarType::string())],
                'address2' => ['type' => ScalarType::string()],
                'city' => ['type' => Type::nonNull(ScalarType::string())],
                'state' => ['type' => ScalarType::string()],
                'postalCode' => ['type' => Type::nonNull(ScalarType::string())],
                'country' => ['type' => Type::nonNull(ScalarType::string())],
                'phone' => ['type' => ScalarType::string()],
                'isDefault' => ['type' => Type::nonNull(ScalarType::boolean())]
            ]
        ]);
    }
    
    private function getProductImageType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'ProductImage') {
                return $type;
            }
        }
        throw new \RuntimeException('ProductImage type not found');
    }
    
    private function getProductAttributeType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'ProductAttribute') {
                return $type;
            }
        }
        throw new \RuntimeException('ProductAttribute type not found');
    }
    
    private function getProductVariantType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'ProductVariant') {
                return $type;
            }
        }
        throw new \RuntimeException('ProductVariant type not found');
    }
    
    private function getReviewType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Review') {
                return $type;
            }
        }
        throw new \RuntimeException('Review type not found');
    }
    
    private function getAddressType(): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === 'Address') {
                return $type;
            }
        }
        throw new \RuntimeException('Address type not found');
    }
    
    // Enum types
    private function getProductStatusType(): Type
    {
        return new EnumType([
            'name' => 'ProductStatus',
            'values' => [
                'ACTIVE' => ['value' => 'active'],
                'INACTIVE' => ['value' => 'inactive'],
                'DRAFT' => ['value' => 'draft']
            ]
        ]);
    }
    
    private function getOrderStatusType(): Type
    {
        return new EnumType([
            'name' => 'OrderStatus',
            'values' => [
                'PENDING' => ['value' => 'pending'],
                'PROCESSING' => ['value' => 'processing'],
                'SHIPPED' => ['value' => 'shipped'],
                'DELIVERED' => ['value' => 'delivered'],
                'CANCELLED' => ['value' => 'cancelled'],
                'REFUNDED' => ['value' => 'refunded']
            ]
        ]);
    }
    
    // Additional helper methods would continue...
    private function registerOrderItemType(): void {}
    private function registerCartItemType(): void {}
    private function registerCouponType(): void {}
    private function registerShippingMethodType(): void {}
    private function registerPaymentMethodType(): void {}
    private function registerPageInfoType(): void {}
    private function registerFilterTypes(): void {}
    private function registerSortTypes(): void {}
    private function registerInputTypes(): void {}
    private function registerEnumTypes(): void {}
    private function getOrderItemType(): Type { return new ObjectType(['name' => 'OrderItem', 'fields' => []]); }
    private function getCartItemType(): Type { return new ObjectType(['name' => 'CartItem', 'fields' => []]); }
    private function getCouponType(): Type { return new ObjectType(['name' => 'Coupon', 'fields' => []]); }
    private function getShippingMethodType(): Type { return new ObjectType(['name' => 'ShippingMethod', 'fields' => []]); }
    private function getPaymentMethodType(): Type { return new ObjectType(['name' => 'PaymentMethod', 'fields' => []]); }
    private function getSearchResultType(): Type { return new ObjectType(['name' => 'SearchResult', 'fields' => []]); }
    private function getSearchTypeEnum(): Type { return new EnumType(['name' => 'SearchType', 'values' => []]); }
    private function getShopType(): Type { return new ObjectType(['name' => 'Shop', 'fields' => []]); }
    private function getCurrencyType(): Type { return new ObjectType(['name' => 'Currency', 'fields' => []]); }
    private function getLanguageType(): Type { return new ObjectType(['name' => 'Language', 'fields' => []]); }
    private function getAuthPayloadType(): Type { return new ObjectType(['name' => 'AuthPayload', 'fields' => []]); }
    private function getRegisterInputType(): Type { return new InputObjectType(['name' => 'RegisterInput', 'fields' => []]); }
    private function getAddressInputType(): Type { return new InputObjectType(['name' => 'AddressInput', 'fields' => []]); }
    private function getPaymentDataInputType(): Type { return new InputObjectType(['name' => 'PaymentDataInput', 'fields' => []]); }
    private function getUpdateProfileInputType(): Type { return new InputObjectType(['name' => 'UpdateProfileInput', 'fields' => []]); }
    private function getWishlistType(): Type { return new ObjectType(['name' => 'Wishlist', 'fields' => []]); }
    private function getProductConnectionType(): Type { return new ObjectType(['name' => 'ProductConnection', 'fields' => []]); }
    private function getProductFilterType(): Type { return new InputObjectType(['name' => 'ProductFilter', 'fields' => []]); }
    private function getProductSortType(): Type { return new EnumType(['name' => 'ProductSort', 'values' => []]); }
    private function getOrderConnectionType(): Type { return new ObjectType(['name' => 'OrderConnection', 'fields' => []]); }
    private function getOrderFilterType(): Type { return new InputObjectType(['name' => 'OrderFilter', 'fields' => []]); }
    private function getOrderSortType(): Type { return new EnumType(['name' => 'OrderSort', 'values' => []]); }
    private function getOrderStatusUpdateType(): Type { return new ObjectType(['name' => 'OrderStatusUpdate', 'fields' => []]); }
    private function getStockUpdateType(): Type { return new ObjectType(['name' => 'StockUpdate', 'fields' => []]); }
    private function getPriceUpdateType(): Type { return new ObjectType(['name' => 'PriceUpdate', 'fields' => []]); }
    private function getCartReminderType(): Type { return new ObjectType(['name' => 'CartReminder', 'fields' => []]); }
}