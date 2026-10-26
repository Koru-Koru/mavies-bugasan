<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Mail\CustomerPasswordResetMail;
use App\Models\Customer;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CustomerController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function resetPassword(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        if (! $customer->canLogIn()) {
            throw new DomainException('Walk-in customers have no login to reset.');
        }

        $temporary = $this->auth->resetCustomerPassword($customer, $request->user('admin'));

        Mail::to($customer->email, $customer->fullName())
            ->send(new CustomerPasswordResetMail($temporary, $customer->fullName()));

        return response()->json(['message' => 'A temporary password was emailed to '.$customer->email.'.']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if ($request->filled('blacklisted')) {
            $query->where('blacklisted', $request->boolean('blacklisted'));
        }

        $page = $query->orderByDesc('created_at')->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $page->items(),
            'pagination' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'pages' => $page->lastPage(),
            ],
        ]);
    }

    public function show(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $orders = \Illuminate\Support\Facades\DB::table('v_orders')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['customer' => $customer, 'orders' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:90'],
            'phone' => ['required', 'string', 'max:20'],
            'address_city' => ['nullable', 'string', 'max:90'],
        ]);

        $customer = $this->auth->createWalkIn($data, $request->user('admin'));

        return response()->json(['customer' => $customer], 201);
    }

    public function update(Request $request, int $customerId): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:60'],
            'last_name' => ['sometimes', 'string', 'max:90'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'address_bldg' => ['sometimes', 'nullable', 'string', 'max:90'],
            'address_street' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address_brgy' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address_city' => ['sometimes', 'nullable', 'string', 'max:90'],
            'address_notes' => ['sometimes', 'nullable', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
            'customer_type' => ['sometimes', 'string', 'in:New,Returning'],
        ]);

        $customer = $this->auth->updateCustomer(Customer::findOrFail($customerId), $data, $request->user('admin'));

        return response()->json(['customer' => $customer]);
    }

    public function blacklist(Request $request, int $customerId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $customer = $this->auth->blacklist(Customer::findOrFail($customerId), $data['reason'], $request->user('admin'));

        return response()->json(['customer' => $customer]);
    }

    public function whitelist(Request $request, int $customerId): JsonResponse
    {
        $customer = $this->auth->whitelist(Customer::findOrFail($customerId), $request->user('admin'));

        return response()->json(['customer' => $customer]);
    }
}
