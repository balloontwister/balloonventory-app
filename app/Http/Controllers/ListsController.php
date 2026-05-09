<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ListsController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Lists/Create');
    }

    public function show(string $list): Response
    {
        return Inertia::render('Lists/Show', ['listId' => $list]);
    }

    public function edit(string $list): Response
    {
        return Inertia::render('Lists/Edit', ['listId' => $list]);
    }
}
