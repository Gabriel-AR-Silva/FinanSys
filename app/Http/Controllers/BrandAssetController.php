<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandAssetController extends Controller
{
    public function favicon(): BinaryFileResponse
    {
        return response()->file(public_path('finansys-favicon-32.png'), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function social(): BinaryFileResponse
    {
        return response()->file(public_path('finansys-social.png'), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
