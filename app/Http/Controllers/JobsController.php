<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class JobsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Jobs/Index');
    }
}
