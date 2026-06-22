<?php

namespace App\Http\Controllers;

class PokemonController extends Controller
{
    public function index()
    {
        return view('pokemon.index');
    }
}
