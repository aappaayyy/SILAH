<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Enums\WhatsappStatus;
use App\Http\Controllers\Controller;
use App\Models\HotspotAccount;
use App\Models\Registration;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'pending'  => Registration::where('status', RequestStatus::Pending)->count(),
            'unlinked' => HotspotAccount::unlinked()->count(),
            'waFailed' => WhatsappMessage::where('status', WhatsappStatus::Failed)
                ->where('created_at', '>', now()->subDay())->count(),
        ]);
    }
}
