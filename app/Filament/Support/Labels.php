<?php

namespace App\Filament\Support;

class Labels
{
    public const STATUS = [
        'draft' => 'مسودة',
        'posted' => 'مُرحّل',
        'sent' => 'مُرسل',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'invoiced' => 'تم التحويل لفاتورة',
        'received' => 'تم الاستلام',
        'cancelled' => 'ملغي',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray',
        'posted' => 'success',
        'sent' => 'info',
        'accepted' => 'success',
        'rejected' => 'danger',
        'invoiced' => 'primary',
        'received' => 'primary',
        'cancelled' => 'danger',
    ];

    public const PAYMENT_STATUS = ['unpaid' => 'غير مدفوعة', 'partial' => 'مدفوعة جزئياً', 'paid' => 'مدفوعة'];

    public const PAYMENT_STATUS_COLORS = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'];
}
