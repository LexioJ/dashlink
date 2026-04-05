<?php
return [
    'routes' => [
        // Public (Dashboard) Routes
        ['name' => 'link#index', 'url' => '/api/v1/links', 'verb' => 'GET'],
        ['name' => 'link#getIcon', 'url' => '/api/v1/links/{id}/icon', 'verb' => 'GET'],

        // Admin Link Routes
        ['name' => 'link#adminIndex', 'url' => '/api/v1/admin/links', 'verb' => 'GET'],
        ['name' => 'link#create', 'url' => '/api/v1/admin/links', 'verb' => 'POST'],
        ['name' => 'link#exportLinks', 'url' => '/api/v1/admin/links/export', 'verb' => 'GET'],
        ['name' => 'link#importLinks', 'url' => '/api/v1/admin/links/import', 'verb' => 'POST'],
        ['name' => 'link#updateOrder', 'url' => '/api/v1/admin/links/order', 'verb' => 'PUT'],
        ['name' => 'link#update', 'url' => '/api/v1/admin/links/{id}', 'verb' => 'PUT'],
        ['name' => 'link#delete', 'url' => '/api/v1/admin/links/{id}', 'verb' => 'DELETE'],
        ['name' => 'link#uploadIcon', 'url' => '/api/v1/admin/links/{id}/icon', 'verb' => 'POST'],
        ['name' => 'link#deleteIcon', 'url' => '/api/v1/admin/links/{id}/icon', 'verb' => 'DELETE'],
        ['name' => 'link#getGroups', 'url' => '/api/v1/admin/groups', 'verb' => 'GET'],

        // User Link Routes (user-private links)
        ['name' => 'userLink#index', 'url' => '/api/v1/user/links', 'verb' => 'GET'],
        ['name' => 'userLink#create', 'url' => '/api/v1/user/links', 'verb' => 'POST'],
        ['name' => 'userLink#exportLinks', 'url' => '/api/v1/user/links/export', 'verb' => 'GET'],
        ['name' => 'userLink#importLinks', 'url' => '/api/v1/user/links/import', 'verb' => 'POST'],
        ['name' => 'userLink#updateOrder', 'url' => '/api/v1/user/links/order', 'verb' => 'PUT'],
        ['name' => 'userLink#update', 'url' => '/api/v1/user/links/{id}', 'verb' => 'PUT'],
        ['name' => 'userLink#delete', 'url' => '/api/v1/user/links/{id}', 'verb' => 'DELETE'],
        ['name' => 'userLink#uploadIcon', 'url' => '/api/v1/user/links/{id}/icon', 'verb' => 'POST'],
        ['name' => 'userLink#deleteIcon', 'url' => '/api/v1/user/links/{id}/icon', 'verb' => 'DELETE'],
        ['name' => 'userLink#getIcon', 'url' => '/api/v1/user/links/{id}/icon', 'verb' => 'GET'],

        // Settings Routes
        ['name' => 'settings#index', 'url' => '/api/v1/admin/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/v1/admin/settings', 'verb' => 'PUT'],

        // Admin Page
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],
    ]
];
