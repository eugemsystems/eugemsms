<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;

class MiscServerError extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.pages.pages-misc-server-error', ['pageConfigs' => $pageConfigs]);
    }
}
