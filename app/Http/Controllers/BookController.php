<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $cities = DB::table('books')
        ->select('city')
        ->distinct()
        ->pluck('city')
        ->toArray();
        
        $filterCity = $request->query('city');
        $filterMinPrice = $request->query('minPrice');
        $filterMaxPrice = $request->query('maxPrice');

        $query = Book::query();

        if (!empty($filterCity)) {
            $query->where('city', $filterCity);
        }
        if (!empty($filterMinPrice)) {
            $query->where('price', '>=', $filterMinPrice);
        }
        if (!empty($filterMaxPrice)) {
            $query->where('price', '<=', $filterMaxPrice);
        }

        $books = $query->paginate(10);

        foreach ($books as $book) {
            $book->main_image = asset('storage/' . $book->main_image);
        }

        return Inertia::render('Main', [
            'cities' => $cities,
            'books' => $books,
        ]);
    }
    
    public function getEstate($id)
    {
        $item = Book::where('id', $id)->first();

        $item->main_image = asset('storage/' . $item->main_image);

        return Inertia::render('SingleBook', [
            'item' => $item
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book)
    {
        //
    }
}
