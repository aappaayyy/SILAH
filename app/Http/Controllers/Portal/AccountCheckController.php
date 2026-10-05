<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckAccountRequest;
use App\Models\Civitas;
use App\Models\Registration;
use App\Support\PortalFlow;
use Illuminate\Http\Request;

class AccountCheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Civitas $civitas)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Civitas $civitas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Civitas $civitas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Civitas $civitas)
    {
        //
    }

    public function check(CheckAccountRequest $request)
    {
        $identifier = $request->validated('identifier');
        $civitas    = Civitas::findByIdentifier($identifier);

        $request->session()->regenerate();
        PortalFlow::clear();

        if ($civitas) {
            session()->put('reset_flow', [
                'civitas_id' => $civitas->id,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
        } else {
            $reg = Registration::latestFor($identifier);

            $tampil = $reg && (
                $reg->status === RequestStatus::Pending
                || ($reg->status === RequestStatus::Rejected && $reg->reviewed_at?->gt(now()->subDays(7)))
            );

            session()->put('register_flow', [
                'identifier'      => $identifier,
                'registration_id' => $tampil ? $reg->id : null,
                'expires_at'      => now()->addMinutes(30)->timestamp,
            ]);
        }

        return redirect()->route('home');
    }
}
