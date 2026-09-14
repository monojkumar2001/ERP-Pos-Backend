<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $this->requireCompany($request);

        $company = $request->user()->company;
        $trialEnds = $company?->trial_ends_at;

        return ApiResponse::success([
            'current_plan' => 'Business',
            'next_billing_date' => $trialEnds
                ? $trialEnds->copy()->addMonth()->format('d M Y')
                : now()->addMonth()->format('d M Y'),
            'trial_ends_at' => $trialEnds?->toDateString(),
            'plans' => [
                [
                    'name' => 'Starter',
                    'price' => '৳ 1,999',
                    'period' => '/month',
                    'features' => ['Accounting', 'Sales', 'Purchase', 'Inventory', '2 Users'],
                    'current' => false,
                ],
                [
                    'name' => 'Business',
                    'price' => '৳ 4,999',
                    'period' => '/month',
                    'features' => ['All Starter modules', 'POS', 'HRM', 'Reports', '10 Users'],
                    'current' => true,
                ],
                [
                    'name' => 'Enterprise',
                    'price' => 'Custom',
                    'period' => '',
                    'features' => ['All modules', 'Manufacturing', 'Priority support', 'Unlimited users'],
                    'current' => false,
                ],
            ],
        ], 'Subscriptions');
    }
}
