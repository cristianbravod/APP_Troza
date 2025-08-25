<?php
protected $routeMiddleware = [
    // ... otros middleware
    'jwt.auth' => \App\Http\Middleware\JWTAuthMiddleware::class,
    'module.permission' => \App\Http\Middleware\CheckModulePermission::class,
];

