<?php

use App\Support\Tenancy\SalonHostResolver;
use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    $saloon = request()->attributes->get(SalonHostResolver::REQUEST_ATTRIBUTE);

    return view('app', [
        'salonWorkspace' => $saloon ? [
            'id' => $saloon->id,
            'name' => $saloon->name,
            'domain' => $saloon->domain,
        ] : null,
    ]);
})->where('any', '.*');
