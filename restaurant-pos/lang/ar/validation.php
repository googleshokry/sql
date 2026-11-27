<?php

// رسائل التحقق بالعربي (القواعد غير المذكورة ترجع للإنجليزي)
return [
    'array' => 'حقل :attribute لازم يكون قائمة.',
    'boolean' => 'حقل :attribute لازم يكون صح أو خطأ.',
    'email' => 'حقل :attribute لازم يكون بريد إلكتروني صحيح.',
    'enum' => 'قيمة :attribute غير صحيحة.',
    'exists' => 'قيمة :attribute غير موجودة.',
    'in' => 'قيمة :attribute غير صحيحة.',
    'integer' => 'حقل :attribute لازم يكون رقم صحيح.',
    'max' => [
        'array' => 'حقل :attribute لا يزيد عن :max عناصر.',
        'numeric' => 'حقل :attribute لا يزيد عن :max.',
        'string' => 'حقل :attribute لا يزيد عن :max حرف.',
    ],
    'min' => [
        'array' => 'حقل :attribute لازم يكون فيه :min عناصر على الأقل.',
        'numeric' => 'حقل :attribute لازم يكون :min على الأقل.',
        'string' => 'حقل :attribute لازم يكون :min حروف على الأقل.',
    ],
    'numeric' => 'حقل :attribute لازم يكون رقم.',
    'required' => 'حقل :attribute مطلوب.',
    'required_if' => 'حقل :attribute مطلوب.',
    'string' => 'حقل :attribute لازم يكون نص.',
    'unique' => 'قيمة :attribute مستخدمة قبل كده.',

    'attributes' => [
        'name' => 'الاسم',
        'phone' => 'الموبايل',
        'email' => 'البريد',
        'password' => 'كلمة المرور',
        'address' => 'العنوان',
        'delivery_area_id' => 'المنطقة',
        'items' => 'الأصناف',
        'type' => 'نوع الطلب',
        'table_id' => 'الطاولة',
        'driver_id' => 'الطيار',
        'payment_method' => 'طريقة الدفع',
        'paid_amount' => 'المبلغ المدفوع',
        'discount' => 'الخصم',
        'status' => 'الحالة',
    ],
];
