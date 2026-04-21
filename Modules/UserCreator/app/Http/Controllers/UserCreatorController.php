<?php

namespace Modules\UserCreator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserCreatorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = User::all();
        return response()->json($user, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //return view('usercreator::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:admin,barista,seniorBarista',
            'password'  => 'required|string|confirmed',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);
        return response()->json([
            'user'  => $user
        ], 201);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        //return view('usercreator::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //return view('usercreator::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'کاربر با موفقیت حذف شد.',
        ], 200);
    }
}
