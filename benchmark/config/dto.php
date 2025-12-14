<?php

declare(strict_types=1);

/**
 * DTO definitions for benchmarking php-collective/dto.
 */
return [
    'User' => [
        'fields' => [
            'id' => ['type' => 'int', 'required' => true],
            'name' => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true],
            'phone' => 'string',
            'active' => ['type' => 'bool', 'defaultValue' => true],
            'roles' => 'string[]',
        ],
    ],
    'Address' => [
        'fields' => [
            'street' => ['type' => 'string', 'required' => true],
            'city' => ['type' => 'string', 'required' => true],
            'country' => ['type' => 'string', 'required' => true],
            'zipCode' => 'string',
        ],
    ],
    'OrderItem' => [
        'fields' => [
            'productId' => ['type' => 'int', 'required' => true],
            'name' => ['type' => 'string', 'required' => true],
            'quantity' => ['type' => 'int', 'required' => true],
            'price' => ['type' => 'float', 'required' => true],
        ],
    ],
    'Order' => [
        'fields' => [
            'id' => ['type' => 'int', 'required' => true],
            'customer' => ['type' => 'User', 'required' => true],
            'shippingAddress' => ['type' => 'Address', 'required' => true],
            'items' => ['type' => 'OrderItem[]', 'collection' => true, 'singular' => 'item'],
            'total' => ['type' => 'float', 'required' => true],
            'status' => ['type' => 'string', 'required' => true],
            'createdAt' => '\DateTimeImmutable',
        ],
    ],
    'ImmutableUser' => [
        'immutable' => true,
        'fields' => [
            'id' => ['type' => 'int', 'required' => true],
            'name' => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true],
            'phone' => 'string',
            'active' => ['type' => 'bool', 'defaultValue' => true],
        ],
    ],
];
