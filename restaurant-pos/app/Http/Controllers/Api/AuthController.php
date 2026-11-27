<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** دخول الموظفين: مدير / كاشير / طيار */
    public function staffLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('بيانات الدخول غير صحيحة')]);
        }

        return [
            'token' => $user->createToken($data['device_name'] ?? 'mobile', ['staff'])->plainTextToken,
            'actor' => 'staff',
            'user' => $this->staffPayload($user),
        ];
    }

    public function customerRegister(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'address' => ['nullable', 'string', 'max:1000'],
            'delivery_area_id' => ['nullable', 'exists:delivery_areas,id'],
        ]);

        $customer = Customer::firstWhere('phone', $data['phone']);
        if ($customer?->password) {
            throw ValidationException::withMessages(['phone' => __('رقم الموبايل مسجل بالفعل، سجل دخول')]);
        }

        // لو العميل طلب قبل كده من الكاشير، نكمل على نفس الحساب
        $customer ??= new Customer(['phone' => $data['phone']]);
        $customer->fill($data)->save();

        return response()->json([
            'token' => $customer->createToken('customer-app', ['customer'])->plainTextToken,
            'actor' => 'customer',
            'user' => $this->customerPayload($customer),
        ], 201);
    }

    public function customerLogin(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::firstWhere('phone', $data['phone']);
        if (! $customer?->password || ! Hash::check($data['password'], $customer->password)) {
            throw ValidationException::withMessages(['phone' => __('بيانات الدخول غير صحيحة')]);
        }

        return [
            'token' => $customer->createToken('customer-app', ['customer'])->plainTextToken,
            'actor' => 'customer',
            'user' => $this->customerPayload($customer),
        ];
    }

    public function me(Request $request)
    {
        $actor = $request->user();

        return $actor instanceof Customer
            ? ['actor' => 'customer', 'user' => $this->customerPayload($actor)]
            : ['actor' => 'staff', 'user' => $this->staffPayload($actor)];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function staffPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'driver_id' => $user->driver?->id,
        ];
    }

    public static function customerPayload(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'address' => $c->address,
            'delivery_area_id' => $c->delivery_area_id,
        ];
    }
}
